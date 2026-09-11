<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\ComputerCourse;
use App\Models\ComputerCourseSemester;
use App\Models\Department;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use App\Support\AcademicPlacement;
use Database\Seeders\AdmissionDepartmentClassSeeder;
use Database\Seeders\ComputerCourseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers Computer as a department of its own and the course it runs.
 *
 * The business change these describe: Computer stopped being an extra
 * facility attached to a student type and became the fourth department. A
 * Dars-e-Nizami + Computer student is therefore placed twice - once in the
 * madrassa and once in the Computer course - exactly as a Hifz + School
 * student is placed in the madrassa and the school.
 *
 * The Computer programme differs from the others in one respect only, and it
 * is the reason for the course tables: it progresses by semester rather than
 * by a class held for the year. Six stages, each with its own dates and its
 * own syllabus, both entered by the admin.
 */
class ComputerCourseTest extends TestCase
{
    use RefreshDatabase;

    private AcademicSession $session;

    private Department $computer;

    private Department $darsENizami;

    private AcademicClass $courseClass;

    private AcademicClass $salEAwwal;

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

        $this->computer = Department::where('name', 'Computer')->firstOrFail();
        $this->darsENizami = Department::where('name', 'Dars-e-Nizami')->firstOrFail();
        $this->courseClass = AcademicClass::where('department_id', $this->computer->id)->firstOrFail();
        $this->salEAwwal = AcademicClass::where('department_id', $this->darsENizami->id)
            ->orderBy('id')->firstOrFail();
    }

    /**
     * The payload a Dars-e-Nizami + Computer student is created with.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function computerPayload(array $overrides = []): array
    {
        return array_merge([
            'registration_number' => 'STD-2026-0001',
            'full_name' => 'Zaid Ahmed',
            'father_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03009998887',
            'admission_date' => '2026-04-01',
            'academic_session_id' => $this->session->id,
            'student_status' => 'Active',
            'student_type' => 'Dars-e-Nizami + Computer',
            'resident_type' => 'Local Resident',
            'madrassa_department_id' => $this->darsENizami->id,
            'madrassa_class_id' => $this->salEAwwal->id,
            'madrassa_section_id' => null,
            'computer_department_id' => $this->computer->id,
            'computer_class_id' => $this->courseClass->id,
            'computer_section_id' => null,
        ], $overrides);
    }

    /* ---------------------------------------------------------------- */
    /* Computer as a department */
    /* ---------------------------------------------------------------- */

    public function test_computer_is_one_of_the_active_departments(): void
    {
        $this->assertTrue($this->computer->status);

        $this->get(route('departments.index'))
            ->assertOk()
            ->assertSee('Computer');
    }

    public function test_the_department_guidance_names_computer_as_required(): void
    {
        $this->assertSame(
            ['Hifz', 'School', 'Dars-e-Nizami', 'Computer'],
            AcademicPlacement::requiredDepartmentNames()
        );

        $this->get(route('departments.index'))
            ->assertOk()
            ->assertSee('Dars-e-Nizami + Computer');
    }

    public function test_no_combined_department_was_created(): void
    {
        // The combinations are student types. A department named after one
        // would be the mistake the guidance exists to prevent.
        $this->assertNull(Department::where('name', 'Dars-e-Nizami + Computer')->first());
        $this->assertNull(Department::where('name', 'Hifz + School')->first());

        $this->assertSame(4, Department::where('status', true)->count());
    }

    /* ---------------------------------------------------------------- */
    /* The course and its semesters */
    /* ---------------------------------------------------------------- */

    public function test_the_three_year_course_exists_with_six_semesters(): void
    {
        $course = ComputerCourse::current();

        $this->assertNotNull($course);
        $this->assertSame('3-Year Computer Course', $course->name);
        $this->assertSame(3, $course->duration_years);
        $this->assertSame(6, $course->semester_count);
        $this->assertCount(6, $course->semesters);
    }

    public function test_the_semesters_are_ordered_one_to_six(): void
    {
        $semesters = ComputerCourse::current()->semesters;

        $this->assertSame([1, 2, 3, 4, 5, 6], $semesters->pluck('order')->all());
        $this->assertSame(
            ['1st Semester', '2nd Semester', '3rd Semester', '4th Semester', '5th Semester', '6th Semester'],
            $semesters->pluck('name')->all()
        );
    }

    public function test_a_semester_order_cannot_be_duplicated_within_a_course(): void
    {
        $course = ComputerCourse::current();

        $this->expectException(UniqueConstraintViolationException::class);

        ComputerCourseSemester::create([
            'computer_course_id' => $course->id,
            'order' => 1,
            'name' => 'Another First Semester',
        ]);
    }

    public function test_the_seeder_invents_no_dates_or_curriculum(): void
    {
        // Neither can be known in advance, so neither is guessed. An undated
        // semester is one the admin has not dated yet.
        foreach (ComputerCourse::current()->semesters as $semester) {
            $this->assertNull($semester->start_date);
            $this->assertNull($semester->end_date);
            $this->assertNull($semester->curriculum);
        }
    }

    public function test_running_the_seeder_twice_changes_nothing(): void
    {
        $semester = ComputerCourse::current()->semesters()->first();
        $semester->update(['curriculum' => "Computer Fundamentals\nWindows"]);

        $this->seed(ComputerCourseSeeder::class);

        $this->assertSame(1, ComputerCourse::count());
        $this->assertCount(6, ComputerCourse::current()->semesters);
        $this->assertSame("Computer Fundamentals\nWindows", $semester->fresh()->curriculum);
    }

    /* ---------------------------------------------------------------- */
    /* Managing the course */
    /* ---------------------------------------------------------------- */

    public function test_the_course_page_lists_every_semester(): void
    {
        $this->get(route('computer-course.index'))
            ->assertOk()
            ->assertSee('3-Year Computer Course')
            ->assertSee('1st Semester')
            ->assertSee('6th Semester')
            ->assertSee('What Will Be Taught');
    }

    public function test_a_semester_can_be_given_dates_and_a_curriculum(): void
    {
        $semester = ComputerCourse::current()->semesters()->first();

        $this->put(route('computer-course.semesters.update', $semester->id), [
            'name' => '1st Semester',
            'start_date' => '2026-04-01',
            'end_date' => '2026-09-30',
            // The admin's own words, over several lines. Nothing about these
            // subjects is built into the system.
            'curriculum' => "Computer Fundamentals\nWindows\nTyping\nBasic computer operations",
            'status' => '1',
        ])->assertRedirect(route('computer-course.index'))->assertSessionHas('success');

        $semester->refresh();

        $this->assertSame('2026-04-01', $semester->start_date->format('Y-m-d'));
        $this->assertSame('2026-09-30', $semester->end_date->format('Y-m-d'));
        $this->assertSame(
            "Computer Fundamentals\nWindows\nTyping\nBasic computer operations",
            $semester->curriculum
        );
    }

    public function test_a_curriculum_can_be_edited_afterwards(): void
    {
        $semester = ComputerCourse::current()->semesters()->skip(1)->first();

        $this->put(route('computer-course.semesters.update', $semester->id), [
            'name' => '2nd Semester',
            'curriculum' => "MS Word\nMS Excel",
            'status' => '1',
        ])->assertSessionHasNoErrors();

        $this->put(route('computer-course.semesters.update', $semester->id), [
            'name' => '2nd Semester',
            'curriculum' => "MS Word\nMS Excel\nMS PowerPoint",
            'status' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame("MS Word\nMS Excel\nMS PowerPoint", $semester->fresh()->curriculum);
    }

    public function test_a_semester_cannot_end_before_it_starts(): void
    {
        $semester = ComputerCourse::current()->semesters()->first();

        $this->put(route('computer-course.semesters.update', $semester->id), [
            'name' => '1st Semester',
            'start_date' => '2026-09-30',
            'end_date' => '2026-04-01',
            'status' => '1',
        ])->assertSessionHasErrors('end_date');

        $this->assertNull($semester->fresh()->start_date);
    }

    public function test_a_semester_may_be_left_undated(): void
    {
        $semester = ComputerCourse::current()->semesters()->first();

        $this->put(route('computer-course.semesters.update', $semester->id), [
            'name' => '1st Semester',
            'start_date' => '',
            'end_date' => '',
            'curriculum' => 'To be confirmed.',
            'status' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull($semester->fresh()->start_date);
    }

    public function test_semester_dates_are_not_tied_to_an_academic_session(): void
    {
        // The session runs to March 2027; the course runs for three years.
        // A semester dated beyond the session is accepted, because the
        // course is not a session-length structure.
        $semester = ComputerCourse::current()->semesters()->skip(4)->first();

        $this->put(route('computer-course.semesters.update', $semester->id), [
            'name' => '5th Semester',
            'start_date' => '2028-04-01',
            'end_date' => '2028-09-30',
            'status' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2028-04-01', $semester->fresh()->start_date->format('Y-m-d'));

        // And nothing about the course itself names a session.
        $this->assertArrayNotHasKey('academic_session_id', ComputerCourse::current()->getAttributes());
    }

    public function test_the_course_details_can_be_edited(): void
    {
        $course = ComputerCourse::current();

        $this->put(route('computer-course.update', $course->id), [
            'name' => '3-Year Computer Diploma',
            'duration_years' => 3,
            'semester_count' => 6,
            'status' => '1',
        ])->assertRedirect(route('computer-course.index'));

        $this->assertSame('3-Year Computer Diploma', $course->fresh()->name);
    }

    /* ---------------------------------------------------------------- */
    /* Placement */
    /* ---------------------------------------------------------------- */

    public function test_the_student_type_maps_to_both_departments(): void
    {
        $this->assertSame(
            ['madrassa' => 'Dars-e-Nizami', 'computer' => 'Computer'],
            AcademicPlacement::departmentNames('Dars-e-Nizami + Computer')
        );

        $this->assertSame('Computer', AcademicPlacement::track('computer'));
    }

    public function test_a_computer_student_gets_both_placements(): void
    {
        $this->post(route('students.store'), $this->computerPayload())
            ->assertSessionHasNoErrors();

        $student = Student::sole();
        $enrollments = $student->activeAcademicEnrollments;

        $this->assertCount(2, $enrollments);

        $madrassa = $enrollments->firstWhere('academic_track', 'Madrassa');
        $this->assertSame($this->darsENizami->id, $madrassa->department_id);
        $this->assertSame($this->salEAwwal->id, $madrassa->academic_class_id);
        $this->assertNull($madrassa->computer_course_semester_id);

        $computer = $enrollments->firstWhere('academic_track', 'Computer');
        $this->assertSame($this->computer->id, $computer->department_id);
        $this->assertSame($this->courseClass->id, $computer->academic_class_id);
    }

    public function test_a_new_computer_student_starts_at_the_first_semester(): void
    {
        $this->post(route('students.store'), $this->computerPayload())
            ->assertSessionHasNoErrors();

        $computer = Student::sole()->activeAcademicEnrollments->firstWhere('academic_track', 'Computer');

        $this->assertSame('1st Semester', $computer->computerCourseSemester->name);
        $this->assertSame(1, $computer->computerCourseSemester->order);
    }

    public function test_the_semester_is_a_reference_not_text(): void
    {
        $this->post(route('students.store'), $this->computerPayload())
            ->assertSessionHasNoErrors();

        $computer = Student::sole()->activeAcademicEnrollments->firstWhere('academic_track', 'Computer');
        $first = ComputerCourse::startingSemester();

        // The placement points at the semester record, so renaming the stage
        // reaches every student standing in it.
        $this->assertSame($first->id, $computer->computer_course_semester_id);

        $first->update(['name' => 'Semester One']);

        $this->assertSame('Semester One', $computer->fresh()->computerCourseSemester->name);
    }

    public function test_an_admin_may_place_a_student_in_a_later_semester(): void
    {
        $third = ComputerCourse::current()->semesters()->skip(2)->first();

        $this->post(route('students.store'), $this->computerPayload([
            AcademicPlacement::semesterField() => $third->id,
        ]))->assertSessionHasNoErrors();

        $computer = Student::sole()->activeAcademicEnrollments->firstWhere('academic_track', 'Computer');

        $this->assertSame('3rd Semester', $computer->computerCourseSemester->name);
    }

    public function test_a_dars_e_nizami_only_student_gets_no_computer_placement(): void
    {
        $this->post(route('students.store'), [
            ...$this->computerPayload(),
            'student_type' => 'Dars-e-Nizami',
            'department_id' => $this->darsENizami->id,
            'academic_class_id' => $this->salEAwwal->id,
            'section_id' => null,
            'madrassa_department_id' => null,
            'madrassa_class_id' => null,
            'madrassa_section_id' => null,
            'computer_department_id' => null,
            'computer_class_id' => null,
            'computer_section_id' => null,
        ])->assertSessionHasNoErrors();

        $student = Student::sole();

        $this->assertCount(1, $student->activeAcademicEnrollments);
        $this->assertSame('Madrassa', $student->activeAcademicEnrollments->first()->academic_track);
        $this->assertSame(0, StudentAcademicEnrollment::where('academic_track', 'Computer')->count());
    }

    public function test_the_listing_shows_the_madrassa_class_then_the_computer_semester(): void
    {
        $this->post(route('students.store'), $this->computerPayload())
            ->assertSessionHasNoErrors();

        // The Computer placement is described by its semester, because that
        // is what the Computer programme progresses by.
        $this->assertSame(
            $this->salEAwwal->name.', 1st Semester',
            Student::sole()->placementClassNames()
        );
    }

    /* ---------------------------------------------------------------- */
    /* Attendance stays as it was */
    /* ---------------------------------------------------------------- */

    public function test_computer_is_not_offered_as_an_attendance_track(): void
    {
        // No Computer attendance has been defined, so the attendance screens
        // do not offer the track - a track with no periods would draw a
        // sheet with no columns to mark.
        $this->assertSame(['Madrassa', 'School'], StudentAcademicEnrollment::attendanceTracks());

        $this->get(route('attendance.index'))
            ->assertOk()
            ->assertDontSee('<option value="Computer"', false);
    }

    public function test_computer_is_still_offered_where_an_enrollment_is_recorded(): void
    {
        // The enrollment form is not the attendance form: a Computer
        // placement has to be creatable even though it takes no register.
        $this->assertContains('Computer', StudentAcademicEnrollment::ACADEMIC_TRACKS);
    }

    public function test_an_attendance_sheet_cannot_be_marked_for_computer(): void
    {
        $this->post(route('attendance.store'), [
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Computer',
            'department_id' => $this->computer->id,
            'academic_class_id' => $this->courseClass->id,
            'attendance_period' => 'Morning',
            'month' => 4,
            'year' => 2026,
        ])->assertSessionHasErrors('academic_track');
    }

    /* ---------------------------------------------------------------- */
    /* Existing students are left alone */
    /* ---------------------------------------------------------------- */

    public function test_existing_computer_students_are_not_backfilled(): void
    {
        // A student admitted while Computer was still a facility: the type
        // says Computer, but only the madrassa placement was ever written.
        $student = Student::create([
            'registration_number' => 'STD-2025-0001',
            'full_name' => 'Older Student',
            'father_name' => 'Ali',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03009998887',
            'admission_date' => '2025-04-01',
            'academic_session_id' => $this->session->id,
            'student_status' => 'Active',
            'student_type' => 'Dars-e-Nizami + Computer',
            'resident_type' => 'Local Resident',
            'department_id' => $this->darsENizami->id,
            'academic_class_id' => $this->salEAwwal->id,
        ]);

        $student->academicEnrollments()->create([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->darsENizami->id,
            'academic_class_id' => $this->salEAwwal->id,
            'start_date' => '2025-04-01',
            'status' => 'Active',
        ]);

        // Nothing in the system creates the missing placement for them: the
        // semester they are actually in is not known, and guessing it would
        // be inventing academic history.
        $this->get(route('students.index'))->assertOk();
        $this->get(route('students.edit', $student->id))->assertOk();

        $this->assertCount(1, $student->fresh()->activeAcademicEnrollments);

        // The reporting command finds them and still writes nothing.
        $this->artisan('computer:missing-placements')
            ->expectsOutputToContain('STD-2025-0001')
            ->assertSuccessful();

        $this->assertCount(1, $student->fresh()->activeAcademicEnrollments);
    }
}
