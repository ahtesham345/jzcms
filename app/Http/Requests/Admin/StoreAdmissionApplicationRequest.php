<?php

namespace App\Http\Requests\Admin;

use App\Models\AdmissionApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdmissionApplicationRequest extends FormRequest
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
            'admission_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            // Not the full status list: a new application cannot start out
            // Passed/Failed (no test result yet) or Approved (no student yet).
            'status' => ['required', Rule::in(AdmissionApplication::creatableStatuses())],
        ];
    }
}
