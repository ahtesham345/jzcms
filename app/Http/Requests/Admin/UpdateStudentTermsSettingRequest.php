<?php

namespace App\Http\Requests\Admin;

use App\Models\DepartmentTerm;
use App\Support\StudentTerms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What may be saved on the student instructions page.
 *
 * One block of text per department, posted as terms[<department id>]. The
 * ids are checked against the departments the page actually offered rather
 * than against the departments table, so a hand-crafted submission cannot
 * attach instructions to a department the student type mapping never places
 * anybody in - a combined one, for instance.
 *
 * The text itself is barely constrained. It is Urdu prose whose line breaks
 * are the structure, so nothing here trims, reflows or re-encodes it; only
 * its length is bounded.
 */
class UpdateStudentTermsSettingRequest extends FormRequest
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
            'terms' => ['required', 'array'],
            'terms.*' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * Get the department ids the settings page offers.
     *
     * @return array<int, int>
     */
    private function editableDepartmentIds(): array
    {
        return array_map('intval', array_keys(StudentTerms::editableByDepartment()));
    }

    /**
     * Add the rule that the posted ids are ones the page offered.
     */
    public function withValidator($validator): void
    {
        $allowed = $this->editableDepartmentIds();

        $validator->after(function ($validator) use ($allowed) {
            foreach (array_keys((array) $this->input('terms', [])) as $departmentId) {
                if (! in_array((int) $departmentId, $allowed, true)) {
                    $validator->errors()->add(
                        'terms',
                        'Instructions may only be saved for the departments shown on this page.'
                    );

                    return;
                }
            }
        });
    }

    /**
     * Get the instructions to save, keyed by department id.
     *
     * The block is normalised through DepartmentTerm's own line splitting
     * and rejoining, so what is stored is what will be read back: blank
     * lines dropped, carriage returns gone, the Urdu untouched. Storing the
     * raw textarea instead would let a stray blank line become an empty
     * instruction in the list.
     *
     * @return array<int, string>
     */
    public function termsByDepartment(): array
    {
        $saved = [];

        foreach ((array) $this->validated()['terms'] as $departmentId => $block) {
            $saved[(int) $departmentId] = DepartmentTerm::joinLines(
                DepartmentTerm::splitLines(is_string($block) ? $block : null)
            );
        }

        return $saved;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'terms' => 'instructions',
            'terms.*' => 'instructions',
        ];
    }
}
