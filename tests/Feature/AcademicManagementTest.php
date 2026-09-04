<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicManagementTest extends TestCase
{
    use RefreshDatabase;

    private AcademicSession $session2026;

    private AcademicSession $session2027;

    private Department $hifz;

    private Department $school;

    private AcademicClass $nazra;

    private AcademicClass $hifzClass;

    private AcademicClass $primary;

    private Section $nazraA;

    private Section $primaryB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->seed(\Database\Seeders\AdmissionDepartmentClassSeeder::class);

        $this->session2026 = AcademicSession::create([
            'name' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'status' => true,
        ]);
        $this->session2027 = AcademicSession::create([
            'name' => '2027-2028', 'start_date' => '2027-04-01', 'end_date' => '2028-03-31',
            'status' => true,
        ]);

        $this->hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->school = Department::where('name', 'School')->firstOrFail();

        $this->nazra = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Nazra')->firstOrFail();
        $this->hifzClass = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Hifz')->firstOrFail();
        $this->primary = AcademicClass::where('department_id', $this->school->id)->where('name', 'Primary Section')->firstOrFail();

        $this->nazraA = $this->section('Nazra-A', $this->nazra);
        $this->primaryB = $this->section('Primary-B', $this->primary);
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

    private function student(array $overrides = []): Student
    {
        static $sequence = 0;
        $sequence++;

        return Student::create(array_merge([
            'registration_number' => 'STD-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'full_name' => 'Ahmed Ali',
            'father_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03007654321',
            'admission_date' => '2026-04-01',
            'academic_session_id' => $this->session2026->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->nazraA->id,
            'student_status' => 'Active',
            'student_type' => 'Hifz',
            'resident_type' => 'Local Resident',
        ], $overrides));
    }

    private function enroll(Student $student, array $overrides = []): StudentAcademicEnrollment
    {
        return $student->academicEnrollments()->create(array_merge([
            'academic_session_id' => $this->session2026->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->nazraA->id,
            'start_date' => '2026-04-01',
            'status' => 'Active',
        ], $overrides));
    }

    /**
     * A student holding one active enrollment per track.
     */
    private function dualTrackStudent(array $overrides = []): Student
    {
        $student = $this->student(array_merge([
            'full_name' => 'Dual Track Student',
            'student_type' => 'Hifz + School',
        ], $overrides));

        $this->enroll($student, ['academic_track' => 'Madrassa']);
        $this->enroll($student, [
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->primaryB->id,
        ]);

        return $student;
    }

    private function visit(array $query = [])
    {
        return $this->get(route('academics.index', $query));
    }

    /* ---------------------------------------------------------------- */
    /* Access                                                           */
    /* ---------------------------------------------------------------- */

    public function test_an_authenticated_user_can_open_the_page(): void
    {
        $this->visit()
            ->assertOk()
            ->assertSee('Academic Management', false)
            ->assertSee('Manage and review student academic records, enrollments and academic progression.', false);
    }

    public function test_a_guest_cannot_open_the_page(): void
    {
        auth()->logout();

        $this->visit()->assertRedirect(route('login'));
    }

    public function test_the_sidebar_links_to_the_page(): void
    {
        $this->visit()
            ->assertOk()
            ->assertSee('href="'.route('academics.index').'"', false);
    }

    /* ---------------------------------------------------------------- */
    /* Records                                                          */
    /* ---------------------------------------------------------------- */

    public function test_active_records_are_listed(): void
    {
        $student = $this->student(['full_name' => 'Listed Student', 'registration_number' => 'STD-9001']);
        $this->enroll($student);

        $this->visit()
            ->assertOk()
            ->assertSee('Listed Student', false)
            ->assertSee('STD-9001', false)
            ->assertSee('2026-2027', false)
            ->assertSee('Madrassa', false)
            ->assertSee('Nazra', false)
            ->assertSee('Nazra-A', false)
            ->assertSee('Active', false)
            ->assertDontSee('No academic records found.', false);
    }

    public function test_a_dual_track_student_shows_both_active_tracks(): void
    {
        $this->dualTrackStudent(['registration_number' => 'STD-8001']);

        $response = $this->visit()->assertOk();

        // Two rows for the one student, one per track.
        $this->assertSame(2, substr_count($response->getContent(), 'STD-8001</td>'));
        $response->assertSee('Madrassa', false)
            ->assertSee('School', false)
            ->assertSee('Nazra', false)
            ->assertSee('Primary Section', false)
            ->assertSee('Primary-B', false);

        $this->assertSame(2, StudentAcademicEnrollment::where('status', 'Active')->count());
    }

    public function test_completed_and_left_records_can_be_filtered(): void
    {
        $student = $this->student(['full_name' => 'Historic Student']);
        $this->enroll($student, ['status' => 'Active']);
        $this->enroll($student, [
            'academic_session_id' => $this->session2027->id,
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->primaryB->id,
            'status' => 'Completed',
            'end_date' => '2028-03-31',
        ]);

        $other = $this->student(['full_name' => 'Departed Student']);
        $this->enroll($other, ['status' => 'Left', 'end_date' => '2026-09-01']);

        $this->visit(['status' => 'Completed'])
            ->assertOk()
            ->assertSee('Historic Student', false)
            ->assertDontSee('Departed Student', false);

        $this->visit(['status' => 'Left'])
            ->assertOk()
            ->assertSee('Departed Student', false);

        $this->visit(['status' => 'Active'])
            ->assertOk()
            ->assertSee('Historic Student', false)
            ->assertDontSee('Departed Student', false);
    }

    /* ---------------------------------------------------------------- */
    /* Search                                                           */
    /* ---------------------------------------------------------------- */

    public function test_search_matches_name_registration_and_roll_number(): void
    {
        $wanted = $this->student([
            'full_name' => 'Bilal Khan',
            'registration_number' => 'STD-7001',
            'roll_number' => 'ROLL-55',
        ]);
        $this->enroll($wanted);

        $other = $this->student([
            'full_name' => 'Usman Tariq',
            'registration_number' => 'STD-7002',
            'roll_number' => 'ROLL-99',
        ]);
        $this->enroll($other);

        $this->visit(['search' => 'Bilal'])
            ->assertOk()->assertSee('Bilal Khan', false)->assertDontSee('Usman Tariq', false);

        $this->visit(['search' => 'STD-7001'])
            ->assertOk()->assertSee('Bilal Khan', false)->assertDontSee('Usman Tariq', false);

        $this->visit(['search' => 'ROLL-99'])
            ->assertOk()->assertSee('Usman Tariq', false)->assertDontSee('Bilal Khan', false);
    }

    /* ---------------------------------------------------------------- */
    /* Filters                                                          */
    /* ---------------------------------------------------------------- */

    public function test_each_filter_narrows_the_records(): void
    {
        $madrassa = $this->student(['full_name' => 'Madrassa Only']);
        $this->enroll($madrassa);

        $schoolStudent = $this->student(['full_name' => 'School Only', 'student_type' => 'School']);
        $this->enroll($schoolStudent, [
            'academic_session_id' => $this->session2027->id,
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->primaryB->id,
        ]);

        // Session
        $this->visit(['academic_session_id' => $this->session2026->id])
            ->assertOk()->assertSee('Madrassa Only', false)->assertDontSee('School Only', false);

        // Department
        $this->visit(['department_id' => $this->school->id])
            ->assertOk()->assertSee('School Only', false)->assertDontSee('Madrassa Only', false);

        // Class
        $this->visit(['academic_class_id' => $this->nazra->id])
            ->assertOk()->assertSee('Madrassa Only', false)->assertDontSee('School Only', false);

        // Section
        $this->visit(['section_id' => $this->primaryB->id])
            ->assertOk()->assertSee('School Only', false)->assertDontSee('Madrassa Only', false);

        // Track
        $this->visit(['academic_track' => 'School'])
            ->assertOk()->assertSee('School Only', false)->assertDontSee('Madrassa Only', false);
    }

    public function test_filters_combine(): void
    {
        $wanted = $this->student(['full_name' => 'Wanted Student']);
        $this->enroll($wanted);

        // Same session and department, different track and status.
        $other = $this->student(['full_name' => 'Other Student']);
        $this->enroll($other, ['status' => 'Completed', 'end_date' => '2027-01-01']);

        $this->visit([
            'academic_session_id' => $this->session2026->id,
            'department_id' => $this->hifz->id,
            'academic_track' => 'Madrassa',
            'status' => 'Active',
        ])
            ->assertOk()
            ->assertSee('Wanted Student', false)
            ->assertDontSee('Other Student', false);
    }

    public function test_a_contradictory_filter_combination_returns_nothing(): void
    {
        $student = $this->student(['full_name' => 'Hifz Student']);
        $this->enroll($student);

        // The class belongs to School, the department filter says Hifz.
        // Neither filter may be quietly dropped.
        $this->visit([
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->primary->id,
        ])
            ->assertOk()
            ->assertSee('No academic records found.', false)
            ->assertDontSee('Hifz Student', false);
    }

    public function test_historical_records_stay_visible_when_their_master_data_goes_inactive(): void
    {
        $student = $this->student(['full_name' => 'Retired Class Student']);
        $this->enroll($student, ['status' => 'Completed', 'end_date' => '2027-03-31']);

        // The class, its department and its section are all retired.
        $this->hifz->update(['status' => false]);
        $this->nazra->update(['status' => false]);
        $this->nazraA->update(['status' => false]);

        $this->visit()
            ->assertOk()
            ->assertSee('Retired Class Student', false)
            ->assertSee('Nazra', false)
            ->assertSee('Nazra-A', false)
            ->assertDontSee('No academic records found.', false);

        // And they remain filterable by the ids they still reference.
        $this->visit(['academic_class_id' => $this->nazra->id])
            ->assertOk()
            ->assertSee('Retired Class Student', false);
    }

    /* ---------------------------------------------------------------- */
    /* Pagination                                                       */
    /* ---------------------------------------------------------------- */

    public function test_records_are_paginated_at_twenty_per_page(): void
    {
        foreach (range(1, 25) as $i) {
            $student = $this->student(['full_name' => "Paged Student {$i}"]);
            $this->enroll($student);
        }

        $this->visit()
            ->assertOk()
            ->assertSee('Showing 1 to 20 of 25 academic records', false);

        $this->visit(['page' => 2])
            ->assertOk()
            ->assertSee('Showing 21 to 25 of 25 academic records', false);
    }

    public function test_search_and_filters_survive_pagination(): void
    {
        foreach (range(1, 25) as $i) {
            $student = $this->student(['full_name' => "Filtered Student {$i}"]);
            $this->enroll($student);
        }

        // One record that the filter must exclude on every page.
        $excluded = $this->student(['full_name' => 'Excluded Student']);
        $this->enroll($excluded, ['status' => 'Left', 'end_date' => '2026-09-01']);

        $response = $this->visit(['status' => 'Active', 'academic_track' => 'Madrassa'])->assertOk();
        $this->assertStringContainsString('status=Active', $response->getContent());
        $this->assertStringContainsString('academic_track=Madrassa', $response->getContent());
        $this->assertStringContainsString('page=2', $response->getContent());

        $this->visit(['status' => 'Active', 'academic_track' => 'Madrassa', 'page' => 2])
            ->assertOk()
            ->assertSee('Showing 21 to 25 of 25 academic records', false)
            ->assertDontSee('Excluded Student', false);
    }

    /* ---------------------------------------------------------------- */
    /* Summary cards                                                    */
    /* ---------------------------------------------------------------- */

    public function test_the_summary_counts_students_not_rows(): void
    {
        // One madrassa only, one school only, one dual track.
        $madrassaOnly = $this->student(['full_name' => 'Madrassa Only']);
        $this->enroll($madrassaOnly);

        $schoolOnly = $this->student(['full_name' => 'School Only']);
        $this->enroll($schoolOnly, [
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->primaryB->id,
        ]);

        $this->dualTrackStudent();

        $response = $this->visit()->assertOk();

        // 4 active rows across 3 students.
        $this->assertSame(4, StudentAcademicEnrollment::where('status', 'Active')->count());

        $summary = $response->viewData('summary');
        $this->assertSame(4, $summary['active_records']);
        $this->assertSame(2, $summary['madrassa_students'], 'Madrassa students are distinct students');
        $this->assertSame(2, $summary['school_students'], 'School students are distinct students');
        $this->assertSame(1, $summary['dual_track_students'], 'The dual-track student counts once, not twice');
    }

    public function test_completed_records_are_not_counted_as_active(): void
    {
        $student = $this->student();
        $this->enroll($student, ['status' => 'Completed', 'end_date' => '2027-03-31']);
        $this->enroll($student, [
            'academic_session_id' => $this->session2027->id,
            'start_date' => '2027-04-01',
            'status' => 'Active',
        ]);

        $summary = $this->visit()->assertOk()->viewData('summary');

        $this->assertSame(1, $summary['active_records']);
        $this->assertSame(1, $summary['madrassa_students']);
        $this->assertSame(0, $summary['school_students']);
        $this->assertSame(0, $summary['dual_track_students']);
    }

    public function test_a_dual_track_student_with_one_completed_track_is_not_dual_track(): void
    {
        $student = $this->dualTrackStudent();

        // The school side finishes; only madrassa stays active.
        $student->activeEnrollmentForTrack('School')->update([
            'status' => 'Completed',
            'end_date' => '2027-03-31',
        ]);

        $summary = $this->visit()->assertOk()->viewData('summary');

        $this->assertSame(1, $summary['active_records']);
        $this->assertSame(1, $summary['madrassa_students']);
        $this->assertSame(0, $summary['school_students']);
        $this->assertSame(0, $summary['dual_track_students']);
    }

    /* ---------------------------------------------------------------- */
    /* Actions                                                          */
    /* ---------------------------------------------------------------- */

    public function test_the_actions_point_at_the_existing_workflows(): void
    {
        $student = $this->student(['full_name' => 'Actionable Student']);
        $this->enroll($student);

        $this->visit()
            ->assertOk()
            // The existing student profile.
            ->assertSee('href="'.route('students.show', $student->id).'"', false)
            // The existing academic history section on that profile, which
            // is also where the existing Add Enrollment form lives.
            ->assertSee('href="'.route('students.show', $student->id).'#academic-history"', false)
            // The existing promotion route, not a second implementation.
            ->assertSee('href="'.route('students.promote', $student->id).'"', false)
            ->assertSee('View Student', false)
            ->assertSee('Academic History', false)
            ->assertSee('Add Enrollment', false)
            ->assertSee('Promote', false);
    }

    public function test_the_academic_history_anchor_exists_on_the_profile(): void
    {
        $student = $this->student();
        $this->enroll($student);

        // The Academic History and Add Enrollment actions deep link here.
        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('id="academic-history"', false)
            ->assertSee('id="current-enrollment"', false);
    }

    public function test_promotion_is_not_offered_for_an_ineligible_student(): void
    {
        $student = $this->student(['full_name' => 'Left Student', 'student_status' => 'Left']);
        $this->enroll($student);

        $this->visit()
            ->assertOk()
            ->assertSee('Left Student', false)
            ->assertDontSee('href="'.route('students.promote', $student->id).'"', false);
    }

    /* ---------------------------------------------------------------- */
    /* Empty states                                                     */
    /* ---------------------------------------------------------------- */

    public function test_the_empty_state_renders_when_nothing_matches_the_filters(): void
    {
        $student = $this->student(['full_name' => 'Existing Student']);
        $this->enroll($student);

        $this->visit(['search' => 'nobody-by-this-name'])
            ->assertOk()
            ->assertSee('No academic records found.', false)
            ->assertSee('Try adjusting your search or filter criteria.', false)
            ->assertDontSee('Existing Student', false);
    }

    public function test_the_empty_state_renders_when_there_are_no_records_at_all(): void
    {
        $this->visit()
            ->assertOk()
            ->assertSee('No academic records found.', false)
            ->assertSee('Academic records appear here once students are enrolled.', false);

        $summary = $this->visit()->viewData('summary');
        $this->assertSame(0, $summary['active_records']);
        $this->assertSame(0, $summary['dual_track_students']);
    }

    /* ---------------------------------------------------------------- */
    /* Performance                                                      */
    /* ---------------------------------------------------------------- */

    public function test_the_listing_does_not_run_a_query_per_row(): void
    {
        foreach (range(1, 15) as $i) {
            $student = $this->student(['full_name' => "Student {$i}"]);
            $this->enroll($student);
        }

        \DB::enableQueryLog();
        $this->visit()->assertOk();
        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        // Records + count + five eager loads + four summary counts + the
        // filter option lists. Comfortably bounded, and unrelated to how
        // many rows the page happens to show.
        $this->assertLessThan(25, $queries, "The page ran {$queries} queries; it must not query per row");
    }
}
