<?php

namespace App\Http\Requests\Admin;

use App\Models\AdmissionApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleAdmissionTestsRequest extends FormRequest
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
            'student_type' => ['required', Rule::in(AdmissionApplication::STUDENT_TYPES)],
            'test_date' => ['required', 'date'],
            'test_time' => ['required', 'date_format:H:i,H:i:s'],
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
            'student_type.required' => 'Please choose the student type to schedule.',
            'student_type.in' => 'Please choose a valid student type.',
            'test_date.required' => 'Please choose the test date.',
            'test_time.required' => 'Please choose the test time.',
            'test_time.date_format' => 'The test time must be a valid time.',
        ];
    }
}
