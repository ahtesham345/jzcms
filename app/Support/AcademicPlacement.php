<?php

namespace App\Support;

use App\Models\AcademicClass;
use App\Models\AdmissionApplication;
use App\Models\Department;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;

/**
 * The academic placement rules shared by Admission Management and Student
 * Management.
 *
 * There is one definition of what a student type may be placed into, and it
 * is the one Admission Management already runs on:
 * AdmissionApplication::STUDENT_TYPE_DEPARTMENTS. Nothing here restates it,
 * hardcodes a department list, or introduces a second academic structure -
 * the departments, classes and sections all come from the existing Master
 * Data tables through that mapping.
 *
 * A "side" is one half of that mapping: madrassa or school. A Hifz + School
 * student uses both, every other student type uses exactly one, and each
 * side records its enrollment against the matching academic track.
 */
class AcademicPlacement
{
    /**
     * The enrollment track each side records against.
     *
     * @var array<string, string>
     */
    public const SIDE_TRACKS = [
        'madrassa' => 'Madrassa',
        'school' => 'School',
    ];

    /**
     * Get the request field each part of a side's placement is posted under.
     *
     * A single-track student type posts the plain field names the students
     * table itself uses, so nothing about the existing form changes. A
     * Hifz + School student posts one prefixed set per side, matching the
     * madrassa_/school_ convention the admission approval modal already uses.
     *
     * @return array<string, string> column => request field name
     */
    public static function fields(?string $studentType, string $side): array
    {
        if (! self::spansBothTracks($studentType)) {
            return [
                'department_id' => 'department_id',
                'academic_class_id' => 'academic_class_id',
                'section_id' => 'section_id',
            ];
        }

        return [
            'department_id' => $side.'_department_id',
            // Named to match the admission application's own madrassa_class_id
            // and school_class_id columns, so one flow reads like the other.
            'academic_class_id' => $side.'_class_id',
            'section_id' => $side.'_section_id',
        ];
    }

    /**
     * Get the department name each side of a student type places into.
     *
     * @return array<string, string> side => department name
     */
    public static function departmentNames(?string $studentType): array
    {
        return AdmissionApplication::departmentsForStudentType($studentType);
    }

    /**
     * Get the department name one side of a student type places into.
     */
    public static function departmentName(?string $studentType, string $side): ?string
    {
        return AdmissionApplication::departmentForSide($studentType, $side);
    }

    /**
     * Get the sides a student type is placed on.
     *
     * @return array<int, string>
     */
    public static function sides(?string $studentType): array
    {
        return array_keys(self::departmentNames($studentType));
    }

    /**
     * Determine whether a student type is placed on both tracks.
     */
    public static function spansBothTracks(?string $studentType): bool
    {
        return count(self::departmentNames($studentType)) > 1;
    }

    /**
     * Get the only side a student type uses, or null when it uses two.
     *
     * Null is also the answer for an unrecognised student type, which has no
     * sides at all; validation rejects that separately.
     */
    public static function soleSide(?string $studentType): ?string
    {
        $sides = self::sides($studentType);

        return count($sides) === 1 ? $sides[0] : null;
    }

    /**
     * Get the academic track a side records its enrollment under.
     */
    public static function track(string $side): string
    {
        return self::SIDE_TRACKS[$side];
    }

    /**
     * Get the active department one side of a student type places into.
     *
     * Read from the departments table by name, never hardcoded, so a school
     * that renames or deactivates a department changes both flows at once.
     */
    public static function departmentId(?string $studentType, string $side): ?int
    {
        $name = self::departmentName($studentType, $side);

        if ($name === null) {
            return null;
        }

        $id = Department::where('name', $name)->where('status', true)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Get the department each student type places into, per side, as ids.
     *
     * Handed to the browser so the dependent selects can resolve a student
     * type to its department without a request. A convenience only: the
     * server checks the posted department against this same mapping.
     *
     * @return array<string, array<string, int>>
     */
    public static function departmentIdsByStudentType(): array
    {
        $departmentIds = Department::where('status', true)
            ->pluck('id', 'name')
            ->map(fn ($id) => (int) $id)
            ->all();

        $map = [];

        foreach (AdmissionApplication::STUDENT_TYPE_DEPARTMENTS as $studentType => $sides) {
            foreach ($sides as $side => $departmentName) {
                if (isset($departmentIds[$departmentName])) {
                    $map[$studentType][$side] = $departmentIds[$departmentName];
                }
            }
        }

        return $map;
    }

    /**
     * Get the options the placement selects are built from.
     *
     * The classes and sections are keyed by their parent, the same shape the
     * enrollment form and the public admission form already use, so the
     * Alpine narrowing is identical in all three.
     *
     * @return array<string, mixed>
     */
    public static function formOptions(): array
    {
        return [
            'departmentNamesById' => Department::where('status', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
            'departmentIdsByStudentType' => self::departmentIdsByStudentType(),
            'classesByDepartment' => AcademicClass::where('status', true)
                ->orderBy('name')
                ->get(['id', 'name', 'department_id'])
                ->groupBy('department_id')
                ->map(fn ($classes) => $classes->map->only(['id', 'name'])->values()),
            'sectionsByClass' => Section::where('status', true)
                ->orderBy('name')
                ->get(['id', 'name', 'academic_class_id'])
                ->groupBy('academic_class_id')
                ->map(fn ($sections) => $sections->map->only(['id', 'name'])->values()),
        ];
    }

    /**
     * Read the placement of every side a student type uses out of validated data.
     *
     * Validation has already confirmed each department belongs to the side,
     * each class to its department and each section to its class, so nothing
     * is re-checked here: this only reshapes what was accepted.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{department_id: int, academic_class_id: int, section_id: int|null}>
     */
    public static function fromValidated(array $data): array
    {
        $studentType = $data['student_type'] ?? null;
        $placements = [];

        foreach (self::sides($studentType) as $side) {
            $fields = self::fields($studentType, $side);

            $section = $data[$fields['section_id']] ?? null;

            $placements[$side] = [
                'department_id' => (int) $data[$fields['department_id']],
                'academic_class_id' => (int) $data[$fields['academic_class_id']],
                'section_id' => ($section === null || $section === '') ? null : (int) $section,
            ];
        }

        return $placements;
    }

    /**
     * Get the placement recorded on the student row itself.
     *
     * The students table holds one placement, so a dual-track student is
     * recorded against the madrassa one and the school side lives on its own
     * enrollment. That is the rule the admission approval already follows.
     *
     * @param  array<string, array<string, mixed>>  $placements
     * @return array{department_id: int, academic_class_id: int, section_id: int|null}|null
     */
    public static function primary(array $placements): ?array
    {
        return $placements['madrassa'] ?? $placements['school'] ?? null;
    }

    /**
     * Record one active enrollment for a student.
     *
     * The single writer of an enrollment created alongside a student, shared
     * by the admission approval and by manual student creation, so the two
     * produce the same rows.
     */
    public static function recordEnrollment(
        Student $student,
        string $track,
        int $departmentId,
        int $academicClassId,
        ?int $sectionId,
        string $startDate,
        int $academicSessionId
    ): StudentAcademicEnrollment {
        return StudentAcademicEnrollment::create([
            'student_id' => $student->id,
            'academic_session_id' => $academicSessionId,
            'academic_track' => $track,
            'department_id' => $departmentId,
            'academic_class_id' => $academicClassId,
            'section_id' => $sectionId,
            'start_date' => $startDate,
            'status' => 'Active',
        ]);
    }
}
