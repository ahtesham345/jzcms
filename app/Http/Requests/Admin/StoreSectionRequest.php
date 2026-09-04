<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
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
            'academic_class_id' => ['required', 'exists:academic_classes,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sections')->where(function ($query) {
                    return $query->where('academic_class_id', $this->academic_class_id);
                }),
            ],
            'code' => ['required', 'string', 'max:20', 'unique:sections,code'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['boolean'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'academic_class_id' => 'class',
            'name' => 'section name',
            'code' => 'section code',
            'description' => 'description',
            'status' => 'status',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A section with this name already exists in the selected class.',
            'code.unique' => 'This section code is already in use.',
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
}
