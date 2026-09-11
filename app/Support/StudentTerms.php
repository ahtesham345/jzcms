<?php

namespace App\Support;

use App\Models\AdmissionApplication;
use App\Models\Department;
use App\Models\DepartmentTerm;
use App\Models\Student;

/**
 * Which instructions a guardian is shown, and where they come from.
 *
 * One resolver, so the public admission form, the admin's view of an
 * application and the student's profile cannot disagree about what a
 * particular student agreed to. Blade asks this for a list and renders it;
 * none of the combining happens in a view.
 *
 * The rule, confirmed with the Imam:
 *
 *   - a student placed in one department is shown that department's
 *     instructions;
 *   - a student placed in two - Hifz + School, Dars-e-Nizami + Computer -
 *     is shown both sets, in placement order, with lines that appear in
 *     both shown once.
 *
 * There is no set of instructions for a combined student type, and there is
 * no combined department. The combination is the student's, and it is
 * resolved by reading the two real departments they are placed in.
 *
 * The heading and the agreement sentence are not here. They are the
 * institution's own and identical for every department, so they stay in
 * config/admission_instructions.php.
 */
class StudentTerms
{
    /**
     * Get the instructions for one department.
     *
     * The configured set when there is one, and the institution's defaults
     * when there is not - a department nobody has written instructions for
     * yet still shows the instructions every student has always been shown,
     * rather than an empty section.
     *
     * @return array<int, string>
     */
    public static function forDepartment(?int $departmentId): array
    {
        if ($departmentId === null) {
            return self::defaults();
        }

        $configured = DepartmentTerm::query()
            ->where('department_id', $departmentId)
            ->where('status', true)
            ->first();

        $items = $configured?->itemLines() ?? [];

        return $items === [] ? self::defaults() : $items;
    }

    /**
     * Get the instructions for a set of departments, combined.
     *
     * The order the departments are given in is the order the instructions
     * come out in, and a line that appears in more than one department is
     * shown once, where it first appeared. That is what keeps a
     * Hifz + School guardian from reading the same rule twice while the two
     * departments still share most of their instructions.
     *
     * @param  array<int, int|null>  $departmentIds
     * @return array<int, string>
     */
    public static function forDepartments(array $departmentIds): array
    {
        $ids = array_values(array_filter(
            array_unique($departmentIds),
            fn ($id) => $id !== null
        ));

        if ($ids === []) {
            return self::defaults();
        }

        $combined = [];

        foreach ($ids as $departmentId) {
            foreach (self::forDepartment((int) $departmentId) as $line) {
                // Keyed by the line itself, so the second department's copy
                // of a shared instruction never reaches the list. First
                // occurrence wins, which preserves placement order.
                $combined[$line] ??= true;
            }
        }

        return array_keys($combined);
    }

    /**
     * Get the instructions a student type's placements call for.
     *
     * The departments come from the existing student type mapping, in the
     * order it lists its sides - madrassa first, then school or computer -
     * so the same architecture that decides where a student is placed also
     * decides which instructions they are shown. Nothing about the
     * combination is restated here.
     *
     * @return array<int, string>
     */
    public static function forStudentType(?string $studentType): array
    {
        $departmentIds = [];

        foreach (AcademicPlacement::sides($studentType) as $side) {
            $departmentIds[] = AcademicPlacement::departmentId($studentType, $side);
        }

        return self::forDepartments($departmentIds);
    }

    /**
     * Get the instructions for a student, from where they are actually placed.
     *
     * Their enrollments rather than their student type: a student's real
     * placements are what they are studying, and they are already read in
     * placement order. The type is the fallback for a student who has no
     * active placement to read - one recorded before the enrollments
     * existed, or one whose placements have all been closed.
     *
     * @return array<int, string>
     */
    public static function forStudent(Student $student): array
    {
        $departmentIds = $student->activeAcademicEnrollments
            ->pluck('department_id')
            ->all();

        if ($departmentIds === []) {
            return self::forStudentType($student->student_type);
        }

        return self::forDepartments($departmentIds);
    }

    /**
     * Get the instructions an application's guardian is agreeing to.
     *
     * An application has no enrollments yet - they are written when it is
     * approved - so the student type is what decides, through the same
     * mapping the approval will place them with.
     *
     * @return array<int, string>
     */
    public static function forApplication(AdmissionApplication $application): array
    {
        return self::forStudentType($application->student_type);
    }

    /**
     * Get the resolved instructions for every student type.
     *
     * Built for the public admission form, where the applicant chooses their
     * student type on the same page the instructions are shown on. Every
     * combination is resolved here, on the server, so the browser only has
     * to pick a list by the type that is selected - the mapping and the
     * de-duplication are not repeated in JavaScript, where they could drift.
     *
     * @return array<string, array<int, string>>
     */
    public static function byStudentType(): array
    {
        $resolved = [];

        foreach (AdmissionApplication::STUDENT_TYPES as $studentType) {
            $resolved[$studentType] = self::forStudentType($studentType);
        }

        return $resolved;
    }

    /**
     * Get the instructions currently configured for every real department.
     *
     * What the settings page edits. Keyed by department id and covering only
     * the departments the student type mapping actually places students in,
     * so a department created by mistake - a combined one, say - is never
     * offered a set of instructions of its own.
     *
     * @return array<int, array{department: Department, items: array<int, string>, configured: bool}>
     */
    public static function editableByDepartment(): array
    {
        $names = AcademicPlacement::requiredDepartmentNames();

        $departments = Department::query()
            ->whereIn('name', $names)
            ->where('status', true)
            ->get()
            // The order the mapping names them in, not the table's.
            ->sortBy(fn (Department $department) => array_search($department->name, $names, true))
            ->values();

        $configured = DepartmentTerm::query()
            ->whereIn('department_id', $departments->pluck('id'))
            ->get()
            ->keyBy('department_id');

        $editable = [];

        foreach ($departments as $department) {
            $term = $configured->get($department->id);
            $items = $term?->itemLines() ?? [];

            $editable[$department->id] = [
                'department' => $department,
                // The defaults are shown for a department nobody has written
                // instructions for, so saving the form as it stands records
                // what is already on display rather than emptying it.
                'items' => $items === [] ? self::defaults() : $items,
                'configured' => $items !== [],
            ];
        }

        return $editable;
    }

    /**
     * The institution's default instructions.
     *
     * The wording the system shipped with, still in config. It is the
     * fallback now rather than the live source: a department with its own
     * instructions is shown those, and this is what anything unconfigured
     * falls back to so no guardian is ever shown an empty list.
     *
     * @return array<int, string>
     */
    public static function defaults(): array
    {
        return array_values(config('admission_instructions.items', []));
    }

    /**
     * The heading above the instructions.
     *
     * Global, not per department: it names the section rather than the
     * programme.
     */
    public static function heading(): string
    {
        return (string) config('admission_instructions.heading', '');
    }

    /**
     * The sentence a guardian ticks to agree.
     *
     * Global for the same reason: it is a statement of consent, identical
     * whatever the student is studying.
     */
    public static function agreement(): string
    {
        return (string) config('admission_instructions.agreement', '');
    }
}
