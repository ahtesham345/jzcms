<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What may be changed about one semester of the Computer course.
 *
 * The dates and the curriculum, plus the name and whether the stage is in
 * use. The order is not editable here: it is what makes the course a
 * sequence, and it is unique within the course, so changing it through an
 * edit form is how two second semesters happen.
 */
class UpdateComputerCourseSemesterRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            // Both optional: a semester nobody has dated yet is a semester
            // waiting to be dated, not an invalid one. Given a start, the end
            // may not come before it - a stage cannot finish before it opens.
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],

            // What will be taught, as the admin writes it. Free text over
            // several lines, because a syllabus is a list of topics rather
            // than a value from a fixed set.
            'curriculum' => ['nullable', 'string', 'max:5000'],

            'status' => ['boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->boolean('status', true),
        ]);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'semester name',
            'start_date' => 'start date',
            'end_date' => 'end date',
            'curriculum' => 'curriculum',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
