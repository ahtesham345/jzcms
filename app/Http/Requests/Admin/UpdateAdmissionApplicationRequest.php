<?php

namespace App\Http\Requests\Admin;

use App\Models\AdmissionApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAdmissionApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The application being updated.
     */
    private ?AdmissionApplication $application = null;

    /**
     * The status the form actually submitted, before any derivation.
     */
    private ?string $submittedStatus = null;

    /**
     * Get the application being updated.
     */
    public function application(): ?AdmissionApplication
    {
        return $this->application ??= AdmissionApplication::find($this->route('admission'));
    }

    /**
     * Prepare the data for validation.
     *
     * A recorded test result drives the status, so the derived status is
     * merged in *before* validation. Doing it here rather than in the
     * controller means the workflow rules below are applied to the status
     * that will actually be saved, and cannot be side-stepped.
     *
     * Derivation only applies while the application is actually sitting at a
     * test stage. Otherwise the test result still present in the form would
     * drag a later status (Approved) backwards on every unrelated edit.
     */
    protected function prepareForValidation(): void
    {
        $this->submittedStatus = $this->input('status');

        $result = $this->input('test_result');
        $application = $this->application();

        if (! $application) {
            return;
        }

        // Scheduling the test on a Pending application moves it to Test
        // Scheduled without the admin having to change the dropdown too.
        if ($this->isSchedulingTransition($application)) {
            $this->merge(['status' => 'Test Scheduled']);

            return;
        }

        if (! $application->canRecordTestResult()) {
            return;
        }

        if (filled($result) && in_array($result, AdmissionApplication::TEST_RESULTS, true)) {
            $this->merge(['status' => $result]);
        }
    }

    /**
     * Determine whether this request schedules the test on a Pending application.
     *
     * Both a date and a time must be supplied, no result may be recorded yet,
     * and the admin must have left the status dropdown alone: an explicitly
     * chosen status is never silently overridden.
     */
    private function isSchedulingTransition(AdmissionApplication $application): bool
    {
        return $application->status === 'Pending'
            && $this->submittedStatus === $application->status
            && blank($this->input('test_result'))
            && blank($application->test_result)
            && filled($this->input('test_date'))
            && filled($this->input('test_time'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_name' => ['required', 'string', 'max:255'],
            'father_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['required', Rule::in(AdmissionApplication::GENDERS)],
            'b_form_number' => ['nullable', 'string', 'max:255'],
            'father_mobile' => ['required', 'string', 'max:255'],
            'mother_mobile' => ['nullable', 'string', 'max:255'],
            'permanent_address' => ['nullable', 'string'],
            'current_address' => ['nullable', 'string'],
            'student_type' => ['required', Rule::in(AdmissionApplication::STUDENT_TYPES)],

            // Correctable, and nullable: applications filed before the
            // session was recorded carry none, and somebody has to be able to
            // set one. Only an active session may be chosen, so an
            // application cannot be filed into a year that has been retired.
            //
            // Frozen once a student exists. By then the student's enrollment
            // has recorded a session of its own, and letting the application
            // disagree with it would leave two answers to one question.
            'academic_session_id' => $this->application()?->isLocked()
                ? ['nullable', Rule::in([$this->application()?->academic_session_id])]
                : [
                    'nullable',
                    'integer',
                    Rule::exists('academic_sessions', 'id')->where('status', true),
                ],

            'admission_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(AdmissionApplication::STATUSES)],

            // Admission test
            'test_date' => ['nullable', 'date'],
            'test_time' => ['nullable', 'date_format:H:i,H:i:s'],
            // Bounded by the decimal(5,2) column so an oversized value fails
            // validation instead of blowing up at the database.
            'test_marks' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'test_result' => ['nullable', Rule::in(AdmissionApplication::TEST_RESULTS)],
            'test_remarks' => ['nullable', 'string'],
        ];
    }

    /**
     * Enforce the admission workflow rules.
     *
     * These run server side regardless of what the status dropdown offered,
     * so a stale page or a hand-crafted request cannot skip a step.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $application = $this->application();

                if (! $application) {
                    return;
                }

                $current = $application->status;
                $new = $this->input('status');
                $result = $this->input('test_result');

                // A result may only be recorded while the test is in progress.
                if (filled($result)
                    && $result !== $application->test_result
                    && ! $application->canRecordTestResult()) {
                    $validator->errors()->add(
                        'test_result',
                        $application->isLocked()
                            ? 'The admission test result cannot be changed after a student record has been created.'
                            : "An admission test result can only be recorded while the application is Test Scheduled or Test Completed. This application is {$current}."
                    );

                    return;
                }

                // The admin picked a status that contradicts the test result.
                if (filled($result)
                    && $this->submittedStatus !== null
                    && $this->submittedStatus !== $current
                    && $this->submittedStatus !== $result
                    && $application->canRecordTestResult()) {
                    $validator->errors()->add(
                        'status',
                        "The status cannot be set to {$this->submittedStatus} while the admission test result is {$result}. A {$result} result moves the application to {$result}."
                    );

                    return;
                }

                // Nothing to check if the status is not moving.
                if ($new === null || $new === $current) {
                    $this->validateTestResultChange($validator, $application);

                    return;
                }

                // A student exists: the outcome is final.
                if ($application->isLocked()) {
                    $validator->errors()->add(
                        'status',
                        "This application's status cannot be changed because a student record ({$application->student?->registration_number}) has already been created from it."
                    );

                    return;
                }

                // Approved is reached only through the Approve Admission action.
                if ($new === 'Approved') {
                    $validator->errors()->add(
                        'status',
                        'An application cannot be set to Approved from the status dropdown. Use the Approve Admission action on the application page, which also creates the student record.'
                    );

                    return;
                }

                // Passed/Failed must agree with the recorded test result.
                if (in_array($new, AdmissionApplication::TEST_RESULTS, true) && $result !== $new) {
                    $validator->errors()->add(
                        'status',
                        "An application can only be marked {$new} when the admission test result is also {$new}. Record the test result first."
                    );

                    return;
                }

                if (! $this->isAllowedTransition($application, $new)) {
                    $allowed = $application->selectableStatuses();
                    $allowed = array_values(array_diff($allowed, [$current]));

                    $validator->errors()->add(
                        'status',
                        $allowed === []
                            ? "An application with the status {$current} cannot be moved to another status."
                            : "An application cannot move from {$current} to {$new}. The allowed next "
                                .(count($allowed) === 1 ? 'status is' : 'statuses are').': '.implode(', ', $allowed).'.'
                    );
                }
            },
        ];
    }

    /**
     * Determine whether the status may move to the submitted value.
     */
    private function isAllowedTransition(AdmissionApplication $application, string $new): bool
    {
        $allowed = AdmissionApplication::STATUS_TRANSITIONS[$application->status] ?? [];

        if (in_array($new, $allowed, true)) {
            return true;
        }

        // Entering a date and time schedules the test, so a Pending
        // application may move straight to Test Scheduled. Selecting that
        // status by hand from the dropdown remains blocked.
        if ($new === 'Test Scheduled' && $this->isSchedulingTransition($application)) {
            return true;
        }

        // Recording a result completes the test, so an application that is
        // still only Test Scheduled may move straight to Passed or Failed.
        return $application->canRecordTestResult()
            && in_array($new, AdmissionApplication::TEST_RESULTS, true)
            && $this->input('test_result') === $new;
    }

    /**
     * Guard test-result edits that do not themselves change the status.
     */
    private function validateTestResultChange(
        Validator $validator,
        AdmissionApplication $application
    ): void {
        $result = $this->input('test_result');

        // Clearing the result would leave a Passed/Failed status unsupported.
        if (blank($result) && in_array($application->status, AdmissionApplication::TEST_RESULTS, true)) {
            $validator->errors()->add(
                'test_result',
                "The test result cannot be cleared while the application status is {$application->status}."
            );
        }
    }

    /**
     * Get the custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'academic_session_id' => 'academic session',
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'academic_session_id.in' => 'The academic session cannot be changed after a student record has been created from this application.',
            'academic_session_id.exists' => 'The selected academic session does not exist or is no longer active.',
            'test_time.date_format' => 'The test time must be a valid time.',
            'test_marks.max' => 'The test marks may not be greater than 999.99.',
        ];
    }
}
