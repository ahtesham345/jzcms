<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\ComputerCourse;
use App\Models\Department;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use App\Support\AcademicPlacement;
use Database\Seeders\AdmissionDepartmentClassSeeder;
use Database\Seeders\ComputerCourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers what an academic session means to a Computer placement.
 *
 * It means when the student started the course, and nothing else. The
 * Computer course runs for three years across about three sessions, so the
 * session cannot be the period the placement belongs to - the semester is
 * where the student actually stands, and no session change may move it.
 *
 * The column is still required and still filled, because every enrollment
 * has one and because the year a student began is worth recording. What
 * these describe is that it stays the year they began.
 */
class ComputerSessionSemanticsTest extends TestCase
{
    use RefreshDatabase;

    private AcademicSession $first;

    private AcademicSession $second;

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

        $this->first = AcademicSession::create([
            'name' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'status' => true,
        ]);

        $this->second = AcademicSession::create([
            'name' => '2027-2028', 'start_date' => '2027-04-01', 'end_date' => '2028-03-31',
            'status' => true,
        ]);

        $this->computer = Department::where('name', 'Computer')->firstOrFail();
        $this->darsENizami = Department::where('name', 'Dars-e-Nizami')->firstOrFail();
        $this->courseClass = AcademicClass::where('department_id', $this->computer->id)->firstOrFail();
        $this->salEAwwal = AcademicClass::where('department_id', $this->darsENizami->id)
            ->orderBy('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'registration_number' => 'STD-2026-0001',
            'full_name' => 'Zaid Ahmed',
            'father_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03009998887',
            'admission_date' => '2026-04-01',
            'academic_session_id' => $this->first->id,
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

    private function createStudent(): Student
    {
        $this->post(route('students.store'), $this->payload())->assertSessionHasNoErrors();

        return Student::sole();
    }

    /* ---------------------------------------------------------------- */
    /* The session is the start year, and it stays there */
    /* ---------------------------------------------------------------- */

    public function test_a_computer_placement_records_the_session_it_began_in(): void
    {
        $computer = $this->createStudent()
            ->activeAcademicEnrollments->firstWhere('academic_track', 'Computer');

        $this->assertSame($this->first->id, $computer->academic_session_id);
    }

    public function test_filing_the_student_into_a_new_session_leaves_the_computer_placement_where_it_began(): void
    {
        $student = $this->createStudent();
        $semesterBefore = $student->activeAcademicEnrollments
            ->firstWhere('academic_track', 'Computer')->computer_course_semester_id;

        // A year later the student is filed into the new session.
        $this->put(route('students.update', $student->id), $this->payload([
            'academic_session_id' => $this->second->id,
        ]))->assertSessionHasNoErrors();

        $enrollments = $student->fresh()->activeAcademicEnrollments;

        $computer = $enrollments->firstWhere('academic_track', 'Computer');
        $madrassa = $enrollments->firstWhere('academic_track', 'Madrassa');

        // The Computer placement keeps the year the course started in: the
        // course is three years long and did not restart.
        $this->assertSame($this->first->id, $computer->academic_session_id);

        // And the semester - where they actually are - is untouched.
        $this->assertSame($semesterBefore, $computer->computer_course_semester_id);
        $this->assertSame('1st Semester', $computer->computerCourseSemester->name);

        // The madrassa placement keeps the existing behaviour: it does move,
        // because a madrassa class does belong to a session.
        $this->assertSame($this->second->id, $madrassa->academic_session_id);
    }

    public function test_a_new_session_creates_no_second_computer_placement(): void
    {
        $student = $this->createStudent();

        $this->put(route('students.update', $student->id), $this->payload([
            'academic_session_id' => $this->second->id,
        ]))->assertSessionHasNoErrors();

        $this->assertCount(
            1,
            $student->fresh()->academicEnrollments()->where('academic_track', 'Computer')->get()
        );
    }

    public function test_the_computer_track_is_not_session_bound(): void
    {
        // Nothing stops a Computer placement existing alongside another in
        // the same session, the same freedom the madrassa has, because the
        // rule exists for the school year and not for a course.
        $this->assertFalse(
            StudentAcademicEnrollment::trackIsSessionBound('Computer')
        );
    }

    public function test_the_semester_survives_a_change_to_the_course_dates(): void
    {
        $student = $this->createStudent();
        $semester = ComputerCourse::startingSemester();

        // Dating the semester across two sessions is allowed and changes
        // nobody's placement.
        $this->put(route('computer-course.semesters.update', $semester->id), [
            'name' => '1st Semester',
            'start_date' => '2026-04-01',
            'end_date' => '2027-09-30',
            'status' => '1',
        ])->assertSessionHasNoErrors();

        $computer = $student->fresh()->activeAcademicEnrollments->firstWhere('academic_track', 'Computer');

        $this->assertSame($semester->id, $computer->computer_course_semester_id);
        $this->assertSame($this->first->id, $computer->academic_session_id);
    }

    /* ---------------------------------------------------------------- */
    /* The two semester workflows do not collide */
    /* ---------------------------------------------------------------- */

    public function test_an_admins_chosen_semester_is_not_overwritten_by_a_later_edit(): void
    {
        $third = ComputerCourse::current()->semesters()->skip(2)->first();

        $student = $this->createStudent();

        // The office moves them to the third semester.
        $this->put(route('students.update', $student->id), $this->payload([
            AcademicPlacement::semesterField() => $third->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            '3rd Semester',
            $student->fresh()->activeAcademicEnrollments
                ->firstWhere('academic_track', 'Computer')->computerCourseSemester->name
        );

        // An unrelated later edit that carries the same semester back - which
        // is what the prefilled form posts - leaves them in the third.
        $this->put(route('students.update', $student->id), $this->payload([
            'full_name' => 'Zaid Ahmed Corrected',
            AcademicPlacement::semesterField() => $third->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            '3rd Semester',
            $student->fresh()->activeAcademicEnrollments
                ->firstWhere('academic_track', 'Computer')->computerCourseSemester->name
        );
    }

    public function test_the_edit_form_shows_the_semester_the_student_is_actually_in(): void
    {
        $fourth = ComputerCourse::current()->semesters()->skip(3)->first();
        $student = $this->createStudent();

        $this->put(route('students.update', $student->id), $this->payload([
            AcademicPlacement::semesterField() => $fourth->id,
        ]))->assertSessionHasNoErrors();

        // Reopening the form offers their real semester, not the course's
        // first one, so a later save cannot quietly send them back to the
        // beginning.
        $html = $this->get(route('students.edit', $student->id))->assertOk()->getContent();

        $this->assertStringContainsString("semesterId: '{$fourth->id}'", $html);
    }
}
