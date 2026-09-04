<?php

namespace App\Http\Requests\Admin;

use App\Models\DisciplineRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Records one discipline incident against one student.
 *
 * Nothing the browser says is taken on trust. The student is named by id
 * and its existence is checked against the students table; the category and
 * the severity are checked against the two fixed lists on the model, so a
 * hand-edited select cannot file an incident under a category this
 * institution does not have.
 *
 * recorded_by is not validated because it is not accepted. It is not a rule
 * here, not in the model's fillable list and not read from the request
 * anywhere: the controller sets it from the authenticated user. A request
 * carrying its own recorded_by changes nothing.
 */
class StoreDisciplineRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The route sits inside the authenticated area, which is what decides
     * who may reach it at all. This class is about what the request may
     * say, not about who is making it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reduce blank optional text to null before anything is checked.
     *
     * An untouched input arrives as an empty string, which would be stored
     * as '' rather than as nothing and would then read as an action having
     * been taken when none was.
     */
    protected function prepareForValidation(): void
    {
        foreach (['action_taken', 'remarks'] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => trim($value) === '' ? null : trim($value)]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The student must exist. There is no enrollment field beside
            // it and none is wanted: an incident belongs to the person, not
            // to the class they happened to be sitting in that morning.
            'student_id' => ['required', 'integer', 'exists:students,id'],

            'date' => ['required', 'date_format:Y-m-d'],

            // Rule::in against the model's list rather than a repeated
            // array, so the form, the filters, the column's enum and this
            // rule can never drift apart.
            'category' => ['required', Rule::in(DisciplineRecord::CATEGORIES)],

            'severity' => ['required', Rule::in(DisciplineRecord::SEVERITIES)],

            'description' => ['required', 'string', 'max:2000'],

            // Free text: "Verbal Warning", "Parent contacted", whatever the
            // office actually did. Optional, because an incident can be
            // recorded before anything has been decided about it.
            'action_taken' => ['nullable', 'string', 'max:255'],

            'remarks' => ['nullable', 'string', 'max:2000'],
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
            'student_id.required' => 'Choose the student this incident belongs to.',
            'student_id.exists' => 'The selected student does not exist.',
            'date.date_format' => 'Enter the incident date as a valid calendar date.',
            'category.in' => 'Choose one of the listed discipline categories.',
            'severity.in' => 'Severity must be Low, Medium or High.',
            'description.required' => 'Describe what happened.',
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
            'student_id' => 'student',
            'date' => 'incident date',
            'action_taken' => 'action taken',
        ];
    }
}
