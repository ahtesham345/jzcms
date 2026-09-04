<?php

namespace App\Http\Requests\Admin;

use App\Models\ParentGuardian;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Links one existing parent to one existing student.
 *
 * Used from both directions: the parent profile posts a student_id, the
 * student profile posts a parent_id, and the other side comes from the
 * route. Neither id is trusted — both are checked against their table.
 */
class LinkParentStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Take the id that belongs to the page from the route, not the form.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'parent_id' => $this->route('parent') ?? $this->input('parent_id'),
            'student_id' => $this->route('student') ?? $this->input('student_id'),
        ], fn ($value) => $value !== null));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['required', 'integer', 'exists:parents,id'],

            // The unique rule rejects a second link between the same pair;
            // the unique index on parent_student is the final guard.
            'student_id' => [
                'required',
                'integer',
                'exists:students,id',
                Rule::unique('parent_student', 'student_id')
                    ->where('parent_id', $this->input('parent_id')),
            ],

            'relationship_type' => ['required', Rule::in(ParentGuardian::RELATIONSHIP_TYPES)],

            // A student may have several parents, but only one primary of
            // each relationship type: no two primary Fathers, and no two
            // primary Mothers.
            'is_primary' => ['nullable', 'boolean', function ($attribute, $value, $fail) {
                if (! $this->boolean('is_primary')) {
                    return;
                }

                $type = $this->input('relationship_type');

                $taken = DB::table('parent_student')
                    ->where('student_id', $this->input('student_id'))
                    ->where('relationship_type', $type)
                    ->where('is_primary', true)
                    ->exists();

                if ($taken) {
                    $fail("This student already has a primary {$type}. Remove that primary first.");
                }
            }],
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
            'student_id.exists' => 'The selected student does not exist.',
            'student_id.unique' => 'This student is already linked to this parent.',
            'relationship_type.required' => 'Please choose a relationship.',
            'relationship_type.in' => 'The selected relationship is invalid.',
        ];
    }
}
