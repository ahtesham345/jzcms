<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesAcademicPlacement;
use App\Models\AdmissionApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    use ValidatesAcademicPlacement;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $studentId = $this->route('student');

        return [
            'registration_number' => ['required', 'string', 'max:255', 'unique:students,registration_number,'.$studentId],
            'roll_number' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
            'full_name' => ['required', 'string', 'max:255'],
            'father_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['required', 'in:Male,Female'],
            'b_form_number' => ['nullable', 'string', 'max:255'],
            'permanent_address' => ['nullable', 'string'],
            'current_address' => ['nullable', 'string'],
            'father_mobile' => ['required', 'string', 'max:255'],
            'mother_mobile' => ['nullable', 'string', 'max:255'],
            'emergency_contact' => ['required', 'string', 'max:255'],
            'admission_date' => ['required', 'date'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'student_status' => ['required', 'in:Active,Passed,Left'],
            'leaving_reason' => ['nullable', 'required_if:student_status,Left', 'string'],
            'student_type' => ['required', Rule::in(AdmissionApplication::STUDENT_TYPES)],
            'resident_type' => ['required', 'in:Local Resident,Outside Resident'],
            'medical_information' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],

            // The same placement rules creation runs on: editing cannot put a
            // student somewhere creating them could not.
            //
            // Parent links are deliberately not editable here. They are
            // managed from the student profile, which already owns that
            // form, so there is one place a link is made and unmade.
            ...$this->placementRules(),
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->placementMessages();
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
            ...$this->placementAttributes(),
        ];
    }
}
