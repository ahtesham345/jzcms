<?php

namespace App\Http\Requests\Admin;

use App\Models\StudentAcademicEnrollment;
use App\Models\StudentResult;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Records one madrassa student's Grand Test result for one term.
 *
 * Nothing the browser says about the student is trusted. The form names an
 * enrollment by id; the track, the student it belongs to and whether it is
 * still active are all read back from the database here. The selects on the
 * page narrow what an administrator sees, they do not decide what is
 * accepted.
 *
 * The percentage and the grade are not validated because they are not
 * submitted. The model computes both from the marks on every save, so a
 * hand-edited request carrying its own percentage changes nothing.
 */
class StoreStudentResultRequest extends FormRequest
{
    /**
     * The enrollment named by this request, resolved once.
     */
    private ?StudentAcademicEnrollment $resolvedEnrollment = null;

    /**
     * Whether the enrollment lookup has already been attempted.
     *
     * Kept separately from the value: "not found" is a legitimate result
     * and must not send the query round again on every check below.
     */
    private bool $enrollmentResolved = false;

    /**
     * Determine if the user is authorized to make this request.
     *
     * The route sits inside the authenticated area, which is what decides
     * who may reach it at all. Everything this class does beyond that is
     * about which rows the request may touch.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The result being edited, or null when one is being created.
     */
    protected function existingResult(): ?StudentResult
    {
        return null;
    }

    /**
     * The id of the result being edited, if any.
     *
     * Used to exclude the row from the duplicate check, so re-saving a
     * result under the same term does not conflict with itself.
     */
    protected function resultId(): ?int
    {
        return $this->existingResult()?->id;
    }

    /**
     * The enrollment this result is being written against.
     *
     * On a create that is whatever the form named; the checks below decide
     * whether it may be used.
     */
    protected function enrollmentId(): ?int
    {
        $id = $this->input('student_academic_enrollment_id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Whether the enrollment must still be active to be written against.
     *
     * True when creating: a new result only belongs to a placement the
     * student currently holds. Editing relaxes it, because a result
     * recorded under a then-active enrollment must stay correctable after
     * the student has been promoted out of it.
     */
    protected function requiresActiveEnrollment(): bool
    {
        return true;
    }

    /**
     * Load the enrollment named by this request, with its student.
     */
    protected function enrollment(): ?StudentAcademicEnrollment
    {
        if ($this->enrollmentResolved) {
            return $this->resolvedEnrollment;
        }

        $this->enrollmentResolved = true;

        $id = $this->enrollmentId();

        return $this->resolvedEnrollment = $id === null
            ? null
            : StudentAcademicEnrollment::with('student')->find($id);
    }

    /**
     * Reduce a blank remark to null before anything is checked.
     *
     * An untouched textarea arrives as an empty string, which would be
     * stored as '' rather than as nothing.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('remarks')) {
            return;
        }

        $remarks = $this->input('remarks');

        if (is_string($remarks)) {
            $this->merge(['remarks' => trim($remarks) === '' ? null : trim($remarks)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Carried on the form and checked against the enrollment below.
            // On its own it proves nothing; it is what makes "this
            // enrollment belongs to somebody else" an error the admin sees
            // rather than a result filed under the wrong student.
            'student_id' => ['required', 'integer', 'exists:students,id'],

            'student_academic_enrollment_id' => [
                'required',
                'integer',
                'exists:student_academic_enrollments,id',
            ],

            'term' => [
                'required',
                Rule::in(StudentResult::TERMS),

                // One result per enrollment per term per test type. The
                // enrollment stands for the student, the session and the
                // track together, so this covers the whole duplicate rule.
                // The unique index behind it is the final guard.
                Rule::unique('student_results', 'term')
                    ->where('student_academic_enrollment_id', $this->enrollmentId())
                    ->where('test_type', $this->input('test_type'))
                    ->ignore($this->resultId()),
            ],

            'test_type' => ['required', Rule::in(StudentResult::TEST_TYPES)],

            // A paper out of nothing cannot be scored, so the total is
            // strictly greater than zero rather than merely non-negative.
            // The upper bound is a sanity limit, not a rule of the
            // institution: it exists so a mistyped total cannot overflow
            // the column.
            'total_marks' => ['required', 'numeric', 'gt:0', 'max:999999.99'],

            // Zero is a legitimate score; less than zero is not. The
            // "not more than the total" rule is the one that makes a
            // percentage meaningful, and it is checked here rather than in
            // the controller so the admin is told which field is wrong.
            'obtained_marks' => ['required', 'numeric', 'min:0', 'lte:total_marks'],

            'result_date' => ['required', 'date_format:Y-m-d'],

            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Check the request against the enrollment it names.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Only worth doing once the ids are structurally sound;
            // otherwise every check below would fail for the same reason
            // and bury the one that matters.
            if ($validator->errors()->hasAny(['student_academic_enrollment_id', 'student_id'])) {
                return;
            }

            $enrollment = $this->enrollment();

            if ($enrollment === null) {
                $validator->errors()->add(
                    'student_academic_enrollment_id',
                    'The selected academic enrollment does not exist.'
                );

                return;
            }

            if (! $this->enrollmentBelongsToStudent($validator, $enrollment)) {
                return;
            }

            $this->enrollmentIsUsable($validator, $enrollment);
        });
    }

    /**
     * Refuse an enrollment that belongs to somebody else.
     *
     * The whole point of carrying student_id on the form: without this an
     * edited id in the request would file one student's result under
     * another student's placement.
     */
    private function enrollmentBelongsToStudent(Validator $validator, StudentAcademicEnrollment $enrollment): bool
    {
        if ((int) $enrollment->student_id === (int) $this->input('student_id')) {
            return true;
        }

        $validator->errors()->add(
            'student_academic_enrollment_id',
            'That academic enrollment does not belong to the selected student.'
        );

        return false;
    }

    /**
     * Refuse an enrollment this module may not record a result against.
     *
     * The track is the rule that matters most. A Hifz + School student
     * holds a school enrollment too, and it must never be the one a result
     * is filed against; a school-only student holds nothing else, so the
     * same check is what refuses them outright.
     */
    private function enrollmentIsUsable(Validator $validator, StudentAcademicEnrollment $enrollment): bool
    {
        if (! StudentResult::isResultableEnrollment($enrollment)) {
            $validator->errors()->add(
                'student_academic_enrollment_id',
                'A result can only be recorded against a Madrassa enrollment.'
            );

            return false;
        }

        if ($this->requiresActiveEnrollment() && $enrollment->status !== 'Active') {
            $validator->errors()->add(
                'student_academic_enrollment_id',
                "A result cannot be created against a {$enrollment->status} enrollment."
            );

            return false;
        }

        return true;
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'term.unique' => 'This student already has a result for that term and test. Edit the existing result instead of creating another one.',
            'term.in' => 'Choose either First Term or Final Term.',
            'test_type.in' => 'Grand Test is the only test type a result can be recorded for.',
            'total_marks.gt' => 'Total marks must be greater than zero.',
            'obtained_marks.min' => 'Obtained marks cannot be negative.',
            'obtained_marks.lte' => 'Obtained marks cannot be greater than the total marks.',
            'result_date.date_format' => 'Enter the result date as a valid calendar date.',
            'student_academic_enrollment_id.exists' => 'The selected academic enrollment does not exist.',
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
            'student_academic_enrollment_id' => 'academic enrollment',
            'student_id' => 'student',
            'term' => 'term',
            'test_type' => 'test type',
            'total_marks' => 'total marks',
            'obtained_marks' => 'obtained marks',
            'result_date' => 'result date',
        ];
    }
}
