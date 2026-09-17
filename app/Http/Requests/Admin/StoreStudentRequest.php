<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesAcademicPlacement;
use App\Models\AdmissionApplication;
use App\Models\ParentGuardian;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
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
        return [
            // Registration number is auto-generated and must not be accepted
            // from the form. If present, it will be ignored.
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
            // The same list the admission application offers, so a student
            // cannot be created under a type admissions cannot produce.
            'student_type' => ['required', Rule::in(AdmissionApplication::STUDENT_TYPES)],
            'resident_type' => ['required', 'in:Local Resident,Outside Resident'],
            'medical_information' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],

            // Optional: link an existing parent while creating the student.
            // No parent record is ever created from these fields, so nothing
            // here can duplicate one.
            'parent_id' => ['nullable', 'integer', 'exists:parents,id'],
            'parent_relationship_type' => [
                'nullable',
                'required_with:parent_id',
                Rule::in(ParentGuardian::RELATIONSHIP_TYPES),
            ],
            'parent_is_primary' => ['nullable', 'boolean'],

            // Department -> class -> section, per track, checked against the
            // database rather than trusted from the form.
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
        return [
            'parent_id.exists' => 'The selected parent does not exist.',
            'parent_relationship_type.required_with' => 'Please choose how this parent is related to the student.',
            ...$this->placementMessages(),
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
            'parent_id' => 'parent',
            'parent_relationship_type' => 'relationship',
            ...$this->placementAttributes(),
        ];
    }
}
