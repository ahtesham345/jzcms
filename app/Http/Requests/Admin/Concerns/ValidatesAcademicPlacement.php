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
        $sides = AcademicPlacement::sides($studentType);

        // The plain fields belong to a student type placed on one programme;
        // a combined type posts one prefixed set per side instead. Whichever
        // set does not apply is prohibited outright.
        $rules = [
            'department_id' => $spansBothTracks
                ? $this->unusedFieldRules()
                : $this->departmentRules($studentType, $soleSide),
            'academic_class_id' => $spansBothTracks
                ? $this->unusedFieldRules()
                : $this->classRules('department_id'),
            'section_id' => $spansBothTracks
                ? $this->unusedFieldRules()
                : $this->sectionRules('academic_class_id'),
        ];

        // Every side the mapping knows about, not only the two this used to
        // name. A side the chosen student type does not use is prohibited,
        // which is what stops a Hifz + School submission carrying a Computer
        // placement it has no business holding.
        foreach (array_keys(AcademicPlacement::SIDE_TRACKS) as $side) {
            $used = $spansBothTracks && in_array($side, $sides, true);

            $rules[$side.'_department_id'] = $used
                ? $this->departmentRules($studentType, $side)
                : $this->unusedFieldRules();
            $rules[$side.'_class_id'] = $used
                ? $this->classRules($side.'_department_id')
                : $this->unusedFieldRules();
            $rules[$side.'_section_id'] = $used
                ? $this->sectionRules($side.'_class_id')
                : $this->unusedFieldRules();
        }

        // The Computer semester. Optional on submission - a new Computer
        // student starts at the first stage of the course, which the
        // placement fills in - but a value that is sent has to be a real,
        // active semester of the configured course.
        $rules[AcademicPlacement::semesterField()] = $this->usesComputerSide($studentType)
            ? $this->computerSemesterRules()
            : $this->unusedFieldRules();

        return $rules;
    }

    /**
     * Determine whether this student type is placed in the Computer course.
     */
    private function usesComputerSide(?string $studentType): bool
    {
        return in_array(
            AcademicPlacement::SEMESTER_SIDE,
            AcademicPlacement::sides($studentType),
            true
        );
    }

    /**
     * Build the rules for the Computer semester.
     *
     * @return array<int, mixed>
     */
    private function computerSemesterRules(): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('computer_course_semesters', 'id')->where('status', true),
        ];
    }

    /**
     * Every field a placement may be posted under.
     *
     * @return array<int, string>
     */
    protected function placementFields(): array
    {
        $fields = ['department_id', 'academic_class_id', 'section_id'];

        foreach (array_keys(AcademicPlacement::SIDE_TRACKS) as $side) {
            $fields[] = $side.'_department_id';
            $fields[] = $side.'_class_id';
            $fields[] = $side.'_section_id';
        }

        $fields[] = AcademicPlacement::semesterField();

        return $fields;
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
        // The plain fields, used by a student type placed on one programme.
        $messages = [
            'department_id.exists' => 'The selected department does not match the chosen student type.',
            'department_id.prohibited' => 'This student type is placed on each of its programmes separately.',
            'academic_class_id.exists' => 'The selected class is inactive or does not belong to the selected department.',
            'academic_class_id.prohibited' => 'This student type is placed on each of its programmes separately.',
            'section_id.exists' => 'The selected section is inactive or does not belong to the selected class.',
            'section_id.prohibited' => 'This student type is placed on each of its programmes separately.',

            'computer_semester_id.exists' => 'The selected semester is inactive or is not part of the Computer course.',
            'computer_semester_id.prohibited' => 'This student type is not enrolled in the Computer course.',
        ];

        // One set per side, worded in that programme's own terms.
        $labels = [
            'madrassa' => 'madrassa',
            'school' => 'school',
            'computer' => 'Computer',
        ];

        foreach (array_keys(AcademicPlacement::SIDE_TRACKS) as $side) {
            $label = $labels[$side] ?? $side;

            $messages[$side.'_department_id.exists'] = "The selected {$label} department does not match the chosen student type.";
            $messages[$side.'_department_id.prohibited'] = "This student type does not have a {$label} placement.";
            $messages[$side.'_class_id.exists'] = "The selected {$label} class is inactive or does not belong to the {$label} department.";
            $messages[$side.'_class_id.prohibited'] = "This student type does not have a {$label} placement.";
            $messages[$side.'_section_id.exists'] = "The selected {$label} section is inactive or does not belong to the {$label} class.";
            $messages[$side.'_section_id.prohibited'] = "This student type does not have a {$label} placement.";
        }

        return $messages;
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
