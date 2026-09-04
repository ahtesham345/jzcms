<?php

namespace App\Http\Requests\Admin;

use App\Models\StudentAcademicEnrollment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Records one academic placement for a student.
 *
 * Every relationship is checked against the database rather than trusted
 * from the form: the dependent selects narrow what the admin sees, they do
 * not decide what is accepted.
 */
class StoreStudentEnrollmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The student the enrollment is being recorded against.
     */
    protected function studentId(): ?int
    {
        return $this->route('student') ? (int) $this->route('student') : null;
    }

    /**
     * The enrollment being edited, if this is an update.
     */
    protected function enrollmentId(): ?int
    {
        return null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_session_id' => [
                'required',
                'integer',
                'exists:academic_sessions,id',

                // One enrollment per student per session per track. Scoped by
                // track so a Hifz + School student can hold both.
                Rule::unique('student_academic_enrollments', 'academic_session_id')
                    ->where('student_id', $this->studentId())
                    ->where('academic_track', $this->input('academic_track'))
                    ->ignore($this->enrollmentId()),
            ],

            'academic_track' => ['required', Rule::in(StudentAcademicEnrollment::ACADEMIC_TRACKS)],

            // Inactive master data is never a valid placement.
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where('status', true),
            ],

            // The class must belong to the chosen department.
            'academic_class_id' => [
                'required',
                'integer',
                Rule::exists('academic_classes', 'id')
                    ->where('status', true)
                    ->where('department_id', $this->input('department_id')),
            ],

            // Optional: a class may be run without sections. A supplied one
            // must belong to the chosen class.
            'section_id' => [
                'nullable',
                'integer',
                Rule::exists('sections', 'id')
                    ->where('status', true)
                    ->where('academic_class_id', $this->input('academic_class_id')),
            ],

            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],

            'status' => ['required', Rule::in(StudentAcademicEnrollment::STATUSES), function ($attribute, $value, $fail) {
                if ($value !== 'Active') {
                    return;
                }

                // A student may hold only one active enrollment per track.
                // The previous one is completed or withdrawn explicitly by
                // the admin; nothing is deactivated behind their back.
                $conflict = DB::table('student_academic_enrollments')
                    ->where('student_id', $this->studentId())
                    ->where('academic_track', $this->input('academic_track'))
                    ->where('status', 'Active')
                    ->when($this->enrollmentId(), fn ($query, $id) => $query->where('id', '!=', $id))
                    ->exists();

                if ($conflict) {
                    $track = $this->input('academic_track');
                    $fail("This student already has an active {$track} enrollment. Complete or withdraw it first.");
                }
            }],

            'notes' => ['nullable', 'string'],
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
            'academic_session_id.unique' => 'This student already has an enrollment for that session and track.',
            'academic_class_id.exists' => 'The selected class is inactive or does not belong to the selected department.',
            'section_id.exists' => 'The selected section is inactive or does not belong to the selected class.',
            'department_id.exists' => 'The selected department is invalid or inactive.',
            'end_date.after_or_equal' => 'The completion date cannot be before the enrollment date.',
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
            'academic_session_id' => 'academic session',
            'academic_class_id' => 'class',
            'section_id' => 'section',
            'department_id' => 'department',
            'start_date' => 'enrollment date',
            'end_date' => 'completion date',
        ];
    }
}
