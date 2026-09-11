<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AdmissionDepartmentClassSeeder;
use Database\Seeders\ComputerCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the placement filters on the Student Management listing.
 *
 * The rule being described: a student is found by where they currently
 * are, and where they are is their active enrollments - not the single
 * placement the students table happens to carry. A Hifz + School student
 * holds one enrollment per track and must be reachable through either.
 *
 * The second rule is that department, class and section are read together.
 * They describe one placement, so they are matched against one enrollment;
 * a student who is in Hifz on one track and in a school class on the other
 * has never been in "Hifz, Class 7" and must not answer to it.
 *
 * Computer is a department in its own right, so a Dars-e-Nizami + Computer
 * student holds two placements and must be findable under either - the same
 * rule, applied to the second combined student type.
 */
class StudentListingFilterTest extends TestCase
{
    use RefreshDatabase;

    private AcademicSession $session;

    private Department $hifz;

    private Department $school;

    private Department $darsENizami;

    private AcademicClass $nazra;

    private AcademicClass $primary;

    private AcademicClass $salEAwwal;

    private Department $computer;

    private AcademicClass $courseClass;

    private Section $nazraA;

    private Section $primaryA;

    private Section $salEAwwalA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->seed(AdmissionDepartmentClassSeeder::class);
        $this->seed(ComputerCourseSeeder::class);

        $this->session = AcademicSession::create([
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
            'status' => true,
        ]);

        $this->hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->school = Department::where('name', 'School')->firstOrFail();
        $this->darsENizami = Department::where('name', 'Dars-e-Nizami')->firstOrFail();
        $this->computer = Department::where('name', 'Computer')->firstOrFail();

        $this->nazra = $this->class($this->hifz, 'Nazra');
        $this->primary = $this->class($this->school, 'Primary Section');
        $this->salEAwwal = AcademicClass::where('department_id', $this->darsENizami->id)
            ->orderBy('id')->firstOrFail();

        $this->nazraA = $this->section('Nazra-A', $this->nazra);
        $this->primaryA = $this->section('Primary-A', $this->primary);
        $this->salEAwwalA = $this->section('Dars-A', $this->salEAwwal);
        $this->courseClass = AcademicClass::where('department_id', $this->computer->id)->firstOrFail();
    }

    /* ---------------------------------------------------------------- */
    /* Fixtures */
    /* ---------------------------------------------------------------- */

    private function class(Department $department, string $name): AcademicClass
    {
        return AcademicClass::where('department_id', $department->id)
            ->where('name', $name)
            ->firstOrFail();
    }

    private function section(string $name, AcademicClass $class): Section
    {
        return Section::create([
            'name' => $name,
            'code' => strtoupper(str_replace('-', '', $name)),
            'academic_class_id' => $class->id,
            'status' => true,
        ]);
    }

    /**
     * A student with their placement rows, written directly.
     *
     * The creation flow has its own suite; this one is about reading the
     * listing back, so the rows are set up rather than posted.
     *
     * @param  array<int, array{track: string, department: Department, class: AcademicClass, section?: Section|null, status?: string}>  $enrollments
     */
    private function student(string $name, string $studentType, array $enrollments): Student
    {
        static $sequence = 0;
        $sequence++;

        $primary = $enrollments[0];

        $student = Student::create([
            'registration_number' => 'STD-2026-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'full_name' => $name,
            'father_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03009998887',
            'admission_date' => '2026-04-01',
            'academic_session_id' => $this->session->id,
            'student_status' => 'Active',
            'student_type' => $studentType,
            'resident_type' => 'Local Resident',
            // The students table holds one placement, madrassa first, which
            // is exactly the limitation these filters must not inherit.
            'department_id' => $primary['department']->id,
            'academic_class_id' => $primary['class']->id,
            'section_id' => $primary['section']->id ?? null,
        ]);

        foreach ($enrollments as $enrollment) {
            $student->academicEnrollments()->create([
                'academic_session_id' => $this->session->id,
                'academic_track' => $enrollment['track'],
                'department_id' => $enrollment['department']->id,
                'academic_class_id' => $enrollment['class']->id,
                'section_id' => $enrollment['section']->id ?? null,
                'start_date' => '2026-04-01',
                'status' => $enrollment['status'] ?? 'Active',
            ]);
        }

        return $student;
    }

    private function hifzStudent(): Student
    {
        return $this->student('Bilal Hifz', 'Hifz', [
            ['track' => 'Madrassa', 'department' => $this->hifz, 'class' => $this->nazra, 'section' => $this->nazraA],
        ]);
    }

    private function schoolStudent(): Student
    {
        return $this->student('Kamran School', 'School', [
            ['track' => 'School', 'department' => $this->school, 'class' => $this->primary, 'section' => $this->primaryA],
        ]);
    }

    private function darsStudent(): Student
    {
        return $this->student('Usman Dars', 'Dars-e-Nizami', [
            ['track' => 'Madrassa', 'department' => $this->darsENizami, 'class' => $this->salEAwwal, 'section' => $this->salEAwwalA],
        ]);
    }

    private function darsWithComputerStudent(): Student
    {
        // Computer is a department of its own, so this student is placed
        // twice - the madrassa programme and the Computer course - the same
        // way a Hifz + School student is.
        return $this->student('Zaid Computer', 'Dars-e-Nizami + Computer', [
            ['track' => 'Madrassa', 'department' => $this->darsENizami, 'class' => $this->salEAwwal, 'section' => $this->salEAwwalA],
            ['track' => 'Computer', 'department' => $this->computer, 'class' => $this->courseClass, 'section' => null],
        ]);
    }

    private function dualStudent(): Student
    {
        return $this->student('Ahmed Dual', 'Hifz + School', [
            ['track' => 'Madrassa', 'department' => $this->hifz, 'class' => $this->nazra, 'section' => $this->nazraA],
            ['track' => 'School', 'department' => $this->school, 'class' => $this->primary, 'section' => $this->primaryA],
        ]);
    }

    /**
     * The names the listing came back with.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    private function listed(array $filters): array
    {
        $response = $this->get(route('students.index', $filters))->assertOk();

        return $response->viewData('students')->pluck('full_name')->all();
    }

    /* ---------------------------------------------------------------- */
    /* Department */
    /* ---------------------------------------------------------------- */

    public function test_a_hifz_student_is_found_under_the_hifz_department(): void
    {
        $this->hifzStudent();
        $this->schoolStudent();

        $this->assertSame(['Bilal Hifz'], $this->listed(['department_id' => $this->hifz->id]));
    }

    public function test_a_school_student_is_found_under_the_school_department(): void
    {
        $this->hifzStudent();
        $this->schoolStudent();

        $this->assertSame(['Kamran School'], $this->listed(['department_id' => $this->school->id]));
    }

    public function test_a_dars_e_nizami_student_is_found_under_that_department(): void
    {
        $this->darsStudent();
        $this->hifzStudent();

        $this->assertSame(['Usman Dars'], $this->listed(['department_id' => $this->darsENizami->id]));
    }

    public function test_a_dual_student_is_found_under_the_hifz_department(): void
    {
        $this->dualStudent();

        $this->assertSame(['Ahmed Dual'], $this->listed(['department_id' => $this->hifz->id]));
    }

    public function test_a_dual_student_is_found_under_the_school_department(): void
    {
        $student = $this->dualStudent();

        // The bug this replaces: the student row carries the madrassa
        // placement, so filtering the row by School returned nothing.
        $this->assertSame($this->hifz->id, $student->department_id);

        $this->assertSame(['Ahmed Dual'], $this->listed(['department_id' => $this->school->id]));
    }

    public function test_a_computer_student_is_found_under_dars_e_nizami(): void
    {
        $this->darsWithComputerStudent();

        $this->assertSame(['Zaid Computer'], $this->listed(['department_id' => $this->darsENizami->id]));
    }

    public function test_a_computer_student_is_found_under_the_computer_department(): void
    {
        $student = $this->darsWithComputerStudent();
        $this->darsStudent();

        // The student row carries the madrassa placement, so this can only
        // be answered by reading the enrollments.
        $this->assertSame($this->darsENizami->id, $student->department_id);

        $this->assertSame(['Zaid Computer'], $this->listed(['department_id' => $this->computer->id]));
    }

    public function test_a_dars_e_nizami_only_student_is_not_found_under_computer(): void
    {
        $this->darsStudent();

        $this->assertSame([], $this->listed(['department_id' => $this->computer->id]));
    }

    /* ---------------------------------------------------------------- */
    /* Class and section */
    /* ---------------------------------------------------------------- */

    public function test_a_dual_student_is_found_by_their_hifz_class(): void
    {
        $this->dualStudent();

        $this->assertSame(['Ahmed Dual'], $this->listed(['academic_class_id' => $this->nazra->id]));
    }

    public function test_a_dual_student_is_found_by_their_school_class(): void
    {
        $this->dualStudent();

        $this->assertSame(['Ahmed Dual'], $this->listed(['academic_class_id' => $this->primary->id]));
    }

    public function test_a_computer_student_is_found_by_their_dars_e_nizami_class(): void
    {
        $this->darsWithComputerStudent();

        $this->assertSame(['Zaid Computer'], $this->listed(['academic_class_id' => $this->salEAwwal->id]));
    }

    public function test_a_dual_student_is_found_by_their_hifz_section(): void
    {
        $this->dualStudent();

        $this->assertSame(['Ahmed Dual'], $this->listed(['section_id' => $this->nazraA->id]));
    }

    public function test_a_dual_student_is_found_by_their_school_section(): void
    {
        $student = $this->dualStudent();

        $this->assertSame($this->nazraA->id, $student->section_id);

        $this->assertSame(['Ahmed Dual'], $this->listed(['section_id' => $this->primaryA->id]));
    }

    /* ---------------------------------------------------------------- */
    /* One enrollment has to satisfy the whole placement */
    /* ---------------------------------------------------------------- */

    public function test_department_and_class_must_describe_the_same_enrollment(): void
    {
        $this->dualStudent();

        // Each half on its own finds the student.
        $this->assertSame(['Ahmed Dual'], $this->listed(['department_id' => $this->hifz->id]));
        $this->assertSame(['Ahmed Dual'], $this->listed(['academic_class_id' => $this->primary->id]));

        // Together they describe a placement nobody holds: the Hifz
        // enrollment is not in the school's Primary Section.
        $this->assertSame([], $this->listed([
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->primary->id,
        ]));
    }

    public function test_department_and_section_must_describe_the_same_enrollment(): void
    {
        $this->dualStudent();

        $this->assertSame([], $this->listed([
            'department_id' => $this->hifz->id,
            'section_id' => $this->primaryA->id,
        ]));

        $this->assertSame(['Ahmed Dual'], $this->listed([
            'department_id' => $this->school->id,
            'section_id' => $this->primaryA->id,
        ]));
    }

    public function test_all_three_together_must_describe_the_same_enrollment(): void
    {
        $this->dualStudent();

        // The madrassa placement, exactly as recorded.
        $this->assertSame(['Ahmed Dual'], $this->listed([
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->nazraA->id,
        ]));

        // The school placement, exactly as recorded.
        $this->assertSame(['Ahmed Dual'], $this->listed([
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->primaryA->id,
        ]));

        // One field from each: no such placement exists.
        $this->assertSame([], $this->listed([
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->nazraA->id,
        ]));
    }

    /* ---------------------------------------------------------------- */
    /* Only current placements answer */
    /* ---------------------------------------------------------------- */

    public function test_a_completed_placement_does_not_answer_a_current_filter(): void
    {
        $this->student('Rehan Moved', 'Hifz', [
            ['track' => 'Madrassa', 'department' => $this->hifz, 'class' => $this->nazra, 'section' => $this->nazraA],
            // Where they used to be. A finished placement is history, not
            // an answer to "who is in the school now".
            ['track' => 'School', 'department' => $this->school, 'class' => $this->primary, 'section' => $this->primaryA, 'status' => 'Completed'],
        ]);

        $this->assertSame(['Rehan Moved'], $this->listed(['department_id' => $this->hifz->id]));
        $this->assertSame([], $this->listed(['department_id' => $this->school->id]));
        $this->assertSame([], $this->listed(['academic_class_id' => $this->primary->id]));
        $this->assertSame([], $this->listed(['section_id' => $this->primaryA->id]));
    }

    /* ---------------------------------------------------------------- */
    /* Student type stays a student-level filter */
    /* ---------------------------------------------------------------- */

    public function test_the_student_type_filter_finds_a_dual_student(): void
    {
        $this->dualStudent();
        $this->hifzStudent();

        $this->assertSame(['Ahmed Dual'], $this->listed(['student_type' => 'Hifz + School']));
    }

    public function test_the_student_type_filter_finds_a_computer_student(): void
    {
        $this->darsWithComputerStudent();
        $this->darsStudent();

        $this->assertSame(['Zaid Computer'], $this->listed(['student_type' => 'Dars-e-Nizami + Computer']));
    }

    public function test_a_computer_student_needs_no_combined_department(): void
    {
        $this->darsWithComputerStudent();

        // Computer is a department; "Dars-e-Nizami + Computer" is not. The
        // student is found through each of the two real departments they
        // are placed in, and through their type.
        $this->assertNotNull(Department::where('name', 'Computer')->first());
        $this->assertNull(Department::where('name', 'Dars-e-Nizami + Computer')->first());

        $this->assertSame(['Zaid Computer'], $this->listed(['department_id' => $this->darsENizami->id]));
        $this->assertSame(['Zaid Computer'], $this->listed(['department_id' => $this->computer->id]));
        $this->assertSame(['Zaid Computer'], $this->listed(['student_type' => 'Dars-e-Nizami + Computer']));
    }

    /* ---------------------------------------------------------------- */
    /* The options the dependent dropdowns are built from */
    /* ---------------------------------------------------------------- */

    /**
     * The class options the page hands the browser, for one department.
     *
     * The narrowing itself is Alpine's, driven entirely by these maps, so
     * what is asserted here is the data it narrows from: a class can only
     * be offered under a department the map files it under.
     *
     * @return array<int, string>
     */
    private function classOptionsFor(?Department $department): array
    {
        $map = $this->get(route('students.index'))->assertOk()->viewData('classesByDepartment');

        $groups = $department === null
            ? $map->values()->flatten(1)
            : ($map[$department->id] ?? collect());

        return collect($groups)->pluck('name')->sort()->values()->all();
    }

    public function test_every_class_is_offered_when_no_department_is_chosen(): void
    {
        $names = $this->classOptionsFor(null);

        $this->assertContains('Nazra', $names);
        $this->assertContains('Primary Section', $names);
        $this->assertContains($this->salEAwwal->name, $names);
        $this->assertSame(AcademicClass::where('status', true)->count(), count($names));
    }

    public function test_only_hifz_classes_are_offered_under_hifz(): void
    {
        $names = $this->classOptionsFor($this->hifz);

        $this->assertContains('Nazra', $names);
        $this->assertNotContains('Primary Section', $names);
        $this->assertNotContains($this->salEAwwal->name, $names);
    }

    public function test_only_school_classes_are_offered_under_school(): void
    {
        $names = $this->classOptionsFor($this->school);

        $this->assertContains('Primary Section', $names);
        $this->assertNotContains('Nazra', $names);
        $this->assertNotContains($this->salEAwwal->name, $names);
    }

    public function test_only_dars_e_nizami_classes_are_offered_under_that_department(): void
    {
        $names = $this->classOptionsFor($this->darsENizami);

        $this->assertContains($this->salEAwwal->name, $names);
        $this->assertNotContains('Nazra', $names);
        $this->assertNotContains('Primary Section', $names);
    }

    /**
     * The section options reachable from one department, through its classes.
     *
     * @return array<int, string>
     */
    private function sectionOptionsFor(Department $department): array
    {
        $page = $this->get(route('students.index'))->assertOk();

        $classes = $page->viewData('classesByDepartment')[$department->id] ?? collect();
        $sectionsByClass = $page->viewData('sectionsByClass');

        return collect($classes)
            ->flatMap(fn ($class) => $sectionsByClass[$class['id']] ?? collect())
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }

    public function test_only_hifz_sections_are_reachable_under_hifz(): void
    {
        $names = $this->sectionOptionsFor($this->hifz);

        $this->assertSame(['Nazra-A'], $names);
    }

    public function test_only_school_sections_are_reachable_under_school(): void
    {
        $names = $this->sectionOptionsFor($this->school);

        $this->assertSame(['Primary-A'], $names);
    }

    public function test_only_dars_e_nizami_sections_are_reachable_under_that_department(): void
    {
        $names = $this->sectionOptionsFor($this->darsENizami);

        $this->assertSame(['Dars-A'], $names);
    }

    public function test_the_filter_row_clears_the_class_and_section_when_the_department_changes(): void
    {
        $html = $this->get(route('students.index'))->assertOk()->getContent();

        // The reset is Alpine's, so what is pinned here is that the
        // department select is actually wired to it and that the handler
        // clears both dependent values.
        $this->assertStringContainsString('@change="onDepartmentChange()"', $html);
        $this->assertStringContainsString("onDepartmentChange() {\n                        this.classId = ''\n                        this.sectionId = ''\n                    }", $html);
    }

    public function test_the_selected_filters_survive_the_round_trip(): void
    {
        $this->dualStudent();

        $html = $this->get(route('students.index', [
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->primaryA->id,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString("departmentId: '{$this->school->id}'", $html);
        $this->assertStringContainsString("classId: '{$this->primary->id}'", $html);
        $this->assertStringContainsString("sectionId: '{$this->primaryA->id}'", $html);
    }

    /* ---------------------------------------------------------------- */
    /* Cost */
    /* ---------------------------------------------------------------- */

    public function test_filtering_happens_in_the_database_and_costs_a_fixed_number_of_queries(): void
    {
        foreach (range(1, 6) as $index) {
            $this->student('Dual '.$index, 'Hifz + School', [
                ['track' => 'Madrassa', 'department' => $this->hifz, 'class' => $this->nazra, 'section' => $this->nazraA],
                ['track' => 'School', 'department' => $this->school, 'class' => $this->primary, 'section' => $this->primaryA],
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $names = $this->listed(['department_id' => $this->school->id]);

        $log = collect(DB::getQueryLog());

        DB::disableQueryLog();
        DB::flushQueryLog();

        $this->assertCount(6, $names);

        // The narrowing is done by the database: the students query carries
        // an exists() over the enrollments rather than every student being
        // loaded and sifted in PHP.
        $this->assertTrue(
            $log->contains(
                fn ($entry) => str_contains($entry['query'], 'from "students"')
                && str_contains($entry['query'], 'exists')
                && str_contains($entry['query'], 'student_academic_enrollments')
            ),
            'The placement filter is not being applied as a database constraint'
        );

        // Enrollments are still eager loaded for the Class column rather
        // than fetched per row.
        $enrollmentReads = $log
            ->filter(fn ($entry) => str_contains($entry['query'], 'from "student_academic_enrollments"'))
            ->count();

        $this->assertLessThanOrEqual(3, $enrollmentReads);
    }
}
