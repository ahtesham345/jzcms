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

/**
 * Covers the academic record page and the group drill-down.
 */
class AcademicGroupTest extends TestCase
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
     * The school-side enrollment overrides.
     *
     * @return array<string, mixed>
     */
    private function schoolTrack(array $overrides = []): array
    {
        return array_merge([
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->primaryB->id,
        ], $overrides);
    }

    /**
     * The parameters naming the madrassa Nazra-A group for 2026.
     *
     * @return array<string, mixed>
     */
    private function madrassaGroup(array $overrides = []): array
    {
        return array_merge([
            'session' => $this->session2026->id,
            'track' => 'Madrassa',
            'department' => $this->hifz->id,
            'class' => $this->nazra->id,
            'section' => $this->nazraA->id,
        ], $overrides);
    }

    /* ---------------------------------------------------------------- */
    /* Academic record page                                             */
    /* ---------------------------------------------------------------- */

    public function test_an_authenticated_user_can_view_an_academic_record(): void
    {
        $student = $this->student(['full_name' => 'Recorded Student', 'roll_number' => 'ROLL-12']);
        $record = $this->enroll($student, ['notes' => 'Doing well.']);

        $this->get(route('academics.enrollments.show', $record->id))
            ->assertOk()
            // Student information.
            ->assertSee('Recorded Student', false)
            ->assertSee('STD-0001', false)
            ->assertSee('ROLL-12', false)
            ->assertSee('Hifz', false)
            ->assertSee('Active', false)
            // Current academic placement, from the enrollment itself.
            ->assertSee('Current Academic Placement', false)
            ->assertSee('2026-2027', false)
            ->assertSee('Madrassa', false)
            ->assertSee('Nazra', false)
            ->assertSee('Nazra-A', false)
            ->assertSee('01 Apr, 2026', false)
            ->assertSee('Doing well.', false);
    }

    public function test_a_guest_cannot_view_an_academic_record(): void
    {
        $record = $this->enroll($this->student());
        auth()->logout();

        $this->get(route('academics.enrollments.show', $record->id))->assertRedirect(route('login'));
    }

    public function test_an_unknown_enrollment_is_a_404(): void
    {
        $this->get(route('academics.enrollments.show', 999999))->assertNotFound();
    }

    public function test_the_record_links_to_the_existing_student_and_promotion_routes(): void
    {
        $student = $this->student();
        $record = $this->enroll($student);

        $this->get(route('academics.enrollments.show', $record->id))
            ->assertOk()
            ->assertSee('href="'.route('students.show', $student->id).'"', false)
            ->assertSee('href="'.route('students.promote', $student->id).'"', false)
            ->assertSee('View Student', false)
            ->assertSee('Promote', false);
    }

    public function test_the_history_shows_only_the_records_own_track(): void
    {
        $student = $this->student(['student_type' => 'Hifz + School']);

        // Two madrassa records across sessions.
        $this->enroll($student, ['status' => 'Completed', 'end_date' => '2027-03-31']);
        $madrassaCurrent = $this->enroll($student, [
            'academic_session_id' => $this->session2027->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => null,
            'start_date' => '2027-04-01',
        ]);

        // And a school record that must not appear in the madrassa history.
        $this->enroll($student, $this->schoolTrack());

        $response = $this->get(route('academics.enrollments.show', $madrassaCurrent->id))->assertOk();

        // Both madrassa rows are listed: the classes and sessions of each.
        $response->assertSee('Madrassa Academic History', false)
            ->assertSee('2026-2027', false)
            ->assertSee('2027-2028', false)
            ->assertSee('Nazra', false);

        // The school placement is not in the history table. It is named in
        // the "other active track" panel, so the assertion is on the table.
        $historyTable = substr(
            $response->getContent(),
            (int) strpos($response->getContent(), 'Madrassa Academic History')
        );
        $this->assertStringNotContainsString('Primary Section', $historyTable);
        $this->assertStringNotContainsString('Primary-B', $historyTable);

        // Two history rows, not three: the school record is excluded.
        $this->assertSame(2, substr_count($historyTable, '<tr class="hover:bg-gray-50'));
        $this->assertSame(3, $student->academicEnrollments()->count(), 'The student does hold three enrollments');
    }

    public function test_the_school_record_shows_only_school_history(): void
    {
        $student = $this->student(['student_type' => 'Hifz + School']);
        $this->enroll($student, ['notes' => 'Madrassa side only']);
        $schoolRecord = $this->enroll($student, $this->schoolTrack(['notes' => 'School first year']));

        $this->get(route('academics.enrollments.show', $schoolRecord->id))
            ->assertOk()
            ->assertSee('School Academic History', false)
            ->assertSee('School first year', false)
            ->assertDontSee('Madrassa side only', false);
    }

    public function test_the_other_active_track_is_shown_when_present(): void
    {
        $student = $this->student(['student_type' => 'Hifz + School']);
        $madrassa = $this->enroll($student);
        $school = $this->enroll($student, $this->schoolTrack());

        $this->get(route('academics.enrollments.show', $madrassa->id))
            ->assertOk()
            ->assertSee('Other Active Academic Track', false)
            ->assertSee('Primary Section', false)
            // Linking to that track's own academic record.
            ->assertSee('href="'.route('academics.enrollments.show', $school->id).'"', false);
    }

    public function test_the_other_active_track_is_hidden_when_absent(): void
    {
        $record = $this->enroll($this->student());

        $this->get(route('academics.enrollments.show', $record->id))
            ->assertOk()
            ->assertDontSee('Other Active Academic Track', false);
    }

    public function test_a_completed_other_track_is_not_shown_as_active(): void
    {
        $student = $this->student(['student_type' => 'Hifz + School']);
        $madrassa = $this->enroll($student);
        $this->enroll($student, $this->schoolTrack([
            'status' => 'Completed', 'end_date' => '2027-03-31',
        ]));

        $this->get(route('academics.enrollments.show', $madrassa->id))
            ->assertOk()
            ->assertDontSee('Other Active Academic Track', false);
    }

    public function test_the_record_survives_its_master_data_going_inactive(): void
    {
        $student = $this->student(['full_name' => 'Retired Data Student']);
        $record = $this->enroll($student, ['status' => 'Completed', 'end_date' => '2027-03-31']);

        $this->hifz->update(['status' => false]);
        $this->nazra->update(['status' => false]);
        $this->nazraA->update(['status' => false]);
        $this->session2026->update(['status' => false, 'is_current' => false]);

        $this->get(route('academics.enrollments.show', $record->id))
            ->assertOk()
            ->assertSee('Retired Data Student', false)
            ->assertSee('2026-2027', false)
            ->assertSee('Hifz', false)
            ->assertSee('Nazra', false)
            ->assertSee('Nazra-A', false);
    }

    public function test_each_record_shows_its_own_students_data(): void
    {
        $mine = $this->student(['full_name' => 'My Student', 'registration_number' => 'STD-6001']);
        $other = $this->student(['full_name' => 'Other Student', 'registration_number' => 'STD-6002']);
        $mineRecord = $this->enroll($mine);
        $this->enroll($other);

        $this->get(route('academics.enrollments.show', $mineRecord->id))
            ->assertOk()
            ->assertSee('My Student', false)
            ->assertDontSee('Other Student', false);
    }

    /* ---------------------------------------------------------------- */
    /* Group page                                                       */
    /* ---------------------------------------------------------------- */

    public function test_the_group_page_loads_with_its_heading(): void
    {
        $student = $this->student(['full_name' => 'Group Member']);
        $this->enroll($student);

        $this->get(route('academics.groups', $this->madrassaGroup()))
            ->assertOk()
            ->assertSee('Madrassa - Nazra - Section Nazra-A', false)
            ->assertSee('Academic Session: 2026-2027', false)
            ->assertSee('Group Member', false);
    }

    public function test_a_guest_cannot_view_the_group_page(): void
    {
        auth()->logout();

        $this->get(route('academics.groups', $this->madrassaGroup()))->assertRedirect(route('login'));
    }

    public function test_each_group_parameter_narrows_the_records(): void
    {
        $inGroup = $this->student(['full_name' => 'In The Group']);
        $this->enroll($inGroup);

        $otherSession = $this->student(['full_name' => 'Other Session']);
        $this->enroll($otherSession, ['academic_session_id' => $this->session2027->id, 'start_date' => '2027-04-01']);

        $otherTrack = $this->student(['full_name' => 'Other Track']);
        $this->enroll($otherTrack, $this->schoolTrack());

        $otherClass = $this->student(['full_name' => 'Other Class']);
        $this->enroll($otherClass, ['academic_class_id' => $this->hifzClass->id, 'section_id' => null]);

        $response = $this->get(route('academics.groups', $this->madrassaGroup()))->assertOk();

        $response->assertSee('In The Group', false)
            ->assertDontSee('Other Session', false)
            ->assertDontSee('Other Track', false)
            ->assertDontSee('Other Class', false);

        // Each parameter alone also narrows.
        $this->get(route('academics.groups', ['session' => $this->session2027->id]))
            ->assertOk()->assertSee('Other Session', false)->assertDontSee('In The Group', false);

        $this->get(route('academics.groups', ['track' => 'School']))
            ->assertOk()->assertSee('Other Track', false)->assertDontSee('In The Group', false);

        $this->get(route('academics.groups', ['department' => $this->school->id]))
            ->assertOk()->assertSee('Other Track', false)->assertDontSee('In The Group', false);

        $this->get(route('academics.groups', ['class' => $this->hifzClass->id]))
            ->assertOk()->assertSee('Other Class', false)->assertDontSee('In The Group', false);

        $this->get(route('academics.groups', ['section' => $this->primaryB->id]))
            ->assertOk()->assertSee('Other Track', false)->assertDontSee('In The Group', false);
    }

    public function test_a_contradictory_group_returns_no_records(): void
    {
        $student = $this->student(['full_name' => 'Hifz Student']);
        $this->enroll($student);

        // A Hifz department with a School class belongs to nobody.
        $this->get(route('academics.groups', [
            'department' => $this->hifz->id,
            'class' => $this->primary->id,
        ]))
            ->assertOk()
            ->assertSee('No academic records found.', false)
            ->assertDontSee('Hifz Student', false);
    }

    public function test_the_group_search_matches_name_registration_and_roll_number(): void
    {
        $wanted = $this->student([
            'full_name' => 'Bilal Khan', 'registration_number' => 'STD-5001', 'roll_number' => 'ROLL-55',
        ]);
        $this->enroll($wanted);

        $other = $this->student([
            'full_name' => 'Usman Tariq', 'registration_number' => 'STD-5002', 'roll_number' => 'ROLL-99',
        ]);
        $this->enroll($other);

        foreach (['Bilal' => 'Bilal Khan', 'STD-5001' => 'Bilal Khan', 'ROLL-99' => 'Usman Tariq'] as $search => $expected) {
            $unexpected = $expected === 'Bilal Khan' ? 'Usman Tariq' : 'Bilal Khan';

            $this->get(route('academics.groups', $this->madrassaGroup(['search' => $search])))
                ->assertOk()
                ->assertSee($expected, false)
                ->assertDontSee($unexpected, false);
        }
    }

    public function test_the_group_paginates_and_keeps_its_parameters(): void
    {
        foreach (range(1, 25) as $i) {
            $this->enroll($this->student(['full_name' => "Group Student {$i}"]));
        }

        // A record outside the group, to prove the filter holds on page 2.
        $outsider = $this->student(['full_name' => 'Outside The Group']);
        $this->enroll($outsider, $this->schoolTrack());

        $response = $this->get(route('academics.groups', $this->madrassaGroup()))->assertOk();
        $response->assertSee('Showing 1 to 20 of 25 students', false);

        $content = $response->getContent();
        $this->assertStringContainsString('track=Madrassa', $content);
        $this->assertStringContainsString('session='.$this->session2026->id, $content);
        $this->assertStringContainsString('page=2', $content);

        $this->get(route('academics.groups', $this->madrassaGroup(['page' => 2])))
            ->assertOk()
            ->assertSee('Showing 21 to 25 of 25 students', false)
            ->assertDontSee('Outside The Group', false);
    }

    public function test_the_group_statistics_are_correct(): void
    {
        // Three active, one completed, one left, all in the same group.
        foreach (range(1, 3) as $i) {
            $this->enroll($this->student(['full_name' => "Active {$i}"]));
        }
        $this->enroll($this->student(['full_name' => 'Completed One']), [
            'status' => 'Completed', 'end_date' => '2027-03-31',
        ]);
        $this->enroll($this->student(['full_name' => 'Left One']), [
            'status' => 'Left', 'end_date' => '2026-09-01',
        ]);

        // And one in a different group entirely, which must not be counted.
        $this->enroll($this->student(['full_name' => 'Other Group']), $this->schoolTrack());

        $statistics = $this->get(route('academics.groups', $this->madrassaGroup()))
            ->assertOk()
            ->viewData('statistics');

        $this->assertSame(5, $statistics['total']);
        $this->assertSame(3, $statistics['active']);
        $this->assertSame(1, $statistics['completed']);
        $this->assertSame(1, $statistics['left']);
    }

    public function test_the_group_lists_active_records_by_default_and_others_on_request(): void
    {
        $this->enroll($this->student(['full_name' => 'Active Member']));
        $this->enroll($this->student(['full_name' => 'Completed Member']), [
            'status' => 'Completed', 'end_date' => '2027-03-31',
        ]);

        // Default: the current members.
        $this->get(route('academics.groups', $this->madrassaGroup()))
            ->assertOk()
            ->assertSee('Active Member', false)
            ->assertDontSee('Completed Member', false);

        // The other statuses stay reachable.
        $this->get(route('academics.groups', $this->madrassaGroup(['status' => 'Completed'])))
            ->assertOk()
            ->assertSee('Completed Member', false)
            ->assertDontSee('Active Member', false);

        $this->get(route('academics.groups', $this->madrassaGroup(['status' => 'all'])))
            ->assertOk()
            ->assertSee('Active Member', false)
            ->assertSee('Completed Member', false);
    }

    public function test_the_group_actions_use_the_existing_routes(): void
    {
        $student = $this->student();
        $record = $this->enroll($student);

        $this->get(route('academics.groups', $this->madrassaGroup()))
            ->assertOk()
            ->assertSee('href="'.route('academics.enrollments.show', $record->id).'"', false)
            ->assertSee('href="'.route('students.show', $student->id).'"', false)
            ->assertSee('href="'.route('students.promote', $student->id).'"', false)
            ->assertSee('View Academic Record', false);
    }

    /* ---------------------------------------------------------------- */
    /* Dual track                                                       */
    /* ---------------------------------------------------------------- */

    public function test_a_dual_track_student_appears_once_in_each_group(): void
    {
        $student = $this->student([
            'full_name' => 'Dual Track Student',
            'registration_number' => 'STD-4001',
            'student_type' => 'Hifz + School',
        ]);
        $this->enroll($student);
        $this->enroll($student, $this->schoolTrack());

        // Once in the madrassa group.
        $madrassa = $this->get(route('academics.groups', $this->madrassaGroup()))->assertOk();
        $madrassa->assertSee('Dual Track Student', false);
        $this->assertSame(1, substr_count($madrassa->getContent(), 'STD-4001</td>'));

        // Once in the school group.
        $school = $this->get(route('academics.groups', [
            'session' => $this->session2026->id,
            'track' => 'School',
            'department' => $this->school->id,
            'class' => $this->primary->id,
            'section' => $this->primaryB->id,
        ]))->assertOk();
        $school->assertSee('Dual Track Student', false);
        $this->assertSame(1, substr_count($school->getContent(), 'STD-4001</td>'));
    }

    public function test_a_group_counts_a_dual_track_student_once(): void
    {
        $student = $this->student(['student_type' => 'Hifz + School']);
        $this->enroll($student);
        $this->enroll($student, $this->schoolTrack());

        $madrassaStats = $this->get(route('academics.groups', $this->madrassaGroup()))
            ->assertOk()->viewData('statistics');

        $this->assertSame(1, $madrassaStats['total'], 'The other track must not be counted here');
        $this->assertSame(1, $madrassaStats['active']);
    }

    public function test_a_student_repeating_a_class_counts_once_in_a_cross_session_group(): void
    {
        // The same class in two sessions: two rows, one student.
        $student = $this->student(['full_name' => 'Repeated The Year']);
        $this->enroll($student, ['status' => 'Completed', 'end_date' => '2027-03-31']);
        $this->enroll($student, [
            'academic_session_id' => $this->session2027->id,
            'start_date' => '2027-04-01',
        ]);

        // A group without a session spans both rows.
        $statistics = $this->get(route('academics.groups', [
            'track' => 'Madrassa',
            'class' => $this->nazra->id,
        ]))->assertOk()->viewData('statistics');

        $this->assertSame(1, $statistics['total'], 'Statistics count students, not rows');
        $this->assertSame(1, $statistics['active']);
        $this->assertSame(1, $statistics['completed']);
    }

    /* ---------------------------------------------------------------- */
    /* Listing integration                                              */
    /* ---------------------------------------------------------------- */

    public function test_the_listing_offers_the_record_and_group_actions(): void
    {
        $student = $this->student();
        $record = $this->enroll($student);

        $this->get(route('academics.index'))
            ->assertOk()
            ->assertSee('href="'.route('academics.enrollments.show', $record->id).'"', false)
            ->assertSee('View Academic Record', false)
            ->assertSee('View Group', false)
            // The group link carries this record's own group.
            ->assertSee('track=Madrassa', false);
    }

    /* ---------------------------------------------------------------- */
    /* Performance                                                      */
    /* ---------------------------------------------------------------- */

    public function test_the_group_page_does_not_run_a_query_per_row(): void
    {
        foreach (range(1, 15) as $i) {
            $this->enroll($this->student(['full_name' => "Student {$i}"]));
        }

        \DB::enableQueryLog();
        $this->get(route('academics.groups', $this->madrassaGroup()))->assertOk();
        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        // Records + count + five eager loads + four statistics + the four
        // heading lookups. Bounded, and unrelated to the number of rows.
        $this->assertLessThan(25, $queries, "The group page ran {$queries} queries; it must not query per row");
    }

    public function test_the_academic_record_page_does_not_run_a_query_per_history_row(): void
    {
        $student = $this->student();
        $record = $this->enroll($student);

        foreach ([$this->session2027] as $session) {
            $this->enroll($student, [
                'academic_session_id' => $session->id,
                'academic_class_id' => $this->hifzClass->id,
                'section_id' => null,
                'start_date' => '2027-04-01',
                'status' => 'Completed',
                'end_date' => '2028-03-31',
            ]);
        }

        \DB::enableQueryLog();
        $this->get(route('academics.enrollments.show', $record->id))->assertOk();
        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertLessThan(25, $queries, "The record page ran {$queries} queries");
    }
}
