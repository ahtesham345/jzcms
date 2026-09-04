<?php

namespace App\Http\Requests;

use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PublicAdmissionApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The admission form is open to the public, so no login is required.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Deliberately narrower than the admin requests: the public form cannot
     * set status, admission date, test results or any staff-only field.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Optional. Validated as a real image server side, not just by
            // extension, and capped at 2MB.
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],

            'student_name' => ['required', 'string', 'max:255'],
            'father_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['required', Rule::in(AdmissionApplication::GENDERS)],
            'b_form_number' => ['nullable', 'string', 'max:255'],
            'father_mobile' => ['required', 'string', 'max:255'],
            'mother_mobile' => ['nullable', 'string', 'max:255'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'current_address' => ['nullable', 'string', 'max:1000'],
            'student_type' => ['required', Rule::in(AdmissionApplication::STUDENT_TYPES)],
            'notes' => ['nullable', 'string', 'max:1000'],

            // "accepted" only passes for yes/on/1/true, so an unticked or
            // absent checkbox is rejected.
            'instructions_accepted' => ['required', 'accepted'],

            // The class must exist *and* belong to the department the chosen
            // student type maps to, so a tampered id from another department
            // is rejected at the database level.
            'madrassa_class_id' => $this->classRules('madrassa'),
            'school_class_id' => $this->classRules('school'),
        ];
    }

    /**
     * Refuse the submission when the institution has no current session.
     *
     * An application has to belong to an academic year. Rather than filing
     * one with no session - which would leave it off every session notice
     * with nobody able to tell why - the form comes back with an explanation
     * and nothing is created.
     *
     * Note what is *not* here: no rule for academic_session_id. The public
     * form neither sends nor accepts one, so validated() cannot carry a
     * session, whatever the browser posts. The session is resolved server
     * side in AdmissionApplication::createWithApplicationNumber().
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (AcademicSession::current() === null) {
                    $validator->errors()->add(
                        'academic_session',
                        'Admissions are closed at the moment because no academic session is open. Please contact the office.'
                    );
                }
            },
        ];
    }

    /**
     * Build the rules for one side of the class selection.
     *
     * @return array<int, mixed>
     */
    private function classRules(string $side): array
    {
        $departmentName = AdmissionApplication::departmentForSide(
            $this->input('student_type'),
            $side
        );

        // The chosen student type does not use this side at all.
        if ($departmentName === null) {
            return ['nullable', 'prohibited'];
        }

        $departmentId = Department::where('name', $departmentName)->value('id');

        return [
            'required',
            'integer',
            Rule::exists('academic_classes', 'id')
                ->where('department_id', $departmentId)
                ->where('status', true),
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
            'student_name.required' => 'Please enter the student\'s full name.',
            'father_name.required' => 'Please enter the father\'s full name.',
            'gender.required' => 'Please select the student\'s gender.',
            'gender.in' => 'Please select a valid gender.',
            'father_mobile.required' => 'Please enter a mobile number we can reach you on.',
            'student_type.required' => 'Please select the programme you are applying for.',
            'student_type.in' => 'Please select a valid programme.',
            'date_of_birth.before' => 'The date of birth must be in the past.',
            'photo.image' => 'The student photo must be an image file.',
            'photo.mimes' => 'The student photo must be a JPG, JPEG or PNG file.',
            'photo.max' => 'The student photo may not be larger than 2MB.',
            'instructions_accepted.required' => 'براہ کرم ضروری ہدایات پڑھ کر اتفاق کریں۔ You must read and accept the instructions before submitting.',
            'instructions_accepted.accepted' => 'براہ کرم ضروری ہدایات پڑھ کر اتفاق کریں۔ You must read and accept the instructions before submitting.',
            'madrassa_class_id.required' => 'Please select a class.',
            'madrassa_class_id.exists' => 'Please select a valid class for the chosen programme.',
            'madrassa_class_id.prohibited' => 'A madrassa class does not apply to the chosen programme.',
            'school_class_id.required' => 'Please select a school class.',
            'school_class_id.exists' => 'Please select a valid school class.',
            'school_class_id.prohibited' => 'A school class does not apply to the chosen programme.',
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
            'madrassa_class_id' => 'class',
            'school_class_id' => 'school class',
        ];
    }
}
