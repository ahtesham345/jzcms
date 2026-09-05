<?php

namespace App\Http\Requests\Admin;

use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Moves one track of a student's placement into a new session/class.
 *
 * Everything the dependent selects narrow is re-checked here: the class
 * against its department, the section against its class, and all three
 * against being active. The promotion itself is carried out by
 * Student::promote() inside a transaction.
 */
class PromoteStudentRequest extends FormRequest
{
    /**
     * The student being promoted, resolved once.
     */
    private ?Student $student = null;

    /**
     * The active enrollment on the chosen track, resolved once.
     */
    private ?StudentAcademicEnrollment $current = null;

    private bool $currentResolved = false;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the student being promoted.
     *
     * findOrFail, so an unknown id is a 404 here rather than a validation
     * error about an enrollment that could never have existed.
     */
    public function student(): Student
    {
        return $this->student ??= Student::findOrFail($this->route('student'));
    }

    /**
     * Get the active enrollment the promotion moves on from.
     */
    public function currentEnrollment(): ?StudentAcademicEnrollment
    {
        if ($this->currentResolved) {
            return $this->current;
        }

        $this->currentResolved = true;

        $track = $this->input('academic_track');

        $this->current = in_array($track, StudentAcademicEnrollment::ACADEMIC_TRACKS, true)
            ? $this->student()->activeEnrollmentForTrack($track)
            : null;

        return $this->current;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_track' => [
                'required',
                Rule::in(StudentAcademicEnrollment::ACADEMIC_TRACKS),
                function ($attribute, $value, $fail) {
                    // Nothing to promote from: the track has no active
                    // placement, so there is no progression to record.
                    if ($this->currentEnrollment() === null) {
                        $fail("This student has no active {$value} enrollment to promote from.");
                    }
                },
            ],

            'academic_session_id' => [
                'required',
                'integer',
                // Only a usable session, per the existing session module.
                Rule::exists('academic_sessions', 'id')->where('status', true),

                // The same guard the enrollment form uses, and the same one
                // Student::promote() re-checks under the lock.
                //
                // School only. A school class runs for the academic year, so
                // a student holds one school enrollment per session. The
                // madrassa is promoted on completion instead: a student who
                // finishes Nazra in July is promoted in July, into the
                // session that is running, and applying this rule to that
                // track is what forced the promotion to wait for the session
                // to end.
                ...(StudentAcademicEnrollment::trackIsSessionBound($this->input('academic_track'))
                    ? [
                        Rule::unique('student_academic_enrollments', 'academic_session_id')
                            ->where('student_id', $this->student()?->id)
                            ->where('academic_track', $this->input('academic_track')),
                    ]
                    : []),
            ],

            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where('status', true),
            ],

            'academic_class_id' => [
                'required',
                'integer',
                Rule::exists('academic_classes', 'id')
                    ->where('status', true)
                    ->where('department_id', $this->input('department_id')),

                function ($attribute, $value, $fail) {
                    // Promoting into the placement the student already holds
                    // records no progression at all.
                    if ($this->targetMatchesCurrent()) {
                        $fail('The target placement is the same as the current one. Choose a different session, class or section.');
                    }
                },
            ],

            // Optional throughout: a class may be run without sections.
            'section_id' => [
                'nullable',
                'integer',
                Rule::exists('sections', 'id')
                    ->where('status', true)
                    ->where('academic_class_id', $this->input('academic_class_id')),
            ],

            'promotion_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    $current = $this->currentEnrollment();

                    // The date closes the old enrollment and opens the new
                    // one, so it cannot precede the placement it ends.
                    if ($current && $current->start_date && $value < $current->start_date->format('Y-m-d')) {
                        $fail('The promotion date cannot be before the current enrollment started on '
                            . $current->start_date->format('d M, Y') . '.');
                    }
                },
            ],

            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Determine whether the target is the placement already held.
     */
    private function targetMatchesCurrent(): bool
    {
        $current = $this->currentEnrollment();

        if ($current === null) {
            return false;
        }

        $section = $this->input('section_id');

        return (int) $this->input('academic_session_id') === (int) $current->academic_session_id
            && (int) $this->input('department_id') === (int) $current->department_id
            && (int) $this->input('academic_class_id') === (int) $current->academic_class_id
            && ($section === null || $section === '' ? null : (int) $section) === $current->section_id;
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'academic_session_id.exists' => 'The selected academic session is invalid or inactive.',
            'academic_session_id.unique' => 'This student already has an enrollment for that session and track.',
            'department_id.exists' => 'The selected department is invalid or inactive.',
            'academic_class_id.exists' => 'The selected class is inactive or does not belong to the selected department.',
            'section_id.exists' => 'The selected section is inactive or does not belong to the selected class.',
        ];
    }

    /**
     * Get the custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'academic_session_id' => 'target academic session',
            'academic_class_id' => 'target class',
            'section_id' => 'target section',
            'department_id' => 'target department',
            'promotion_date' => 'promotion date',
        ];
    }
}
