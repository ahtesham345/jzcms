<?php

namespace App\Http\Requests\Admin;

use App\Models\Teacher;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
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
            // The teacher ID is immutable: it is displayed read only and can
            // never be changed through request data.
            'teacher_id' => ['nullable', 'prohibited'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
            'full_name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['required', Rule::in(Teacher::GENDERS)],
            'cnic_number' => ['nullable', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:255'],
            'alternate_mobile' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'joining_date' => ['required', 'date'],
            'teacher_status' => ['required', Rule::in(Teacher::STATUSES)],
            'notes' => ['nullable', 'string'],

            // Optional. Every id must be an existing *active* class, checked
            // at the database level so arbitrary ids cannot be injected.
            'academic_class_ids' => ['nullable', 'array'],
            'academic_class_ids.*' => [
                'integer',
                Rule::exists('academic_classes', 'id')->where('status', true),
            ],
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
            'teacher_id.prohibited' => 'The teacher ID is generated automatically and cannot be changed.',
            'academic_class_ids.*.exists' => 'One of the selected classes is invalid or inactive.',
            'photo.image' => 'The photo must be an image file.',
            'photo.mimes' => 'The photo must be a JPG, JPEG or PNG file.',
            'photo.max' => 'The photo may not be larger than 2MB.',
            'date_of_birth.before' => 'The date of birth must be in the past.',
        ];
    }
}
