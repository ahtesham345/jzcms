<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Support\AcademicPlacement;
use Illuminate\Validation\Rule;

/**
 * The placement rules the manual student forms are checked against.
 *
 * The dependent selects are a convenience for the admin and nothing more:
 * every relationship is re-derived here from the posted student type and
 * checked against the database, so a hand-crafted request cannot place a
 * School class under the Hifz department, hang a section off a class it does
 * not belong to, or give a Hifz + School student only half a placement.
 *
 * Shared by StoreStudentRequest and UpdateStudentRequest so creating and
 * editing a student enforce one set of rules rather than two.
 */
trait ValidatesAcademicPlacement
{
    /**
     * Get the placement rules for the posted student type.
     *
     * A single-track type is placed with department_id / academic_class_id /
     * section_id; a Hifz + School student with one madrassa_ and one school_
     * set. Whichever set does not apply is prohibited outright, the same way
     * the admission approval prohibits the section fields it does not need.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function placementRules(): array
    {
        $studentType = $this->input('student_type');

        // An unrecognised student type has no sides at all. It is rejected by
        // its own `in` rule, so nothing extra is asserted here: piling
        // prohibited/required errors onto every placement field would only
        // bury the one message that explains the problem.
        if (AcademicPlacement::sides($studentType) === []) {
            return array_fill_keys($this->placementFields(), ['nullable']);
        }

        $spansBothTracks = AcademicPlacement::spansBothTracks($studentType);
        $soleSide = AcademicPlacement::soleSide($studentType);

        return [
            'department_id' => $spansBothTracks
                ? $this->unusedFieldRules()
                : $this->departmentRules($studentType, $soleSide),
            'academic_class_id' => $spansBothTracks
                ? $this->unusedFieldRules()
                : $this->classRules('department_id'),
            'section_id' => $spansBothTracks
                ? $this->unusedFieldRules()
                : $this->sectionRules('academic_class_id'),

            'madrassa_department_id' => $spansBothTracks
                ? $this->departmentRules($studentType, 'madrassa')
                : $this->unusedFieldRules(),
            'madrassa_class_id' => $spansBothTracks
                ? $this->classRules('madrassa_department_id')
                : $this->unusedFieldRules(),
            'madrassa_section_id' => $spansBothTracks
                ? $this->sectionRules('madrassa_class_id')
                : $this->unusedFieldRules(),

            'school_department_id' => $spansBothTracks
                ? $this->departmentRules($studentType, 'school')
                : $this->unusedFieldRules(),
            'school_class_id' => $spansBothTracks
                ? $this->classRules('school_department_id')
                : $this->unusedFieldRules(),
            'school_section_id' => $spansBothTracks
                ? $this->sectionRules('school_class_id')
                : $this->unusedFieldRules(),
        ];
    }

    /**
     * Every field a placement may be posted under.
     *
     * @return array<int, string>
     */
    protected function placementFields(): array
    {
        return [
            'department_id', 'academic_class_id', 'section_id',
            'madrassa_department_id', 'madrassa_class_id', 'madrassa_section_id',
            'school_department_id', 'school_class_id', 'school_section_id',
        ];
    }

    /**
     * The rules for a field this student type does not use.
     *
     * @return array<int, mixed>
     */
    private function unusedFieldRules(): array
    {
        return ['nullable', 'prohibited'];
    }

    /**
     * Build the rules for one side's department.
     *
     * The department is not free: it must be the active department the chosen
     * student type maps that side to, which is the same mapping the admission
     * form places applicants with. A School department posted for the
     * madrassa side of a Hifz + School student fails here.
     *
     * @return array<int, mixed>
     */
    private function departmentRules(?string $studentType, ?string $side): array
    {
        $departmentName = $side === null
            ? null
            : AcademicPlacement::departmentName($studentType, $side);

        if ($departmentName === null) {
            return $this->unusedFieldRules();
        }

        return [
            'required',
            'integer',
            Rule::exists('departments', 'id')
                ->where('name', $departmentName)
                ->where('status', true),
        ];
    }

    /**
     * Build the rules for a class, scoped to the department posted with it.
     *
     * @return array<int, mixed>
     */
    private function classRules(string $departmentField): array
    {
        return [
            'required',
            'integer',
            Rule::exists('academic_classes', 'id')
                ->where('status', true)
                ->where('department_id', $this->input($departmentField)),
        ];
    }

    /**
     * Build the rules for a section, scoped to the class posted with it.
     *
     * Optional throughout, matching the admission approval and the enrollment
     * form: a class may be run without sections. A supplied one must be
     * active and belong to that class.
     *
     * @return array<int, mixed>
     */
    private function sectionRules(string $classField): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('sections', 'id')
                ->where('status', true)
                ->where('academic_class_id', $this->input($classField)),
        ];
    }

    /**
     * The validation messages for the placement fields.
     *
     * @return array<string, string>
     */
    protected function placementMessages(): array
    {
        return [
            'department_id.exists' => 'The selected department does not match the chosen student type.',
            'department_id.prohibited' => 'A Hifz + School student needs a madrassa placement and a school placement.',
            'academic_class_id.exists' => 'The selected class is inactive or does not belong to the selected department.',
            'academic_class_id.prohibited' => 'A Hifz + School student needs a madrassa placement and a school placement.',
            'section_id.exists' => 'The selected section is inactive or does not belong to the selected class.',
            'section_id.prohibited' => 'A Hifz + School student needs a madrassa section and a school section.',

            'madrassa_department_id.exists' => 'The selected madrassa department does not match the chosen student type.',
            'madrassa_department_id.prohibited' => 'This student type does not have a madrassa placement.',
            'madrassa_class_id.exists' => 'The selected madrassa class is inactive or does not belong to the madrassa department.',
            'madrassa_class_id.prohibited' => 'This student type does not have a madrassa placement.',
            'madrassa_section_id.exists' => 'The selected madrassa section is inactive or does not belong to the madrassa class.',
            'madrassa_section_id.prohibited' => 'This student type does not have a madrassa placement.',

            'school_department_id.exists' => 'The selected school department does not match the chosen student type.',
            'school_department_id.prohibited' => 'This student type does not have a school placement.',
            'school_class_id.exists' => 'The selected school class is inactive or does not belong to the school department.',
            'school_class_id.prohibited' => 'This student type does not have a school placement.',
            'school_section_id.exists' => 'The selected school section is inactive or does not belong to the school class.',
            'school_section_id.prohibited' => 'This student type does not have a school placement.',
        ];
    }

    /**
     * The display names for the placement fields.
     *
     * @return array<string, string>
     */
    protected function placementAttributes(): array
    {
        return [
            'department_id' => 'department',
            'academic_class_id' => 'class',
            'section_id' => 'section',
            'madrassa_department_id' => 'madrassa department',
            'madrassa_class_id' => 'madrassa class',
            'madrassa_section_id' => 'madrassa section',
            'school_department_id' => 'school department',
            'school_class_id' => 'school class',
            'school_section_id' => 'school section',
        ];
    }
}
