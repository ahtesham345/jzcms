<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What may be changed about the Computer course itself.
 *
 * Its name, how long it runs and how many stages it is taught in. The
 * semesters are edited one at a time on their own form; changing the count
 * here does not add or remove any, because a stage that students are
 * standing in is not something a number field should be able to delete.
 */
class UpdateComputerCourseRequest extends FormRequest
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

            // Bounded so a typo cannot record a nought-year or a
            // fifty-year course. The institution's is three years, six
            // semesters; the bounds leave room around that without
            // pretending any number is meaningful.
            'duration_years' => ['required', 'integer', 'min:1', 'max:10'],
            'semester_count' => ['required', 'integer', 'min:1', 'max:20'],

            'description' => ['nullable', 'string', 'max:2000'],
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
            'name' => 'course name',
            'duration_years' => 'duration in years',
            'semester_count' => 'number of semesters',
        ];
    }
}
