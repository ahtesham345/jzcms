<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
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
            'registration_number' => ['required', 'string', 'max:255', 'unique:students,registration_number,' . $studentId],
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
            'department_id' => ['required', 'exists:departments,id'],
            'academic_class_id' => ['required', 'exists:academic_classes,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'student_status' => ['required', 'in:Active,Passed,Left'],
            'leaving_reason' => ['nullable', 'required_if:student_status,Left', 'string'],
            'student_type' => ['required', 'in:Hifz,Hifz + School,School,Dars-e-Nizami + Computer,Dars-e-Nizami'],
            'resident_type' => ['required', 'in:Local Resident,Outside Resident'],
            'medical_information' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
