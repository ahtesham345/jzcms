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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the monthly attendance report.
 *
 * The report counts rows that exist. The cases that matter most are the
 * ones about what it must NOT do: turn a day nobody transcribed into a
 * present, put calendar days in the denominator, or add a dual-track
 * student's two enrollments together.
 */
class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    /** August 2026: the 1st and 2nd are the weekend, the 3rd a Monday. */
    private const MONDAY = '2026-08-03';

    private const TUESDAY = '2026-08-04';

    private const WEDNESDAY = '2026-08-05';

    private const THURSDAY = '2026-08-06';

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

        $this->session = AcademicSession::create([
            'name' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'status' => true,
        ]);
        $this->otherSession = AcademicSession::create([
            'name' => '2027-2028', 'start_date' => '2027-04-01', 'end_date' => '2028-03-31',
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

    private function student(string $name = 'Ahmed Ali', array $overrides = []): Student
    {
        static $sequence = 0;
        $sequence++;

        return Student::create(array_merge([
            'registration_number' => 'STD-2026-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'roll_number' => str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'full_name' => $name,
            'father_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03007654321',
            'admission_date' => '2026-04-01',
            'academic_session_id' => $this->session->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'student_status' => 'Active',
            'student_type' => 'Hifz',
            'resident_type' => 'Local Resident',
        ], $overrides));
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
            'start_date' => '2026-04-01',
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
            'start_date' => '2026-04-01',
            'status' => 'Active',
        ], $overrides));
    }

    /**
     * Record one attendance row, through the same model the sheet uses.
     */
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
     * Record several days at once.
     *
     * @param  array<int, string>  $dates
     */
    private function recordMany(StudentAcademicEnrollment $enrollment, array $dates, string $period, string $status): void
    {
        foreach ($dates as $date) {
            $this->record($enrollment, $date, $period, $status, $status === 'Absent' ? 'Sick' : null);
        }
    }

    /**
     * Open the report.
     */
    private function report(array $filters = [])
    {
        return $this->get(route('attendance.reports', array_merge([
            'month' => 8,
            'year' => 2026,
            'academic_track' => 'Madrassa',
        ], $filters)));
    }

    /**
     * The report row for one enrollment.
     */
    private function rowFor($response, StudentAcademicEnrollment $enrollment)
    {
        return $response->viewData('students')->firstWhere('id', $enrollment->id);
    }

    /**
     * The counts on one enrollment's report row.
     *
     * @return array{present: int, absent: int}
     */
    private function countsFor($response, StudentAcademicEnrollment $enrollment): array
    {
        $row = $this->rowFor($response, $enrollment);

        $this->assertNotNull($row, 'The enrollment was expected on the report.');

        return ['present' => (int) $row->present_count, 'absent' => (int) $row->absent_count];
    }

    /* ---------------------------------------------------------------- */
    /* Access and navigation */
    /* ---------------------------------------------------------------- */

    public function test_the_reports_page_requires_authentication(): void
    {
        auth()->logout();

        $this->get(route('attendance.reports'))->assertRedirect(route('login'));
    }

    public function test_the_reports_page_loads(): void
    {
        $this->report()
            ->assertOk()
            ->assertSee('Monthly Attendance Report')
            ->assertSee('Total Recorded')
            ->assertSee('August 2026');
    }

    public function test_the_sidebar_attendance_link_still_opens_the_monthly_entry_sheet(): void
    {
        // The sidebar is unchanged: Attendance is still the entry sheet, and
        // the report is reached from that page rather than from a second
        // sidebar module.
        $this->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Monthly Attendance Entry')
            ->assertSee('href="'.route('attendance.index').'"', false)
            ->assertSee('href="'.route('attendance.reports').'"', false);

        $this->report()->assertOk()->assertSee('href="'.route('attendance.index').'"', false);
    }

    public function test_the_report_has_no_all_tracks_option(): void
    {
        $response = $this->report(['academic_track' => 'nonsense'])->assertOk();

        // An unrecognised track falls back to a real one rather than
        // widening the report to cover both.
        $this->assertContains(
            $response->viewData('filters')['academic_track'],
            StudentAcademicEnrollment::ACADEMIC_TRACKS
        );
    }

    /* ---------------------------------------------------------------- */
    /* Filters */
    /* ---------------------------------------------------------------- */

    public function test_the_academic_session_filter_works(): void
    {
        $student = $this->student('Ahmed Ali');
        $thisYear = $this->madrassaEnrollment($student, ['status' => 'Completed']);
        $nextYear = $this->madrassaEnrollment($student, [
            'academic_session_id' => $this->otherSession->id,
            'start_date' => '2027-04-01',
        ]);

        $this->assertSame(
            [$thisYear->id],
            $this->report(['academic_session_id' => $this->session->id])->viewData('students')->pluck('id')->all()
        );
        $this->assertSame(
            [$nextYear->id],
            $this->report(['academic_session_id' => $this->otherSession->id])->viewData('students')->pluck('id')->all()
        );
    }

    public function test_the_track_filter_works(): void
    {
        $madrassa = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $school = $this->schoolEnrollment($this->student('Hassan Raza'));

        $this->assertSame([$madrassa->id], $this->report(['academic_track' => 'Madrassa'])->viewData('students')->pluck('id')->all());
        $this->assertSame([$school->id], $this->report(['academic_track' => 'School'])->viewData('students')->pluck('id')->all());
    }

    public function test_the_department_class_and_section_filters_work(): void
    {
        $inGroup = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $otherSection = $this->madrassaEnrollment($this->student('Bilal Khan'), ['section_id' => $this->hifzB->id]);
        $otherClass = $this->madrassaEnrollment($this->student('Usman Tariq'), [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        // Department: everything in Hifz.
        $this->assertCount(3, $this->report(['department_id' => $this->hifz->id])->viewData('students'));

        // Department, but a class from another department: an AND, so it
        // matches nothing rather than one filter quietly winning.
        $this->assertCount(0, $this->report([
            'department_id' => $this->school->id,
            'academic_class_id' => $this->hifzClass->id,
        ])->viewData('students'));

        // Class narrows to the two in Hifz.
        $this->assertEqualsCanonicalizing(
            [$inGroup->id, $otherSection->id],
            $this->report(['academic_class_id' => $this->hifzClass->id])->viewData('students')->pluck('id')->all()
        );
        $this->assertNotContains($otherClass->id, $this->report(['academic_class_id' => $this->hifzClass->id])->viewData('students')->pluck('id')->all());

        // Section narrows to one.
        $this->assertSame(
            [$inGroup->id],
            $this->report(['academic_class_id' => $this->hifzClass->id, 'section_id' => $this->hifzA->id])->viewData('students')->pluck('id')->all()
        );
    }

    public function test_the_month_and_year_filters_work(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->record($enrollment, self::MONDAY);
        $this->record($enrollment, '2026-09-07');
        $this->record($enrollment, '2027-08-02');

        $this->assertSame(1, $this->report(['month' => 8, 'year' => 2026])->viewData('summary')['recorded']);
        $this->assertSame(1, $this->report(['month' => 9, 'year' => 2026])->viewData('summary')['recorded']);
        $this->assertSame(1, $this->report(['month' => 8, 'year' => 2027])->viewData('summary')['recorded']);
        $this->assertSame(0, $this->report(['month' => 10, 'year' => 2026])->viewData('summary')['recorded']);
    }

    public function test_the_report_handles_month_lengths_correctly(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // The last day of each month, including a leap-year February.
        $this->record($enrollment, '2027-02-26');
        $this->record($enrollment, '2028-02-29');
        $this->record($enrollment, '2026-04-30');
        $this->record($enrollment, '2026-08-31');

        foreach ([[2, 2027], [2, 2028], [4, 2026], [8, 2026]] as [$month, $year]) {
            $this->assertSame(
                1,
                $this->report(['month' => $month, 'year' => $year])->viewData('summary')['recorded'],
                "The last day of {$month}/{$year} was expected inside the month."
            );
        }
    }

    public function test_the_search_matches_name_registration_and_roll_number(): void
    {
        $ali = $this->madrassaEnrollment($this->student('Ali Raza'));
        $ahmed = $this->madrassaEnrollment($this->student('Ahmed Khan'));

        $this->assertSame([$ali->id], $this->report(['search' => 'Ali Raza'])->viewData('students')->pluck('id')->all());
        $this->assertSame([$ali->id], $this->report(['search' => $ali->student->registration_number])->viewData('students')->pluck('id')->all());
        $this->assertSame([$ahmed->id], $this->report(['search' => $ahmed->student->roll_number])->viewData('students')->pluck('id')->all());
    }

    public function test_the_search_combines_with_the_other_filters_using_and(): void
    {
        $inGroup = $this->madrassaEnrollment($this->student('Ali Raza'));
        $this->madrassaEnrollment($this->student('Ali Hassan'), ['academic_class_id' => $this->nazra->id, 'section_id' => null]);
        $this->schoolEnrollment($this->student('Ali Khan'));

        // Track and class narrow first; the search narrows what is left,
        // rather than reaching back outside it.
        $this->assertSame(
            [$inGroup->id],
            $this->report([
                'academic_track' => 'Madrassa',
                'academic_class_id' => $this->hifzClass->id,
                'search' => 'Ali',
            ])->viewData('students')->pluck('id')->all()
        );
    }

    /* ---------------------------------------------------------------- */
    /* School */
    /* ---------------------------------------------------------------- */

    public function test_school_counts_the_morning_register_only(): void
    {
        $enrollment = $this->schoolEnrollment($this->student('Ahmed Ali'));

        $this->recordMany($enrollment, [self::MONDAY, self::TUESDAY], 'Morning', 'Present');
        $this->record($enrollment, self::WEDNESDAY, 'Morning', 'Absent', 'Sick');

        // Rows that should not exist for a school student. Written straight
        // to the table, past the entry sheet that would refuse them: the
        // report must not count them even so.
        $this->record($enrollment, self::MONDAY, 'Afternoon', 'Present');
        $this->record($enrollment, self::MONDAY, 'Evening', 'Absent', 'Sick');

        $response = $this->report(['academic_track' => 'School'])->assertOk();

        $this->assertSame(['present' => 2, 'absent' => 1], $this->countsFor($response, $enrollment));
        $this->assertSame(3, $response->viewData('summary')['recorded']);
    }

    public function test_the_school_period_summary_has_a_morning_row_only(): void
    {
        $enrollment = $this->schoolEnrollment();
        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        $this->record($enrollment, self::TUESDAY, 'Morning', 'Absent', 'Sick');

        $summary = $this->report(['academic_track' => 'School'])->assertOk()->viewData('periodSummary');

        $this->assertSame(['Morning'], array_column($summary, 'period'));
        $this->assertSame(1, $summary[0]['present']);
        $this->assertSame(1, $summary[0]['absent']);
        $this->assertSame(50.0, $summary[0]['percentage']);
    }

    public function test_school_has_no_period_filter(): void
    {
        $response = $this->report(['academic_track' => 'School', 'attendance_period' => 'Evening'])->assertOk();

        // Evening carried over from a madrassa report cannot survive here.
        $this->assertSame(['Morning'], $response->viewData('availablePeriods'));
        $this->assertSame('Morning', $response->viewData('filters')['attendance_period']);
    }

    /* ---------------------------------------------------------------- */
    /* Madrassa */
    /* ---------------------------------------------------------------- */

    public function test_each_madrassa_period_counts_on_its_own(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->recordMany($enrollment, [self::MONDAY, self::TUESDAY, self::WEDNESDAY], 'Morning', 'Present');
        $this->recordMany($enrollment, [self::MONDAY, self::TUESDAY], 'Afternoon', 'Absent');
        $this->record($enrollment, self::MONDAY, 'Evening', 'Present');

        foreach ([
            'Morning' => ['present' => 3, 'absent' => 0],
            'Afternoon' => ['present' => 0, 'absent' => 2],
            'Evening' => ['present' => 1, 'absent' => 0],
        ] as $period => $expected) {
            $response = $this->report(['attendance_period' => $period])->assertOk();

            $this->assertSame($expected, $this->countsFor($response, $enrollment), "The {$period} counts were wrong.");
        }
    }

    public function test_all_periods_counts_the_three_registers_separately(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // One teaching day: present, absent, present. Three records, not
        // one day rolled into a single verdict.
        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        $this->record($enrollment, self::MONDAY, 'Afternoon', 'Absent', 'Sick');
        $this->record($enrollment, self::MONDAY, 'Evening', 'Present');

        $response = $this->report(['attendance_period' => 'all'])->assertOk();

        $this->assertSame(['present' => 2, 'absent' => 1], $this->countsFor($response, $enrollment));
        $this->assertSame(3, $response->viewData('summary')['recorded']);
        $this->assertSame(66.67, $response->viewData('summary')['percentage']);
    }

    public function test_the_madrassa_period_summary_breaks_the_month_down(): void
    {
        $first = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $second = $this->madrassaEnrollment($this->student('Hassan Raza'));

        foreach ([$first, $second] as $enrollment) {
            $this->recordMany($enrollment, [self::MONDAY, self::TUESDAY, self::WEDNESDAY, self::THURSDAY], 'Morning', 'Present');
            $this->recordMany($enrollment, [self::MONDAY, self::TUESDAY, self::WEDNESDAY], 'Afternoon', 'Present');
            $this->record($enrollment, self::THURSDAY, 'Afternoon', 'Absent', 'Sick');
            $this->recordMany($enrollment, [self::MONDAY, self::TUESDAY], 'Evening', 'Absent');
        }

        $summary = $this->report(['attendance_period' => 'all'])->assertOk()->viewData('periodSummary');

        $this->assertSame(['Morning', 'Afternoon', 'Evening'], array_column($summary, 'period'));

        $this->assertSame(['present' => 8, 'absent' => 0, 'recorded' => 8, 'percentage' => 100.0], collect($summary[0])->only(['present', 'absent', 'recorded', 'percentage'])->all());
        $this->assertSame(['present' => 6, 'absent' => 2, 'recorded' => 8, 'percentage' => 75.0], collect($summary[1])->only(['present', 'absent', 'recorded', 'percentage'])->all());
        $this->assertSame(['present' => 0, 'absent' => 4, 'recorded' => 4, 'percentage' => 0.0], collect($summary[2])->only(['present', 'absent', 'recorded', 'percentage'])->all());
    }

    /* ---------------------------------------------------------------- */
    /* Counting and percentage */
    /* ---------------------------------------------------------------- */

    public function test_recorded_is_present_plus_absent_and_the_percentage_uses_it(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // Twenty present, five absent, and ten teaching days nobody has
        // transcribed yet. The denominator is 25, never 35.
        $dates = collect(StudentAttendance::monthDays(2026, 8))
            ->reject(fn ($day) => $day['is_off_day'])
            ->pluck('date');

        $this->recordMany($enrollment, $dates->take(20)->all(), 'Morning', 'Present');
        $this->recordMany($enrollment, $dates->slice(20, 1)->all(), 'Morning', 'Absent');

        // August 2026 has 21 teaching days, so four more absences are
        // recorded in the afternoon register to reach five.
        $this->recordMany($enrollment, $dates->take(4)->all(), 'Afternoon', 'Absent');

        $response = $this->report(['attendance_period' => 'all'])->assertOk();
        $summary = $response->viewData('summary');

        $this->assertSame(20, $summary['present']);
        $this->assertSame(5, $summary['absent']);
        $this->assertSame(25, $summary['recorded']);
        $this->assertSame(80.0, $summary['percentage']);
    }

    public function test_the_percentage_is_rounded_to_two_decimal_places(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // 72 of 78 is 92.307692...
        $this->assertSame(92.31, StudentAttendance::attendancePercentage(72, 6));
        $this->assertSame('92.31%', StudentAttendance::formatPercentage(72, 6));

        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        $this->record($enrollment, self::TUESDAY, 'Morning', 'Present');
        $this->record($enrollment, self::WEDNESDAY, 'Morning', 'Absent', 'Sick');

        $this->assertSame(66.67, $this->report()->viewData('summary')['percentage']);
    }

    public function test_zero_recorded_shows_not_available_rather_than_zero(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $response = $this->report()->assertOk();

        $this->assertNull($response->viewData('summary')['percentage']);
        $this->assertNull(StudentAttendance::attendancePercentage(0, 0));
        $this->assertSame('N/A', StudentAttendance::formatPercentage(0, 0));

        $this->assertSame(['present' => 0, 'absent' => 0], $this->countsFor($response, $enrollment));
        $response->assertSee('N/A');
    }

    public function test_missing_attendance_never_becomes_present(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // One day out of twenty-one teaching days.
        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');

        $summary = $this->report()->assertOk()->viewData('summary');

        $this->assertSame(1, $summary['present']);
        $this->assertSame(0, $summary['absent']);
        $this->assertSame(1, $summary['recorded']);
        $this->assertSame(100.0, $summary['percentage']);
    }

    public function test_students_with_no_attendance_still_appear_with_zero_counts(): void
    {
        $entered = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $notEntered = $this->madrassaEnrollment($this->student('Hassan Raza'));

        $this->record($entered, self::MONDAY, 'Morning', 'Present');

        $response = $this->report()->assertOk();

        // Both students, which is how the report shows whose paper register
        // is still waiting.
        $this->assertCount(2, $response->viewData('students'));
        $this->assertSame(2, $response->viewData('summary')['students']);
        $this->assertSame(['present' => 0, 'absent' => 0], $this->countsFor($response, $notEntered));
    }

    public function test_a_student_with_partial_attendance_has_the_right_percentage(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $this->recordMany($enrollment, [self::MONDAY, self::TUESDAY, self::WEDNESDAY], 'Morning', 'Present');
        $this->record($enrollment, self::THURSDAY, 'Morning', 'Absent', 'Sick');

        $counts = $this->countsFor($this->report()->assertOk(), $enrollment);

        $this->assertSame(['present' => 3, 'absent' => 1], $counts);
        $this->assertSame(75.0, StudentAttendance::attendancePercentage($counts['present'], $counts['absent']));
    }

    /* ---------------------------------------------------------------- */
    /* Dual track */
    /* ---------------------------------------------------------------- */

    public function test_a_dual_track_students_reports_never_merge(): void
    {
        $student = $this->student('Ahmed Ali');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->recordMany($school, [self::MONDAY, self::TUESDAY], 'Morning', 'Present');
        $this->record($school, self::WEDNESDAY, 'Morning', 'Absent', 'Sick');

        $this->record($madrassa, self::MONDAY, 'Morning', 'Absent', 'Sick');
        $this->recordMany($madrassa, [self::MONDAY, self::TUESDAY, self::WEDNESDAY], 'Afternoon', 'Present');
        $this->record($madrassa, self::MONDAY, 'Evening', 'Present');

        $schoolReport = $this->report(['academic_track' => 'School'])->assertOk();
        $madrassaReport = $this->report(['academic_track' => 'Madrassa', 'attendance_period' => 'all'])->assertOk();

        // The same student, on both reports, with only their own numbers.
        $this->assertSame(['present' => 2, 'absent' => 1], $this->countsFor($schoolReport, $school));
        $this->assertSame(3, $schoolReport->viewData('summary')['recorded']);

        $this->assertSame(['present' => 4, 'absent' => 1], $this->countsFor($madrassaReport, $madrassa));
        $this->assertSame(5, $madrassaReport->viewData('summary')['recorded']);

        // And neither report knows about the other's enrollment.
        $this->assertNull($this->rowFor($schoolReport, $madrassa));
        $this->assertNull($this->rowFor($madrassaReport, $school));
    }

    /* ---------------------------------------------------------------- */
    /* Historical context */
    /* ---------------------------------------------------------------- */

    public function test_historical_context_stays_correct_after_a_promotion(): void
    {
        $student = $this->student('Ahmed Ali');
        $old = $this->madrassaEnrollment($student, ['academic_class_id' => $this->nazra->id, 'section_id' => null]);

        $this->recordMany($old, [self::MONDAY, self::TUESDAY], 'Morning', 'Present');

        $student->promote([
            'academic_track' => 'Madrassa',
            'academic_session_id' => $this->otherSession->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'promotion_date' => '2026-09-01',
        ]);

        $response = $this->report(['academic_session_id' => $this->session->id])->assertOk();
        $row = $this->rowFor($response, $old);

        // The old month still reports under Nazra, the class it happened
        // in, not under the Hifz class the student has since moved to.
        $this->assertNotNull($row);
        $this->assertSame($this->nazra->id, $row->academic_class_id);
        $this->assertSame(2, (int) $row->present_count);

        $this->assertSame(
            ['Nazra'],
            array_column($response->viewData('groupSummary'), 'class')
        );
    }

    public function test_inactive_master_data_does_not_remove_attendance_from_reports(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $this->recordMany($enrollment, [self::MONDAY, self::TUESDAY], 'Morning', 'Present');

        // Retired after the month it covers.
        $this->hifz->update(['status' => false]);
        $this->hifzClass->update(['status' => false]);
        $this->hifzA->update(['status' => false]);

        $response = $this->report()->assertOk();

        $this->assertSame(['present' => 2, 'absent' => 0], $this->countsFor($response, $enrollment));
        $this->assertSame(2, $response->viewData('summary')['recorded']);
        $response->assertSee($this->hifzClass->name)->assertSee($this->hifzA->name);
    }

    /* ---------------------------------------------------------------- */
    /* Group summary */
    /* ---------------------------------------------------------------- */

    public function test_the_group_summary_totals_each_class_and_section(): void
    {
        $sectionA = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $alsoA = $this->madrassaEnrollment($this->student('Hassan Raza'));
        $sectionB = $this->madrassaEnrollment($this->student('Bilal Khan'), ['section_id' => $this->hifzB->id]);
        $nazra = $this->madrassaEnrollment($this->student('Usman Tariq'), [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $this->recordMany($sectionA, [self::MONDAY, self::TUESDAY], 'Morning', 'Present');
        $this->record($alsoA, self::MONDAY, 'Morning', 'Absent', 'Sick');
        $this->record($sectionB, self::MONDAY, 'Morning', 'Present');
        // Nazra has nobody entered yet.

        $groups = collect($this->report()->assertOk()->viewData('groupSummary'))
            ->keyBy(fn ($group) => $group['class'].'|'.$group['section']);

        $this->assertSame(
            ['students' => 2, 'present' => 2, 'absent' => 1, 'recorded' => 3],
            collect($groups['Hifz|Hifz-A'])->only(['students', 'present', 'absent', 'recorded'])->all()
        );
        $this->assertSame(66.67, $groups['Hifz|Hifz-A']['percentage']);

        $this->assertSame(1, $groups['Hifz|Hifz-B']['students']);
        $this->assertSame(100.0, $groups['Hifz|Hifz-B']['percentage']);

        // A class with no attendance still appears, with its students and
        // no percentage rather than a zero.
        $this->assertSame(1, $groups['Nazra|No section']['students']);
        $this->assertSame(0, $groups['Nazra|No section']['recorded']);
        $this->assertNull($groups['Nazra|No section']['percentage']);
        $this->assertNotNull($nazra);
    }

    /* ---------------------------------------------------------------- */
    /* Pagination, sorting and totals */
    /* ---------------------------------------------------------------- */

    public function test_the_student_table_is_paginated_at_twenty_five(): void
    {
        collect(range(1, 30))->each(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        $first = $this->report()->assertOk();

        $this->assertSame(30, $first->viewData('students')->total());
        $this->assertCount(25, $first->viewData('students'));

        $second = $this->report(['page' => 2])->assertOk();

        $this->assertCount(5, $second->viewData('students'));
        $this->assertSame(2, $second->viewData('students')->currentPage());
    }

    public function test_the_summary_totals_every_matching_student_not_just_the_page(): void
    {
        $enrollments = collect(range(1, 30))
            ->map(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        // One present each, across all thirty students.
        $enrollments->each(fn ($enrollment) => $this->record($enrollment, self::MONDAY, 'Morning', 'Present'));

        $response = $this->report(['page' => 2])->assertOk();

        // Page two holds five students; the cards still cover all thirty.
        $this->assertCount(5, $response->viewData('students'));
        $this->assertSame(30, $response->viewData('summary')['students']);
        $this->assertSame(30, $response->viewData('summary')['present']);
        $this->assertSame(30, $response->viewData('summary')['recorded']);
        $this->assertSame(100.0, $response->viewData('summary')['percentage']);

        // And so does the group summary.
        $this->assertSame(30, $response->viewData('groupSummary')[0]['students']);
        $this->assertSame(30, $response->viewData('groupSummary')[0]['present']);
    }

    public function test_filters_survive_pagination(): void
    {
        collect(range(1, 30))->each(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));
        $this->schoolEnrollment($this->student('School Student'));

        $response = $this->report([
            'academic_class_id' => $this->hifzClass->id,
            'attendance_period' => 'Morning',
            'page' => 2,
        ])->assertOk();

        $this->assertSame(30, $response->viewData('students')->total());

        foreach (['academic_track=Madrassa', 'attendance_period=Morning', 'month=8', 'year=2026'] as $parameter) {
            $this->assertStringContainsString($parameter, urldecode($response->viewData('students')->previousPageUrl()));
        }
    }

    public function test_the_table_can_be_sorted(): void
    {
        $low = $this->madrassaEnrollment($this->student('Zaid Ahmed'));
        $high = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $this->record($low, self::MONDAY, 'Morning', 'Absent', 'Sick');
        $this->recordMany($high, [self::MONDAY, self::TUESDAY], 'Morning', 'Present');

        // By name, ascending.
        $this->assertSame(
            [$high->id, $low->id],
            $this->report(['sort' => 'student', 'direction' => 'asc'])->viewData('students')->pluck('id')->all()
        );

        // By present count, descending.
        $this->assertSame(
            [$high->id, $low->id],
            $this->report(['sort' => 'present', 'direction' => 'desc'])->viewData('students')->pluck('id')->all()
        );

        // By percentage, ascending: 0% before 100%.
        $this->assertSame(
            [$low->id, $high->id],
            $this->report(['sort' => 'percentage', 'direction' => 'asc'])->viewData('students')->pluck('id')->all()
        );
    }

    public function test_the_student_history_link_carries_the_track_session_and_month(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        // Escaped, because Blade escapes the ampersands in the href.
        $link = e(route('students.attendance', [
            'student' => $enrollment->student_id,
            'academic_track' => 'Madrassa',
            'academic_session_id' => $this->session->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $this->report()
            ->assertOk()
            ->assertSee($link, false)
            ->assertSee('View Attendance History');
    }

    /* ---------------------------------------------------------------- */
    /* Empty states */
    /* ---------------------------------------------------------------- */

    public function test_no_students_and_no_attendance_are_different_messages(): void
    {
        // Nobody in the group at all.
        $this->report()->assertOk()->assertSee('No students found for the selected filters.');

        // Students, but nothing entered for the month.
        $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $this->report()
            ->assertOk()
            ->assertDontSee('No students found for the selected filters.')
            ->assertSee('No attendance has been recorded for the selected month.');
    }

    /* ---------------------------------------------------------------- */
    /* Safety and performance */
    /* ---------------------------------------------------------------- */

    public function test_visiting_the_report_creates_and_changes_nothing(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $this->recordMany($enrollment, [self::MONDAY, self::TUESDAY], 'Morning', 'Present');
        $this->record($enrollment, self::WEDNESDAY, 'Morning', 'Absent', 'Sick');

        $before = DB::table('student_attendances')->orderBy('id')->get()->toArray();

        $this->report()->assertOk();
        $this->report(['attendance_period' => 'all'])->assertOk();
        $this->report(['academic_track' => 'School'])->assertOk();
        $this->report(['month' => 9])->assertOk();

        $this->assertDatabaseCount('student_attendances', 3);
        $this->assertEquals($before, DB::table('student_attendances')->orderBy('id')->get()->toArray());
    }

    public function test_the_report_does_not_run_a_query_per_student(): void
    {
        $enrollments = collect(range(1, 3))
            ->map(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        $enrollments->each(fn ($enrollment) => $this->record($enrollment, self::MONDAY, 'Morning', 'Present'));

        $small = $this->queriesToOpenTheReport();

        $more = collect(range(4, 25))
            ->map(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        $more->each(function ($enrollment) {
            foreach ([self::MONDAY, self::TUESDAY, self::WEDNESDAY, self::THURSDAY] as $date) {
                foreach (['Morning', 'Afternoon', 'Evening'] as $period) {
                    $this->record($enrollment, $date, $period, 'Present');
                }
            }
        });

        $large = $this->queriesToOpenTheReport();

        $this->assertSame(25, $this->report(['attendance_period' => 'all'])->viewData('summary')['students']);

        // Twenty-five students and hundreds of attendance rows on the same
        // number of queries: everything is aggregated in SQL.
        $this->assertLessThanOrEqual($small, $large);
        $this->assertLessThan(25, $large);
    }

    /**
     * Count the queries one report page costs.
     */
    private function queriesToOpenTheReport(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->report(['attendance_period' => 'all'])->assertOk();

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    }
}
