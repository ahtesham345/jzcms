<?php

namespace App\Http\Requests\Admin;

use App\Models\AdmissionApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveAdmissionApplicationRequest extends FormRequest
{
    /**
     * The application being approved.
     */
    private ?AdmissionApplication $application = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the application being approved.
     */
    public function application(): ?AdmissionApplication
    {
        return $this->application ??= AdmissionApplication::find($this->route('admission'));
    }

    /**
     * Determine whether the application carries its own class selection.
     */
    private function hasOwnClasses(): bool
    {
        $application = $this->application();

        return $application !== null
            && ($application->madrassa_class_id !== null || $application->school_class_id !== null);
    }

    /**
     * Determine whether the application spans both tracks.
     */
    private function needsTwoSections(): bool
    {
        $application = $this->application();

        return $application !== null
            && $application->madrassa_class_id !== null
            && $application->school_class_id !== null;
    }

    /**
     * Get the class a single-track application is being admitted into.
     *
     * Applications carrying no class of their own are placed using the class
     * chosen in the modal, so that is what the section must belong to.
     */
    private function singleTrackClassId(): ?int
    {
        $application = $this->application();

        if ($application === null) {
            return null;
        }

        return $application->madrassa_class_id
            ?? $application->school_class_id
            ?? ($this->input('academic_class_id') ? (int) $this->input('academic_class_id') : null);
    }

    /**
     * Build the rules for a section, scoped to the class it belongs to.
     *
     * Sections are optional: null always passes. A supplied section must
     * exist, be active, and belong to that class.
     *
     * @return array<int, mixed>
     */
    private function sectionRules(?int $academicClassId): array
    {
        if ($academicClassId === null) {
            // No class to scope against, so only accept an empty value.
            return ['nullable', 'prohibited'];
        }

        return [
            'nullable',
            'integer',
            Rule::exists('sections', 'id')
                ->where('academic_class_id', $academicClassId)
                ->where('status', true),
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Department and class are taken from the admission application, so they
     * are rejected outright when the application already carries them: the
     * admin cannot override the parent's choice during approval. Only
     * applications with no class of their own (admin created, or predating
     * the class selection) still ask for a placement.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $placementRules = $this->hasOwnClasses()
            ? ['nullable', 'prohibited']
            : ['required'];

        return [
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],

            'department_id' => $this->hasOwnClasses()
                ? $placementRules
                : [...$placementRules, 'exists:departments,id'],
            'academic_class_id' => $this->hasOwnClasses()
                ? $placementRules
                : [...$placementRules, 'exists:academic_classes,id'],

            // Sections are optional, but a chosen one must belong to the class
            // it is being assigned against. One section per track when the
            // application spans both.
            'section_id' => $this->needsTwoSections()
                ? ['nullable', 'prohibited']
                : $this->sectionRules($this->singleTrackClassId()),
            'madrassa_section_id' => $this->needsTwoSections()
                ? $this->sectionRules($this->application()?->madrassa_class_id)
                : ['nullable', 'prohibited'],
            'school_section_id' => $this->needsTwoSections()
                ? $this->sectionRules($this->application()?->school_class_id)
                : ['nullable', 'prohibited'],

            'admission_date' => ['required', 'date'],
            'emergency_contact' => ['required', 'string', 'max:255'],
            'resident_type' => ['required', 'in:Local Resident,Outside Resident'],
            'roll_number' => ['nullable', 'string', 'max:255'],
            'medical_information' => ['nullable', 'string'],
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
            'academic_session_id' => 'academic session',
            'department_id' => 'department',
            'academic_class_id' => 'class',
            'section_id' => 'section',
            'madrassa_section_id' => 'madrassa section',
            'school_section_id' => 'school section',
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
            'department_id.prohibited' => 'The department comes from the admission application and cannot be changed during approval.',
            'academic_class_id.prohibited' => 'The class comes from the admission application and cannot be changed during approval.',
            'section_id.prohibited' => 'This application needs a madrassa section and a school section.',
            'section_id.exists' => 'The selected section does not belong to this class.',
            'madrassa_section_id.prohibited' => 'This application does not have a madrassa track.',
            'madrassa_section_id.exists' => 'The selected madrassa section does not belong to the madrassa class.',
            'school_section_id.prohibited' => 'This application does not have a school track.',
            'school_section_id.exists' => 'The selected school section does not belong to the school class.',
        ];
    }
}
