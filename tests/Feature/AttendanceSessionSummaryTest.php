<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentAttendance;
use App\Models\User;
use Database\Seeders\AdmissionDepartmentClassSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the session-wide attendance summary.
 *
 * The number this page adds is the attendance opportunity: what the paper
 * registers could hold, counted from the session and enrollment dates
 * rather than from the rows on file. Most of what follows is about keeping
 * that honest — weekends are never an opportunity, a student promoted
 * mid-session is one student, and untranscribed attendance is never an
 * absence.
 */
class AttendanceSessionSummaryTest extends TestCase
{
    use RefreshDatabase;

    /** A short session, so opportunity counts stay checkable by hand. */
    private const SESSION_START = '2026-08-01';

    private const SESSION_END = '2026-10-31';

    private const MONDAY = '2026-08-03';

    private const TUESDAY = '2026-08-04';

    private const WEDNESDAY = '2026-08-05';

    private const SATURDAY = '2026-08-01';

    private const SUNDAY = '2026-08-02';

    private AcademicSession $session;

    private AcademicSession $otherSession;

    private Department $hifz;

    private Department $school;

    private AcademicClass $hifzClass;

    private AcademicClass $nazra;

    private AcademicClass $primary;

    private Section $hifzA;

    private Section $hifzB;

    private Section $primaryB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->seed(AdmissionDepartmentClassSeeder::class);

        // August, September and October 2026: 21 + 22 + 22 = 65 weekdays.
        $this->session = AcademicSession::create([
            'name' => '2026-2027', 'start_date' => self::SESSION_START, 'end_date' => self::SESSION_END,
            'is_current' => true, 'status' => true,
        ]);
        $this->otherSession = AcademicSession::create([
            'name' => '2027-2028', 'start_date' => '2027-08-01', 'end_date' => '2027-10-31',
            'status' => true,
        ]);

        $this->hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->school = Department::where('name', 'School')->firstOrFail();

        $this->hifzClass = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Hifz')->firstOrFail();
        $this->nazra = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Nazra')->firstOrFail();
        $this->primary = AcademicClass::where('department_id', $this->school->id)->where('name', 'Primary Section')->firstOrFail();

        $this->hifzA = $this->section('Hifz-A', $this->hifzClass);
        $this->hifzB = $this->section('Hifz-B', $this->hifzClass);
        $this->primaryB = $this->section('Primary-B', $this->primary);
    }

    /* ---------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------- */

    private function section(string $name, AcademicClass $class): Section
    {
        return Section::create([
            'name' => $name,
            'code' => strtoupper(str_replace('-', '', $name)),
            'academic_class_id' => $class->id,
            'status' => true,
        ]);
    }

    private function student(string $name = 'Ahmed Ali'): Student
    {
        static $sequence = 0;
        $sequence++;

        return Student::create([
            'registration_number' => 'STD-2026-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'roll_number' => str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'full_name' => $name,
            'father_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03007654321',
            'admission_date' => self::SESSION_START,
            'academic_session_id' => $this->session->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'student_status' => 'Active',
            'student_type' => 'Hifz',
            'resident_type' => 'Local Resident',
        ]);
    }

    private function madrassaEnrollment(?Student $student = null, array $overrides = []): StudentAcademicEnrollment
    {
        $student ??= $this->student();

        return $student->academicEnrollments()->create(array_merge([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'start_date' => self::SESSION_START,
            'status' => 'Active',
        ], $overrides));
    }

    private function schoolEnrollment(?Student $student = null, array $overrides = []): StudentAcademicEnrollment
    {
        $student ??= $this->student();

        return $student->academicEnrollments()->create(array_merge([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->primaryB->id,
            'start_date' => self::SESSION_START,
            'status' => 'Active',
        ], $overrides));
    }

    private function record(
        StudentAcademicEnrollment $enrollment,
        string $date,
        string $period = 'Morning',
        string $status = 'Present',
        ?string $reason = null
    ): StudentAttendance {
        return StudentAttendance::create([
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => $date,
            'attendance_period' => $period,
            'status' => $status,
            'absence_reason' => $reason,
        ]);
    }

    /**
     * @param  array<int, string>  $dates
     */
    private function recordMany(StudentAcademicEnrollment $enrollment, array $dates, string $period, string $status): void
    {
        foreach ($dates as $date) {
            $this->record($enrollment, $date, $period, $status, $status === 'Absent' ? 'Sick' : null);
        }
    }

    /**
     * Open the session summary.
     */
    private function summary(array $filters = [])
    {
        return $this->get(route('attendance.session-summary', array_merge([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
        ], $filters)));
    }

    /**
     * The summary row for one student.
     *
     * @return array<string, mixed>|null
     */
    private function rowFor($response, Student $student): ?array
    {
        return collect($response->viewData('students')->items())
            ->firstWhere('student_id', $student->id);
    }

    /**
     * Export and read the CSV back as rows.
     *
     * @return array<int, array<int, string>>
     */
    private function exportRows(array $filters = []): array
    {
        $response = $this->get(route('attendance.session-summary.export', array_merge([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
        ], $filters)))->assertOk();

        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent());

        $rows = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /* ---------------------------------------------------------------- */
    /* Access */
    /* ---------------------------------------------------------------- */

    public function test_a_guest_cannot_reach_the_session_summary_or_its_outputs(): void
    {
        auth()->logout();

        $this->get(route('attendance.session-summary'))->assertRedirect(route('login'));
        $this->get(route('attendance.session-summary.print'))->assertRedirect(route('login'));
        $this->get(route('attendance.session-summary.export'))->assertRedirect(route('login'));
    }

    public function test_the_session_summary_loads(): void
    {
        $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $this->summary()
            ->assertOk()
            ->assertSee('Session Attendance Summary')
            ->assertSee('Attendance Opportunities')
            ->assertSee('2026-2027')
            ->assertSee('Ahmed Ali');
    }

    public function test_the_summary_has_no_all_tracks_option(): void
    {
        $response = $this->summary(['academic_track' => 'nonsense'])->assertOk();

        $this->assertContains(
            $response->viewData('filters')['academic_track'],
            StudentAcademicEnrollment::ACADEMIC_TRACKS
        );
    }

    /* ---------------------------------------------------------------- */
    /* Filters and search */
    /* ---------------------------------------------------------------- */

    public function test_the_academic_session_filter_works(): void
    {
        $student = $this->student('Ahmed Ali');
        $this->madrassaEnrollment($student, ['status' => 'Completed', 'end_date' => self::SESSION_END]);
        $this->madrassaEnrollment($student, [
            'academic_session_id' => $this->otherSession->id,
            'start_date' => '2027-08-01',
        ]);

        $this->assertSame(1, $this->summary()->viewData('totals')['students']);
        $this->assertSame(1, $this->summary(['academic_session_id' => $this->otherSession->id])->viewData('totals')['students']);

        // The session decides the window, so the two never share days.
        $this->assertSame(
            '2026-08-01',
            $this->summary()->viewData('sessionStart')->format('Y-m-d')
        );
    }

    public function test_the_track_filter_works(): void
    {
        $madrassa = $this->madrassaEnrollment($this->student('Madrassa Student'));
        $school = $this->schoolEnrollment($this->student('School Student'));

        $this->assertSame(
            ['Madrassa Student'],
            collect($this->summary(['academic_track' => 'Madrassa'])->viewData('students')->items())->pluck('full_name')->all()
        );
        $this->assertSame(
            ['School Student'],
            collect($this->summary(['academic_track' => 'School'])->viewData('students')->items())->pluck('full_name')->all()
        );

        $this->assertNotNull($madrassa);
        $this->assertNotNull($school);
    }

    public function test_the_department_class_and_section_filters_work(): void
    {
        $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $this->madrassaEnrollment($this->student('Bilal Khan'), ['section_id' => $this->hifzB->id]);
        $this->madrassaEnrollment($this->student('Usman Tariq'), [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $this->assertSame(3, $this->summary(['department_id' => $this->hifz->id])->viewData('totals')['students']);
        $this->assertSame(2, $this->summary(['academic_class_id' => $this->hifzClass->id])->viewData('totals')['students']);
        $this->assertSame(1, $this->summary([
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
        ])->viewData('totals')['students']);

        // A class from another department is an AND, so it matches nothing.
        $this->assertSame(0, $this->summary([
            'department_id' => $this->school->id,
            'academic_class_id' => $this->hifzClass->id,
        ])->viewData('totals')['students']);
    }

    public function test_the_search_matches_name_registration_and_roll_number(): void
    {
        $ali = $this->madrassaEnrollment($this->student('Ali Raza'));
        $ahmed = $this->madrassaEnrollment($this->student('Ahmed Khan'));

        $names = fn (array $filters) => collect($this->summary($filters)->viewData('students')->items())->pluck('full_name')->all();

        $this->assertSame(['Ali Raza'], $names(['search' => 'Ali Raza']));
        $this->assertSame(['Ali Raza'], $names(['search' => $ali->student->registration_number]));
        $this->assertSame(['Ahmed Khan'], $names(['search' => $ahmed->student->roll_number]));

        // The cards follow the search, not the whole session.
        $this->assertSame(1, $this->summary(['search' => 'Ali Raza'])->viewData('totals')['students']);
    }

    /* ---------------------------------------------------------------- */
    /* Attendance opportunities */
    /* ---------------------------------------------------------------- */

    public function test_school_has_one_opportunity_per_teaching_day(): void
    {
        $enrollment = $this->schoolEnrollment($this->student('Ahmed Ali'));

        $row = $this->rowFor($this->summary(['academic_track' => 'School'])->assertOk(), $enrollment->student);

        // 21 + 22 + 22 weekdays across August, September and October.
        $this->assertSame(65, $row['teaching_days']);
        $this->assertSame(65, $row['opportunities']);
    }

    public function test_madrassa_has_three_opportunities_per_teaching_day(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $row = $this->rowFor($this->summary()->assertOk(), $enrollment->student);

        $this->assertSame(65, $row['teaching_days']);
        $this->assertSame(195, $row['opportunities']);
    }

    public function test_saturdays_and_sundays_are_never_an_opportunity(): void
    {
        // A session made of one weekend holds nothing to mark.
        $weekend = AcademicSession::create([
            'name' => 'Weekend only', 'start_date' => self::SATURDAY, 'end_date' => self::SUNDAY, 'status' => true,
        ]);

        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'), [
            'academic_session_id' => $weekend->id,
            'start_date' => self::SATURDAY,
        ]);

        $response = $this->summary(['academic_session_id' => $weekend->id])->assertOk();
        $row = $this->rowFor($response, $enrollment->student);

        $this->assertSame(0, $row['teaching_days']);
        $this->assertSame(0, $row['opportunities']);
        $this->assertSame(0, $response->viewData('totals')['session_teaching_days']);

        // And a session that adds the Monday holds exactly one day.
        $withMonday = AcademicSession::create([
            'name' => 'Weekend plus Monday', 'start_date' => self::SATURDAY, 'end_date' => self::MONDAY, 'status' => true,
        ]);
        $this->madrassaEnrollment($this->student('Hassan Raza'), [
            'academic_session_id' => $withMonday->id,
            'start_date' => self::SATURDAY,
        ]);

        $this->assertSame(1, $this->summary(['academic_session_id' => $withMonday->id])->viewData('totals')['session_teaching_days']);
    }

    public function test_the_session_start_and_end_dates_bound_the_opportunities(): void
    {
        // An enrollment that opens before the session and never closes.
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'), ['start_date' => '2026-01-01']);

        $row = $this->rowFor($this->summary()->assertOk(), $enrollment->student);

        // Still only the session's own 65 teaching days.
        $this->assertSame(65, $row['teaching_days']);
    }

    public function test_the_enrollment_start_date_is_respected(): void
    {
        // Joined on 10 September: August is not theirs.
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'), ['start_date' => '2026-09-10']);

        $row = $this->rowFor($this->summary()->assertOk(), $enrollment->student);

        // 15 weekdays left in September plus October's 22.
        $this->assertSame(37, $row['teaching_days']);
        $this->assertSame(111, $row['opportunities']);
    }

    public function test_the_enrollment_end_date_is_respected(): void
    {
        // Left on 15 September.
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'), [
            'end_date' => '2026-09-15',
            'status' => 'Left',
        ]);

        $row = $this->rowFor($this->summary()->assertOk(), $enrollment->student);

        // August's 21 weekdays plus the 11 in September up to the 15th.
        $this->assertSame(32, $row['teaching_days']);
        $this->assertSame(96, $row['opportunities']);
    }

    public function test_an_active_enrollment_runs_to_the_end_of_the_session(): void
    {
        $active = $this->madrassaEnrollment($this->student('Active Student'));
        $closed = $this->madrassaEnrollment($this->student('Closed Student'), [
            'end_date' => self::SESSION_END,
            'status' => 'Completed',
        ]);

        $response = $this->summary()->assertOk();

        // No end date and an end date on the last day come to the same
        // thing: the session is the boundary either way.
        $this->assertSame(65, $this->rowFor($response, $active->student)['teaching_days']);
        $this->assertSame(65, $this->rowFor($response, $closed->student)['teaching_days']);
    }

    /* ---------------------------------------------------------------- */
    /* Recorded, unrecorded and percentage */
    /* ---------------------------------------------------------------- */

    public function test_recorded_present_absent_and_unrecorded_are_counted_correctly(): void
    {
        $enrollment = $this->schoolEnrollment($this->student('Ahmed Ali'));

        // Twenty present and five absent out of sixty-five opportunities.
        $weekdays = collect(range(0, 40))
            ->map(fn ($offset) => Carbon::parse(self::SESSION_START)->addDays($offset)->format('Y-m-d'))
            ->filter(fn ($date) => StudentAttendance::isAttendanceDay($date))
            ->values();

        $this->recordMany($enrollment, $weekdays->take(20)->all(), 'Morning', 'Present');
        $this->recordMany($enrollment, $weekdays->slice(20, 5)->all(), 'Morning', 'Absent');

        $row = $this->rowFor($this->summary(['academic_track' => 'School'])->assertOk(), $enrollment->student);

        $this->assertSame(20, $row['present']);
        $this->assertSame(5, $row['absent']);
        $this->assertSame(25, $row['recorded']);
        $this->assertSame(65, $row['opportunities']);
        // The forty still on paper, which is not the same as forty absences.
        $this->assertSame(40, $row['unrecorded']);
        $this->assertSame(80.0, $row['percentage']);
    }

    public function test_untranscribed_attendance_is_never_treated_as_absence(): void
    {
        $enrollment = $this->schoolEnrollment($this->student('Ahmed Ali'));

        // One day marked out of a whole session.
        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');

        $row = $this->rowFor($this->summary(['academic_track' => 'School'])->assertOk(), $enrollment->student);

        $this->assertSame(1, $row['recorded']);
        $this->assertSame(64, $row['unrecorded']);
        $this->assertSame(0, $row['absent']);
        // Present over recorded, not present over opportunities.
        $this->assertSame(100.0, $row['percentage']);
        $this->assertNotSame(round(1 / 65 * 100, 2), $row['percentage']);
    }

    public function test_a_student_with_nothing_recorded_shows_not_available(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $response = $this->summary()->assertOk();
        $row = $this->rowFor($response, $enrollment->student);

        $this->assertSame(0, $row['recorded']);
        $this->assertSame(195, $row['unrecorded']);
        $this->assertNull($row['percentage']);
        $this->assertNull($response->viewData('totals')['percentage']);

        $response->assertSee('N/A');
    }

    public function test_the_overall_percentage_comes_from_aggregate_totals(): void
    {
        $heavy = $this->schoolEnrollment($this->student('Heavy Records'));
        $light = $this->schoolEnrollment($this->student('Light Records'));

        $weekdays = collect(range(0, 40))
            ->map(fn ($offset) => Carbon::parse(self::SESSION_START)->addDays($offset)->format('Y-m-d'))
            ->filter(fn ($date) => StudentAttendance::isAttendanceDay($date))
            ->values();

        // Nine present of ten, against one absent of one.
        $this->recordMany($heavy, $weekdays->take(9)->all(), 'Morning', 'Present');
        $this->recordMany($heavy, $weekdays->slice(9, 1)->all(), 'Morning', 'Absent');
        $this->recordMany($light, $weekdays->take(1)->all(), 'Morning', 'Absent');

        $totals = $this->summary(['academic_track' => 'School'])->assertOk()->viewData('totals');

        // 9 present of 11 recorded is 81.82%. Averaging the two students'
        // own percentages would have given 45%.
        $this->assertSame(9, $totals['present']);
        $this->assertSame(2, $totals['absent']);
        $this->assertSame(11, $totals['recorded']);
        $this->assertSame(81.82, $totals['percentage']);
    }

    /* ---------------------------------------------------------------- */
    /* Monthly breakdown */
    /* ---------------------------------------------------------------- */

    public function test_the_monthly_breakdown_covers_only_the_months_the_session_touches(): void
    {
        $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $months = $this->summary()->assertOk()->viewData('months');

        $this->assertSame(['Aug 2026', 'Sep 2026', 'Oct 2026'], array_column($months, 'label'));
        $this->assertSame([21, 22, 22], array_column($months, 'session_teaching_days'));
    }

    public function test_a_partial_first_and_final_month_count_only_the_overlap(): void
    {
        $partial = AcademicSession::create([
            'name' => 'Partial', 'start_date' => '2026-08-20', 'end_date' => '2026-10-09', 'status' => true,
        ]);

        $this->madrassaEnrollment($this->student('Ahmed Ali'), [
            'academic_session_id' => $partial->id,
            'start_date' => '2026-08-20',
        ]);

        $months = $this->summary(['academic_session_id' => $partial->id])->assertOk()->viewData('months');

        // 20 to 31 August is 8 weekdays; 1 to 9 October is 7.
        $this->assertSame(['Aug 2026', 'Sep 2026', 'Oct 2026'], array_column($months, 'label'));
        $this->assertSame([8, 22, 7], array_column($months, 'session_teaching_days'));
    }

    public function test_monthly_opportunities_follow_the_track(): void
    {
        $this->madrassaEnrollment($this->student('Madrassa Student'));
        $this->schoolEnrollment($this->student('School Student'));

        $madrassa = $this->summary()->assertOk()->viewData('months');
        $school = $this->summary(['academic_track' => 'School'])->assertOk()->viewData('months');

        // One student on each track, so the month reads as days times
        // registers.
        $this->assertSame(21 * 3, $madrassa[0]['opportunities']);
        $this->assertSame(21, $school[0]['opportunities']);
    }

    public function test_the_monthly_breakdown_counts_what_was_recorded_in_each_month(): void
    {
        $enrollment = $this->schoolEnrollment($this->student('Ahmed Ali'));

        $this->recordMany($enrollment, [self::MONDAY, self::TUESDAY], 'Morning', 'Present');
        $this->record($enrollment, self::WEDNESDAY, 'Morning', 'Absent', 'Sick');
        $this->recordMany($enrollment, ['2026-09-07', '2026-09-08'], 'Morning', 'Present');

        $months = collect($this->summary(['academic_track' => 'School'])->assertOk()->viewData('months'))->keyBy('label');

        $this->assertSame(['present' => 2, 'absent' => 1, 'recorded' => 3], collect($months['Aug 2026'])->only(['present', 'absent', 'recorded'])->all());
        $this->assertSame(66.67, $months['Aug 2026']['percentage']);

        $this->assertSame(['present' => 2, 'absent' => 0, 'recorded' => 2], collect($months['Sep 2026'])->only(['present', 'absent', 'recorded'])->all());
        $this->assertSame(100.0, $months['Sep 2026']['percentage']);

        // Nothing entered for October yet, which is not nought percent.
        $this->assertSame(0, $months['Oct 2026']['recorded']);
        $this->assertNull($months['Oct 2026']['percentage']);
    }

    /* ---------------------------------------------------------------- */
    /* Promotion inside a session */
    /* ---------------------------------------------------------------- */

    public function test_a_student_cannot_hold_two_enrollments_on_one_track_in_one_session(): void
    {
        $student = $this->student('Ahmed Ali');
        $this->madrassaEnrollment($student, ['end_date' => '2026-09-15', 'status' => 'Completed']);

        // The academic module allows one enrollment per student per session
        // per track, so a promotion always moves the student into another
        // session. A second madrassa row in this session is refused by the
        // database, which is why one student is always one row here.
        $this->expectException(UniqueConstraintViolationException::class);

        $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
            'start_date' => '2026-09-16',
        ]);
    }

    public function test_a_student_appears_once_per_session_and_track(): void
    {
        $student = $this->student('Ahmed Ali');
        $enrollment = $this->madrassaEnrollment($student);

        $this->recordMany($enrollment, [self::MONDAY, self::TUESDAY], 'Morning', 'Present');
        $this->record($enrollment, self::WEDNESDAY, 'Morning', 'Absent', 'Sick');

        $response = $this->summary()->assertOk();

        $this->assertSame(1, $response->viewData('totals')['students']);
        $this->assertCount(1, $response->viewData('students')->items());

        $row = $this->rowFor($response, $student);

        $this->assertSame(1, $row['placements']);
        $this->assertSame(65, $row['teaching_days']);
        $this->assertSame(2, $row['present']);
        $this->assertSame(1, $row['absent']);
    }

    public function test_a_promotion_into_the_next_session_keeps_each_session_on_its_own(): void
    {
        $student = $this->student('Ahmed Ali');

        // The placement the student sat this session in.
        $first = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
            'end_date' => self::SESSION_END,
            'status' => 'Completed',
        ]);

        // Promoted into the next session, which is where the academic module
        // puts a promotion.
        $second = $student->academicEnrollments()->create([
            'academic_session_id' => $this->otherSession->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'start_date' => '2027-08-01',
            'status' => 'Active',
        ]);

        $this->recordMany($first, [self::MONDAY, self::TUESDAY], 'Morning', 'Present');
        $this->record($second, '2027-08-02', 'Morning', 'Absent', 'Sick');

        // This session reports the class it was actually sat in, and only
        // its own attendance.
        $thisSession = $this->rowFor($this->summary()->assertOk(), $student);

        $this->assertSame($this->nazra->id, $thisSession['academic_class_id']);
        $this->assertSame(2, $thisSession['present']);
        $this->assertSame(0, $thisSession['absent']);

        // The next session reports the new class, and only its attendance.
        $nextSession = $this->rowFor(
            $this->summary(['academic_session_id' => $this->otherSession->id])->assertOk(),
            $student
        );

        $this->assertSame($this->hifzClass->id, $nextSession['academic_class_id']);
        $this->assertSame(0, $nextSession['present']);
        $this->assertSame(1, $nextSession['absent']);
    }

    public function test_a_class_filter_only_includes_the_matching_placement(): void
    {
        $inNazra = $this->madrassaEnrollment($this->student('Nazra Student'), [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);
        $inHifz = $this->madrassaEnrollment($this->student('Hifz Student'));

        $this->recordMany($inNazra, [self::MONDAY, self::TUESDAY], 'Morning', 'Present');
        $this->record($inHifz, self::MONDAY, 'Morning', 'Absent', 'Sick');

        $nazra = $this->summary(['academic_class_id' => $this->nazra->id])->assertOk();
        $hifz = $this->summary(['academic_class_id' => $this->hifzClass->id])->assertOk();

        // Each filter reports its own placement and nothing else.
        $this->assertSame(['present' => 2, 'absent' => 0], collect($nazra->viewData('totals'))->only(['present', 'absent'])->all());
        $this->assertSame(['present' => 0, 'absent' => 1], collect($hifz->viewData('totals'))->only(['present', 'absent'])->all());

        $this->assertNull($this->rowFor($nazra, $inHifz->student));
        $this->assertNull($this->rowFor($hifz, $inNazra->student));
    }

    /* ---------------------------------------------------------------- */
    /* Dual track */
    /* ---------------------------------------------------------------- */

    public function test_a_dual_track_student_is_reported_separately_on_each_track(): void
    {
        $student = $this->student('Ahmed Ali');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->recordMany($madrassa, [self::MONDAY, self::TUESDAY], 'Morning', 'Present');
        $this->record($madrassa, self::MONDAY, 'Afternoon', 'Absent', 'Sick');
        $this->record($madrassa, self::MONDAY, 'Evening', 'Present');
        $this->recordMany($school, [self::MONDAY, self::TUESDAY, self::WEDNESDAY], 'Morning', 'Present');

        $madrassaRow = $this->rowFor($this->summary()->assertOk(), $student);
        $schoolRow = $this->rowFor($this->summary(['academic_track' => 'School'])->assertOk(), $student);

        // Madrassa: three registers a day, and only its own marks.
        $this->assertSame(195, $madrassaRow['opportunities']);
        $this->assertSame(3, $madrassaRow['present']);
        $this->assertSame(1, $madrassaRow['absent']);

        // School: one register a day, and only its own marks.
        $this->assertSame(65, $schoolRow['opportunities']);
        $this->assertSame(3, $schoolRow['present']);
        $this->assertSame(0, $schoolRow['absent']);

        // Never one combined figure.
        $this->assertSame(4, $this->summary()->viewData('totals')['recorded']);
        $this->assertSame(3, $this->summary(['academic_track' => 'School'])->viewData('totals')['recorded']);
    }

    public function test_a_school_summary_ignores_stray_afternoon_and_evening_rows(): void
    {
        $enrollment = $this->schoolEnrollment($this->student('Ahmed Ali'));

        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        // Rows the entry sheet would refuse, written straight to the table.
        $this->record($enrollment, self::MONDAY, 'Afternoon', 'Present');
        $this->record($enrollment, self::MONDAY, 'Evening', 'Absent', 'Sick');

        $row = $this->rowFor($this->summary(['academic_track' => 'School'])->assertOk(), $enrollment->student);

        $this->assertSame(1, $row['recorded']);
        $this->assertSame(0, $row['absent']);
    }

    /* ---------------------------------------------------------------- */
    /* Print and export */
    /* ---------------------------------------------------------------- */

    public function test_the_printed_summary_respects_the_filters(): void
    {
        $inGroup = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $this->madrassaEnrollment($this->student('Bilal Khan'), ['section_id' => $this->hifzB->id]);

        $this->recordMany($inGroup, [self::MONDAY, self::TUESDAY], 'Morning', 'Present');

        $response = $this->get(route('attendance.session-summary.print', [
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
        ]))->assertOk();

        $this->assertCount(1, $response->viewData('students'));
        $response->assertSee('Ahmed Ali')
            ->assertDontSee('Bilal Khan')
            ->assertSee('Session Attendance Summary')
            ->assertSee('Monthly Breakdown')
            // The screen chrome is not on the printed page.
            ->assertDontSee('Generate Summary');

        $this->assertStringContainsString('print:hidden', $response->getContent());
    }

    public function test_the_printed_summary_covers_every_matching_student(): void
    {
        collect(range(1, 30))->each(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        $students = $this->get(route('attendance.session-summary.print', [
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
        ]))->assertOk()->viewData('students');

        $this->assertCount(30, $students);
    }

    public function test_the_export_has_the_expected_columns(): void
    {
        $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $this->assertSame([
            'Student Name',
            'Registration Number',
            'Roll Number',
            'Academic Session',
            'Department',
            'Class',
            'Section',
            'Track',
            'Teaching Days',
            'Attendance Opportunities',
            'Recorded',
            'Unrecorded',
            'Present',
            'Absent',
            'Attendance Percentage',
        ], $this->exportRows()[0]);
    }

    public function test_the_export_carries_the_right_figures(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $this->recordMany($enrollment, [self::MONDAY, self::TUESDAY], 'Morning', 'Present');
        $this->record($enrollment, self::WEDNESDAY, 'Morning', 'Absent', 'Sick');

        $row = $this->exportRows()[1];

        $this->assertSame('Ahmed Ali', $row[0]);
        $this->assertSame('2026-2027', $row[3]);
        $this->assertSame('Hifz', $row[4]);
        $this->assertSame('Hifz', $row[5]);
        $this->assertSame('Hifz-A', $row[6]);
        $this->assertSame('Madrassa', $row[7]);
        $this->assertSame('65', $row[8]);
        $this->assertSame('195', $row[9]);
        $this->assertSame('3', $row[10]);
        $this->assertSame('192', $row[11]);
        $this->assertSame('2', $row[12]);
        $this->assertSame('1', $row[13]);
        $this->assertSame('66.67', $row[14]);
    }

    public function test_the_export_contains_every_matching_student_and_respects_filters(): void
    {
        collect(range(1, 30))->each(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));
        $this->madrassaEnrollment($this->student('Other Section'), ['section_id' => $this->hifzB->id]);
        $this->schoolEnrollment($this->student('School Student'));

        // Thirty-one madrassa students plus the header, where the screen
        // pages at twenty-five.
        $this->assertCount(32, $this->exportRows());

        // Filters narrow the export exactly as they narrow the page.
        $this->assertCount(31, $this->exportRows(['section_id' => $this->hifzA->id, 'academic_class_id' => $this->hifzClass->id]));
        $this->assertCount(2, $this->exportRows(['academic_track' => 'School']));
        $this->assertCount(2, $this->exportRows(['search' => 'Student 7']));
    }

    public function test_the_export_includes_students_with_no_attendance(): void
    {
        $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $row = $this->exportRows()[1];

        $this->assertSame('195', $row[9]);
        $this->assertSame('0', $row[10]);
        $this->assertSame('195', $row[11]);
        $this->assertSame('0', $row[12]);
        $this->assertSame('0', $row[13]);
        $this->assertSame('N/A', $row[14]);
    }

    /* ---------------------------------------------------------------- */
    /* Safety and performance */
    /* ---------------------------------------------------------------- */

    public function test_the_summary_never_changes_the_attendance_records(): void
    {
        $student = $this->student('Ahmed Ali');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->record($madrassa, self::MONDAY, 'Morning', 'Present');
        $this->record($madrassa, self::TUESDAY, 'Afternoon', 'Absent', 'Sick');
        $this->record($school, self::MONDAY, 'Morning', 'Present');

        $before = DB::table('student_attendances')->orderBy('id')->get()->toArray();

        $this->summary()->assertOk();
        $this->summary(['academic_track' => 'School'])->assertOk();
        $this->get(route('attendance.session-summary.print', [
            'academic_session_id' => $this->session->id, 'academic_track' => 'Madrassa',
        ]))->assertOk();
        $this->exportRows();

        $this->assertDatabaseCount('student_attendances', 3);
        $this->assertEquals($before, DB::table('student_attendances')->orderBy('id')->get()->toArray());
    }

    public function test_the_query_count_does_not_grow_per_student(): void
    {
        collect(range(1, 3))->each(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        $small = $this->queriesToOpen();

        collect(range(4, 30))->each(function ($index) {
            $enrollment = $this->madrassaEnrollment($this->student("Student {$index}"));

            foreach ([self::MONDAY, self::TUESDAY, self::WEDNESDAY] as $date) {
                foreach (['Morning', 'Afternoon', 'Evening'] as $period) {
                    $this->record($enrollment, $date, $period, 'Present');
                }
            }
        });

        $large = $this->queriesToOpen();

        $this->assertSame(30, $this->summary()->viewData('totals')['students']);

        // Ten times the students and hundreds of attendance rows, on the
        // same number of queries.
        $this->assertLessThanOrEqual($small, $large);
        $this->assertLessThan(25, $large);
    }

    public function test_the_export_query_count_does_not_grow_per_student(): void
    {
        collect(range(1, 3))->each(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        $small = $this->queriesToExport();

        collect(range(4, 30))->each(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        $large = $this->queriesToExport();

        $this->assertLessThanOrEqual($small, $large);
        $this->assertLessThan(25, $large);
    }

    private function queriesToOpen(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->summary()->assertOk();

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    }

    private function queriesToExport(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('attendance.session-summary.export', [
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
        ]))->assertOk()->streamedContent();

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    }
}
