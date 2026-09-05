<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentPrayerAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers the academic session prayer summary.
 *
 * What makes a session harder than a month is Expected. A month's expected
 * count is the same for everyone; over a year students join and leave, so
 * each student's expected prayers are the working days of their own
 * madrassa placement inside the session, times five. A student who joined
 * in November must not be marked down for September.
 *
 * The rest carries over from the earlier reports: Madrassa only, Unrecorded
 * is never an absence, and percentages are Present over Recorded.
 *
 * Expected also stops at today while a session is still running: a day
 * nobody has reached cannot have been prayed, so it is not counted and
 * never appears as unrecorded. Only the expected side is capped - the
 * records and the month list still cover the session as declared.
 *
 * The fixture session runs 2026-04-01 to 2027-03-31.
 */
class PrayerAttendanceSessionSummaryTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    /**
     * The day these tests are run as, a Saturday inside the fixture session.
     *
     * Frozen so that "how much of the session has happened" is a fact about
     * the test rather than about the day the suite is run.
     */
    private const TODAY = '2026-08-22';

    protected function setUp(): void
    {
        parent::setUp();

        // Cleared for us by Laravel's TestCase teardown.
        Carbon::setTestNow(self::TODAY);

        $this->buildMadrassa();
    }

    /**
     * The working days of the session so far, as expected prayers.
     */
    private function expectedToToday(string $from = '2026-04-01'): int
    {
        return StudentPrayerAttendance::workingDaysBetween($from, self::TODAY) * 5;
    }

    /* ---------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------- */

    /**
     * Run the session summary.
     *
     * @param  array<string, mixed>  $filters
     */
    private function summary(array $filters = [])
    {
        return $this->get(route('prayer-attendance.session-summary', array_merge([
            'academic_session_id' => $this->session->id,
        ], $filters)));
    }

    private function prayer(
        StudentAcademicEnrollment $enrollment,
        string $date,
        string $prayerName,
        string $status
    ): void {
        StudentPrayerAttendance::create([
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => $date,
            'prayer' => $prayerName,
            'status' => $status,
        ]);
    }

    private function wholeDay(StudentAcademicEnrollment $enrollment, string $date, string $status = 'Present'): void
    {
        foreach (StudentPrayerAttendance::PRAYERS as $prayerName) {
            $this->prayer($enrollment, $date, $prayerName, $status);
        }
    }

    /**
     * The report rows, keyed by student id.
     *
     * @return Collection<int, object>
     */
    private function rows($response)
    {
        return $response->viewData('students')->getCollection()->keyBy('student_id');
    }

    /**
     * One student's expected prayers, as the report worked them out.
     */
    private function expectedFor($response, int $studentId): int
    {
        $row = $this->rows($response)->get($studentId);

        return (int) ($response->viewData('expectedByEnrollment')[(int) $row->enrollment_id] ?? 0);
    }

    /* ---------------------------------------------------------------- */
    /* Access and the session filter */
    /* ---------------------------------------------------------------- */

    public function test_a_guest_cannot_reach_the_session_summary(): void
    {
        auth()->logout();

        $this->get(route('prayer-attendance.session-summary'))->assertRedirect(route('login'));
    }

    public function test_the_session_is_required_before_anything_is_reported(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $response = $this->get(route('prayer-attendance.session-summary'));

        $response->assertOk();
        $response->assertSee('Choose an academic session to run the summary.');
        $this->assertNull($response->viewData('students'));
        $this->assertNull($response->viewData('group'));
    }

    public function test_the_session_filter_selects_that_sessions_placements(): void
    {
        $thisSession = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($thisSession);

        $nextSession = $this->student('Zaid Khan');
        $this->madrassaEnrollment($nextSession, [
            'academic_session_id' => $this->nextSession->id,
            'start_date' => '2027-04-01',
        ]);

        $this->assertSame([$thisSession->id], $this->rows($this->summary())->keys()->all());

        $this->assertSame(
            [$nextSession->id],
            $this->rows($this->summary(['academic_session_id' => $this->nextSession->id]))->keys()->all()
        );
    }

    public function test_the_monthly_report_links_to_the_session_summary(): void
    {
        $this->get(route('prayer-attendance.reports'))
            ->assertOk()
            ->assertSee('Session Summary')
            ->assertSee(route('prayer-attendance.session-summary'), false);
    }

    /* ---------------------------------------------------------------- */
    /* Madrassa only */
    /* ---------------------------------------------------------------- */

    public function test_a_school_only_student_is_never_reported(): void
    {
        $madrassa = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($madrassa);

        $schoolOnly = $this->student('Usman Tariq', 'School');
        $this->schoolEnrollment($schoolOnly);

        $response = $this->summary();

        $this->assertSame([$madrassa->id], $this->rows($response)->keys()->all());
        $response->assertDontSee('Usman Tariq');
        $this->assertSame(1, $response->viewData('group')['students']);
    }

    public function test_a_hifz_plus_school_student_is_counted_once(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->wholeDay($madrassa, self::MONDAY, 'Present');
        // Planted on the school side. A prayer should never be written
        // there; if one were, it must reach no total.
        $this->wholeDay($school, self::MONDAY, 'Absent');

        $response = $this->summary();
        $rows = $this->rows($response);
        $group = $response->viewData('group');

        $this->assertCount(1, $rows);
        $this->assertSame($madrassa->id, (int) $rows->first()->enrollment_id);
        $this->assertNotSame($school->id, (int) $rows->first()->enrollment_id);

        $this->assertSame(1, $group['students']);
        $this->assertSame(5, $group['recorded']);
        $this->assertSame(5, $group['present']);
        $this->assertSame(0, $group['absent']);
    }

    /* ---------------------------------------------------------------- */
    /* Expected prayers from the real enrollment period */
    /* ---------------------------------------------------------------- */

    public function test_expected_prayers_cover_the_session_so_far_for_a_full_year_student(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student, ['start_date' => '2026-04-01']);

        $response = $this->summary();

        // The session runs to March 2027, but it is still running, so only
        // the days up to today have offered any prayers.
        $this->assertSame($this->expectedToToday(), $this->expectedFor($response, $student->id));
        $this->assertSame($this->expectedToToday(), $response->viewData('group')['expected']);

        // Emphatically not the whole declared year.
        $this->assertLessThan(
            StudentPrayerAttendance::workingDaysBetween('2026-04-01', '2027-03-31') * 5,
            $response->viewData('group')['expected']
        );
    }

    public function test_days_before_the_student_joined_are_not_expected(): void
    {
        $wholeYear = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($wholeYear, ['start_date' => '2026-04-01']);

        // Joined mid-month, part way through the session.
        $joinedLate = $this->student('Zaid Khan');
        $this->madrassaEnrollment($joinedLate, ['start_date' => '2026-06-16']);

        $response = $this->summary();

        $expectedLate = $this->expectedToToday('2026-06-16');

        $this->assertSame($expectedLate, $this->expectedFor($response, $joinedLate->id));
        $this->assertLessThan(
            $this->expectedFor($response, $wholeYear->id),
            $this->expectedFor($response, $joinedLate->id)
        );

        // And the group total is the sum of the two, not two full sessions.
        $this->assertSame(
            $this->expectedFor($response, $wholeYear->id) + $expectedLate,
            $response->viewData('group')['expected']
        );
    }

    public function test_days_after_the_student_left_are_not_expected(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        // Left well before today, so the enrollment end is what binds and
        // not the running-session cap.
        $this->madrassaEnrollment($student, [
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-15',
            'status' => 'Left',
        ]);

        $expected = StudentPrayerAttendance::workingDaysBetween('2026-04-01', '2026-06-15') * 5;

        $this->assertSame($expected, $this->expectedFor($this->summary(), $student->id));
        $this->assertLessThan($this->expectedToToday(), $expected);
    }

    public function test_an_enrollment_reaching_outside_the_session_is_clamped_to_it(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        // Starts before the session opens and has no end at all.
        $this->madrassaEnrollment($student, ['start_date' => '2025-01-01']);

        // Clamped at the front by the session, and at the back by today.
        $this->assertSame($this->expectedToToday(), $this->expectedFor($this->summary(), $student->id));
    }

    public function test_an_ongoing_session_never_expects_a_future_date(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        // Open-ended, so nothing but today limits the far end.
        $this->madrassaEnrollment($student, ['start_date' => '2026-04-01', 'end_date' => null]);

        $response = $this->summary();
        $group = $response->viewData('group');

        $this->assertSame($this->expectedToToday(), $group['expected']);

        // Nothing recorded, so everything expected is unrecorded - and that
        // total still stops at today rather than running to March.
        $this->assertSame(0, $group['recorded']);
        $this->assertSame($this->expectedToToday(), $group['unrecorded']);

        // Every month still to come expects nothing and therefore reports
        // nothing as unrecorded, while still being listed.
        $monthly = collect($response->viewData('monthly'))->keyBy('label');

        foreach (['September 2026', 'October 2026', 'January 2027', 'March 2027'] as $future) {
            $this->assertSame(0, $monthly->get($future)['expected'], $future);
            $this->assertSame(0, $monthly->get($future)['unrecorded'], $future);
        }

        // The current month counts only as far as today: 1 to 22 August
        // holds nineteen working days.
        $this->assertSame(95, $monthly->get('August 2026')['expected']);

        // A month already behind us is counted in full.
        $this->assertSame(
            StudentPrayerAttendance::workingDaysBetween('2026-07-01', '2026-07-31') * 5,
            $monthly->get('July 2026')['expected']
        );
    }

    public function test_a_completed_session_still_uses_its_own_end_date(): void
    {
        // A session that finished before today. Nothing about it is in the
        // future, so the whole of it is expected.
        $finished = AcademicSession::create([
            'name' => '2025-2026',
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'status' => true,
        ]);

        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student, [
            'academic_session_id' => $finished->id,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'status' => 'Completed',
        ]);

        $response = $this->summary(['academic_session_id' => $finished->id]);

        $expected = StudentPrayerAttendance::workingDaysBetween('2025-04-01', '2026-03-31') * 5;

        $this->assertSame($expected, $this->expectedFor($response, $student->id));
        $this->assertSame($expected, $response->viewData('group')['expected']);

        // Every month of it is counted, including the last.
        $monthly = collect($response->viewData('monthly'))->keyBy('label');

        $this->assertCount(12, $monthly);
        $this->assertSame(
            StudentPrayerAttendance::workingDaysBetween('2026-03-01', '2026-03-31') * 5,
            $monthly->get('March 2026')['expected']
        );
        $this->assertSame($expected, collect($monthly)->sum('expected'));
    }

    public function test_a_completed_session_still_respects_the_enrollment_dates(): void
    {
        $finished = AcademicSession::create([
            'name' => '2025-2026',
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'status' => true,
        ]);

        $student = $this->student('Ahtesham Shakeel');
        // Joined two months in and left two months early.
        $this->madrassaEnrollment($student, [
            'academic_session_id' => $finished->id,
            'start_date' => '2025-06-01',
            'end_date' => '2026-01-31',
            'status' => 'Completed',
        ]);

        $expected = StudentPrayerAttendance::workingDaysBetween('2025-06-01', '2026-01-31') * 5;

        $response = $this->summary(['academic_session_id' => $finished->id]);

        $this->assertSame($expected, $this->expectedFor($response, $student->id));
        $this->assertLessThan(
            StudentPrayerAttendance::workingDaysBetween('2025-04-01', '2026-03-31') * 5,
            $expected
        );

        // The months outside the placement expect nothing from them.
        $monthly = collect($response->viewData('monthly'))->keyBy('label');

        $this->assertSame(0, $monthly->get('April 2025')['expected']);
        $this->assertSame(0, $monthly->get('March 2026')['expected']);
    }

    public function test_a_future_dated_present_record_is_ignored(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // 1 September 2026 is a Tuesday, and still to come. A day nobody
        // has reached cannot have been prayed, so a register entered ahead
        // of time waits until its date arrives.
        $this->wholeDay($enrollment, '2026-09-01', 'Present');

        $group = $this->summary()->viewData('group');

        $this->assertSame(0, $group['recorded']);
        $this->assertSame(0, $group['present']);
        $this->assertSame(0, $group['absent']);
        $this->assertSame('N/A', $group['percentage']);

        // The row says the same.
        $row = $this->rows($this->summary())->get($student->id);

        $this->assertSame(0, (int) $row->recorded);
        $this->assertSame(0, (int) $row->present);
        $this->assertSame(0, (int) $row->fajr_present);

        // The record is still on file - this report simply does not count
        // it yet.
        $this->assertDatabaseCount('student_prayer_attendances', 5);
    }

    public function test_a_future_dated_absent_record_is_ignored(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->wholeDay($enrollment, '2026-09-01', 'Absent');

        $group = $this->summary()->viewData('group');

        $this->assertSame(0, $group['recorded']);
        $this->assertSame(0, $group['absent']);
        $this->assertSame('N/A', $group['percentage']);

        // And it does not reach the prayer breakdown either.
        $this->assertSame(0, $group['by_prayer']['Fajr']['absent']);
        $this->assertSame(0, $group['by_prayer']['Isha']['absent']);
    }

    public function test_future_records_do_not_change_the_totals_or_the_percentage(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // Two days already past: five present, five absent.
        $this->wholeDay($enrollment, self::MONDAY, 'Present');
        $this->wholeDay($enrollment, self::TUESDAY, 'Absent');

        $before = $this->summary()->viewData('group');

        $this->assertSame(10, $before['recorded']);
        $this->assertSame('50.00%', $before['percentage']);

        // Now enter a month that has not happened yet, all absent. It must
        // move nothing at all.
        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $future) {
            $this->wholeDay($enrollment, $future, 'Absent');
        }

        $after = $this->summary()->viewData('group');

        $this->assertSame($before['recorded'], $after['recorded']);
        $this->assertSame($before['present'], $after['present']);
        $this->assertSame($before['absent'], $after['absent']);
        $this->assertSame($before['percentage'], $after['percentage']);
        $this->assertSame($before['expected'], $after['expected']);
        $this->assertSame($before['unrecorded'], $after['unrecorded']);

        // Fifteen more rows exist; none of them is counted.
        $this->assertDatabaseCount('student_prayer_attendances', 25);
    }

    public function test_recorded_never_runs_past_expected(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // A whole future month transcribed early. Left uncounted, Expected
        // still bounds Recorded and Unrecorded stays a real figure.
        foreach (StudentPrayerAttendance::monthDays(2026, 9) as $day) {
            if (! $day['is_off_day']) {
                $this->wholeDay($enrollment, $day['date'], 'Present');
            }
        }

        $group = $this->summary()->viewData('group');

        $this->assertGreaterThan(0, $group['expected']);
        $this->assertSame(0, $group['recorded']);
        $this->assertLessThanOrEqual($group['expected'], $group['recorded']);
        $this->assertSame($group['expected'], $group['unrecorded']);

        // September expects nothing and reports nothing, while still being
        // listed as a month the session runs over.
        $september = collect($this->summary()->viewData('monthly'))->firstWhere('label', 'September 2026');

        $this->assertSame(0, $september['expected']);
        $this->assertSame(0, $september['recorded']);
        $this->assertSame(0, $september['unrecorded']);
        $this->assertSame('N/A', $september['percentage']);
    }

    public function test_a_completed_session_still_counts_records_up_to_its_end(): void
    {
        // Nothing about a finished session is in the future, so every
        // record in it is counted exactly as before.
        $finished = AcademicSession::create([
            'name' => '2025-2026',
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'status' => true,
        ]);

        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student, [
            'academic_session_id' => $finished->id,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'status' => 'Completed',
        ]);

        // The last working day of the session: 31 March 2026 is a Tuesday.
        $this->wholeDay($enrollment, '2026-03-31', 'Present');
        $this->wholeDay($enrollment, '2025-04-01', 'Absent');

        $group = $this->summary(['academic_session_id' => $finished->id])->viewData('group');

        $this->assertSame(10, $group['recorded']);
        $this->assertSame(5, $group['present']);
        $this->assertSame(5, $group['absent']);
        $this->assertSame('50.00%', $group['percentage']);
    }

    public function test_sundays_are_excluded_from_expected_prayers(): void
    {
        // One calendar week is six working days, not seven.
        $this->assertSame(6, StudentPrayerAttendance::workingDaysBetween(self::MONDAY, '2026-08-09'));
        $this->assertSame(30, StudentPrayerAttendance::expectedPrayersForDays(6));

        // A Sunday on its own offers nothing.
        $this->assertSame(0, StudentPrayerAttendance::workingDaysBetween(self::SUNDAY, self::SUNDAY));

        // The Saturday beside it is worked, so the pair offers one day.
        $this->assertSame(1, StudentPrayerAttendance::workingDaysBetween(self::SATURDAY, self::SUNDAY));
    }

    public function test_overlapping_placements_do_not_double_count_a_day(): void
    {
        // The unique index already allows only one madrassa placement per
        // student per session, so this guards the arithmetic rather than a
        // reachable state. Asserted directly on the rule.
        $week = [self::MONDAY, '2026-08-07'];

        $this->assertSame(5, StudentPrayerAttendance::workingDaysAcrossPeriods([$week]));

        // The same week twice is still one week.
        $this->assertSame(5, StudentPrayerAttendance::workingDaysAcrossPeriods([$week, $week]));

        // Overlapping halves cover the week once.
        $this->assertSame(5, StudentPrayerAttendance::workingDaysAcrossPeriods([
            [self::MONDAY, self::WEDNESDAY],
            [self::TUESDAY, '2026-08-07'],
        ]));

        // Genuinely separate weeks add up.
        $this->assertSame(10, StudentPrayerAttendance::workingDaysAcrossPeriods([
            $week,
            ['2026-08-10', '2026-08-14'],
        ]));
    }

    public function test_a_period_that_ends_before_it_starts_covers_nothing(): void
    {
        $this->assertSame(0, StudentPrayerAttendance::workingDaysAcrossPeriods([['2026-08-10', '2026-08-03']]));
        $this->assertSame(0, StudentPrayerAttendance::workingDaysAcrossPeriods([]));
        $this->assertNull(StudentPrayerAttendance::overlappingPeriod('2026-01-01', '2026-02-01', '2026-05-01', '2026-06-01'));
    }

    /* ---------------------------------------------------------------- */
    /* The recorded numbers */
    /* ---------------------------------------------------------------- */

    public function test_a_student_with_no_records_appears_with_zeroes_and_na(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student);

        $response = $this->summary();
        $row = $this->rows($response)->get($student->id);

        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->recorded);
        $this->assertSame(0, (int) $row->present);
        $this->assertSame(0, (int) $row->absent);
        $this->assertSame(0, (int) $row->fajr_present);

        // Expected is still real: they were enrolled all year.
        $this->assertGreaterThan(0, $this->expectedFor($response, $student->id));

        $response->assertSee('N/A');
    }

    public function test_present_absent_and_recorded_are_counted_from_the_rows(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->wholeDay($enrollment, self::MONDAY, 'Present');
        $this->wholeDay($enrollment, self::TUESDAY, 'Absent');
        $this->prayer($enrollment, self::WEDNESDAY, 'Fajr', 'Present');

        $row = $this->rows($this->summary())->get($student->id);

        $this->assertSame(11, (int) $row->recorded);
        $this->assertSame(6, (int) $row->present);
        $this->assertSame(5, (int) $row->absent);
    }

    public function test_unrecorded_prayers_are_never_counted_as_absent(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // One prayer in a whole session.
        $this->prayer($enrollment, self::MONDAY, 'Fajr', 'Present');

        $response = $this->summary();
        $group = $response->viewData('group');
        $expected = $this->expectedFor($response, $student->id);

        $this->assertSame(1, $group['recorded']);
        $this->assertSame(1, $group['present']);
        $this->assertSame(0, $group['absent']);
        $this->assertSame($expected - 1, $group['unrecorded']);
        // One of one, not one of twelve hundred.
        $this->assertSame('100.00%', $group['percentage']);
    }

    public function test_each_prayer_is_counted_separately(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->prayer($enrollment, self::MONDAY, 'Fajr', 'Absent');
        $this->prayer($enrollment, self::MONDAY, 'Zuhr', 'Present');
        $this->prayer($enrollment, self::MONDAY, 'Asr', 'Present');
        $this->prayer($enrollment, self::MONDAY, 'Maghrib', 'Present');
        $this->prayer($enrollment, self::MONDAY, 'Isha', 'Present');
        $this->prayer($enrollment, self::TUESDAY, 'Fajr', 'Present');

        $row = $this->rows($this->summary())->get($student->id);

        $this->assertSame(1, (int) $row->fajr_present);
        $this->assertSame(1, (int) $row->fajr_absent);
        $this->assertSame(1, (int) $row->zuhr_present);
        $this->assertSame(0, (int) $row->zuhr_absent);
        $this->assertSame(1, (int) $row->asr_present);
        $this->assertSame(1, (int) $row->maghrib_present);
        $this->assertSame(1, (int) $row->isha_present);
    }

    /* ---------------------------------------------------------------- */
    /* The five-prayer session summary */
    /* ---------------------------------------------------------------- */

    public function test_the_prayer_summary_reports_each_prayer_with_its_own_expected(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student, ['start_date' => '2026-04-01']);

        $this->prayer($enrollment, self::MONDAY, 'Fajr', 'Present');
        $this->prayer($enrollment, self::TUESDAY, 'Fajr', 'Absent');
        $this->prayer($enrollment, self::MONDAY, 'Zuhr', 'Absent');

        $breakdown = $this->summary()->viewData('group')['by_prayer'];

        // In the order they are prayed, never alphabetically.
        $this->assertSame(['Fajr', 'Zuhr', 'Asr', 'Maghrib', 'Isha'], array_keys($breakdown));

        // Each prayer happens once a working day: a fifth of the session so
        // far, since the session is still running.
        $workingDays = StudentPrayerAttendance::workingDaysBetween('2026-04-01', self::TODAY);

        $this->assertSame($workingDays, $breakdown['Fajr']['expected']);
        $this->assertSame(2, $breakdown['Fajr']['recorded']);
        $this->assertSame($workingDays - 2, $breakdown['Fajr']['unrecorded']);
        $this->assertSame(1, $breakdown['Fajr']['present']);
        $this->assertSame(1, $breakdown['Fajr']['absent']);
        $this->assertSame('50.00%', $breakdown['Fajr']['percentage']);

        $this->assertSame('0.00%', $breakdown['Zuhr']['percentage']);

        // Never transcribed, so no verdict.
        $this->assertSame(0, $breakdown['Isha']['recorded']);
        $this->assertSame('N/A', $breakdown['Isha']['percentage']);

        // The five prayers' expected counts add up to the session total.
        $this->assertSame(
            $this->summary()->viewData('group')['expected'],
            array_sum(array_column($breakdown, 'expected'))
        );
    }

    /* ---------------------------------------------------------------- */
    /* The group summary */
    /* ---------------------------------------------------------------- */

    public function test_the_group_counts_students_with_and_without_records(): void
    {
        $recorded = $this->student('Ahtesham Shakeel');
        $this->wholeDay($this->madrassaEnrollment($recorded), self::MONDAY, 'Present');

        $this->madrassaEnrollment($this->student('Zaid Khan'));
        $this->madrassaEnrollment($this->student('Owais Raza'));

        $group = $this->summary()->viewData('group');

        $this->assertSame(3, $group['students']);
        $this->assertSame(1, $group['students_with_records']);
        $this->assertSame(2, $group['students_without_records']);
        $this->assertSame(5, $group['recorded']);
    }

    public function test_the_group_percentage_is_from_totals_not_averaged(): void
    {
        // One student at 100% over a single prayer, another at barely
        // anything over ninety-five. Averaging the two percentages would
        // give about 50%; the honest figure is 2 of 96.
        $first = $this->student('Ahtesham Shakeel');
        $firstEnrollment = $this->madrassaEnrollment($first);
        $this->prayer($firstEnrollment, self::MONDAY, 'Fajr', 'Present');

        $second = $this->student('Zaid Khan');
        $secondEnrollment = $this->madrassaEnrollment($second);

        $written = 0;

        // Only days already past: a future date would not be counted, and
        // this test is about the arithmetic rather than the cap.
        foreach (StudentPrayerAttendance::monthDays(2026, 8) as $day) {
            if ($day['is_off_day'] || $day['date'] > self::TODAY) {
                continue;
            }

            foreach (StudentPrayerAttendance::PRAYERS as $prayerName) {
                $this->prayer($secondEnrollment, $day['date'], $prayerName, $written === 0 ? 'Present' : 'Absent');
                $written++;
            }
        }

        // Nineteen working days to 22 August, five prayers each.
        $this->assertSame(95, $written);

        $group = $this->summary()->viewData('group');

        $this->assertSame(96, $group['recorded']);
        $this->assertSame(2, $group['present']);
        $this->assertSame(94, $group['absent']);
        $this->assertSame('2.08%', $group['percentage']);
    }

    /* ---------------------------------------------------------------- */
    /* The monthly breakdown */
    /* ---------------------------------------------------------------- */

    public function test_the_monthly_breakdown_covers_only_the_sessions_months(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $monthly = $this->summary()->viewData('monthly');

        // April 2026 through March 2027 is twelve months.
        $this->assertCount(12, $monthly);
        $this->assertSame('April 2026', $monthly[0]['label']);
        $this->assertSame('March 2027', $monthly[11]['label']);
    }

    public function test_the_monthly_breakdown_counts_each_month_on_its_own(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->wholeDay($enrollment, self::MONDAY, 'Present');
        $this->wholeDay($enrollment, self::TUESDAY, 'Absent');
        // 1 July 2026 is a Wednesday, and already past - a future month
        // would not be counted at all.
        $this->wholeDay($enrollment, '2026-07-01', 'Present');

        $monthly = collect($this->summary()->viewData('monthly'))->keyBy('label');

        $august = $monthly->get('August 2026');
        $this->assertSame(10, $august['recorded']);
        $this->assertSame(5, $august['present']);
        $this->assertSame(5, $august['absent']);
        $this->assertSame('50.00%', $august['percentage']);
        // 1 to 22 August is nineteen working days: the month is still
        // running, so the days after today expect nothing.
        $this->assertSame(95, $august['expected']);
        $this->assertSame(85, $august['unrecorded']);

        $july = $monthly->get('July 2026');
        $this->assertSame(5, $july['recorded']);
        $this->assertSame(5, $july['present']);
        $this->assertSame('100.00%', $july['percentage']);

        // A past month with nothing entered is unrecorded, not absent.
        $may = $monthly->get('May 2026');
        $this->assertSame(0, $may['recorded']);
        $this->assertSame(0, $may['absent']);
        $this->assertSame($may['expected'], $may['unrecorded']);
        $this->assertGreaterThan(0, $may['expected']);
        $this->assertSame('N/A', $may['percentage']);
    }

    public function test_a_month_before_the_student_joined_expects_nothing(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        // Joined in August, so April to July expect nothing from them.
        $this->madrassaEnrollment($student, ['start_date' => '2026-08-01']);

        $monthly = collect($this->summary()->viewData('monthly'))->keyBy('label');

        $this->assertSame(0, $monthly->get('April 2026')['expected']);
        $this->assertSame(0, $monthly->get('July 2026')['expected']);
        // August is the current month, so it is counted to today only.
        $this->assertSame(95, $monthly->get('August 2026')['expected']);

        // And the months add up to the session total.
        $this->assertSame(
            $this->summary()->viewData('group')['expected'],
            collect($monthly)->sum('expected')
        );
    }

    /* ---------------------------------------------------------------- */
    /* Historical placement */
    /* ---------------------------------------------------------------- */

    public function test_a_row_names_the_placement_its_records_were_made_under(): void
    {
        $student = $this->student('Ahtesham Shakeel');

        $nazra = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->hifzA->id,
        ]);
        $this->wholeDay($nazra, self::MONDAY, 'Present');

        // Promoted into a different class, in the next session.
        $student->promote([
            'academic_track' => 'Madrassa',
            'academic_session_id' => $this->nextSession->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'promotion_date' => '2027-04-01',
        ]);

        $row = $this->rows($this->summary())->get($student->id);

        // The 2026-2027 summary still reads Nazra / Hifz-A.
        $this->assertSame($nazra->id, (int) $row->enrollment_id);
        $this->assertSame('Nazra', $row->class_name);
        $this->assertSame('Hifz-A', $row->section_name);
        $this->assertSame(5, (int) $row->present);

        // And the next session reports the new placement.
        $next = $this->rows($this->summary(['academic_session_id' => $this->nextSession->id]))->get($student->id);

        $this->assertSame('Hifz', $next->class_name);
        $this->assertSame('Hifz-B', $next->section_name);
        $this->assertSame(0, (int) $next->present);
    }

    /* ---------------------------------------------------------------- */
    /* Filters and search */
    /* ---------------------------------------------------------------- */

    public function test_the_department_class_and_section_filters_narrow_the_summary(): void
    {
        $inHifzA = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($inHifzA, ['section_id' => $this->hifzA->id]);

        $inHifzB = $this->student('Zaid Khan');
        $this->madrassaEnrollment($inHifzB, ['section_id' => $this->hifzB->id]);

        $inNazra = $this->student('Owais Raza');
        $this->madrassaEnrollment($inNazra, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $this->assertCount(3, $this->rows($this->summary(['department_id' => $this->hifz->id])));
        $this->assertCount(0, $this->rows($this->summary(['department_id' => $this->darsENizami->id])));

        $this->assertSame(
            [$inNazra->id],
            $this->rows($this->summary(['academic_class_id' => $this->nazra->id]))->keys()->all()
        );
        $this->assertSame(
            [$inHifzA->id],
            $this->rows($this->summary(['section_id' => $this->hifzA->id]))->keys()->all()
        );

        // Contradictory filters match nothing rather than one winning.
        $this->assertCount(0, $this->rows($this->summary([
            'department_id' => $this->darsENizami->id,
            'academic_class_id' => $this->hifzClass->id,
        ])));
    }

    public function test_the_search_finds_a_student_by_name_registration_and_roll_number(): void
    {
        $wanted = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($wanted);
        $this->madrassaEnrollment($this->student('Zaid Khan'));

        foreach (['Ahtesham', $wanted->registration_number, $wanted->roll_number] as $term) {
            $this->assertSame(
                [$wanted->id],
                $this->rows($this->summary(['search' => $term]))->keys()->all(),
                "search for {$term}"
            );
        }
    }

    public function test_the_filters_also_narrow_the_group_and_monthly_totals(): void
    {
        $inHifz = $this->student('Ahtesham Shakeel');
        $this->wholeDay($this->madrassaEnrollment($inHifz), self::MONDAY, 'Present');

        $inNazra = $this->student('Zaid Khan');
        $this->wholeDay($this->madrassaEnrollment($inNazra, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]), self::MONDAY, 'Absent');

        $hifzOnly = $this->summary(['academic_class_id' => $this->hifzClass->id]);

        $this->assertSame(1, $hifzOnly->viewData('group')['students']);
        $this->assertSame(5, $hifzOnly->viewData('group')['present']);
        $this->assertSame(0, $hifzOnly->viewData('group')['absent']);

        $august = collect($hifzOnly->viewData('monthly'))->firstWhere('label', 'August 2026');

        $this->assertSame(5, $august['recorded']);
        $this->assertSame(5, $august['present']);
    }

    /* ---------------------------------------------------------------- */
    /* Individual student view */
    /* ---------------------------------------------------------------- */

    public function test_a_student_row_opens_their_existing_history_for_the_session(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student);

        $expected = route('students.prayer-attendance', [
            'student' => $student->id,
            'academic_session_id' => $this->session->id,
        ]);

        // Asserted on the escaped markup, since Blade writes the ampersands
        // as entities.
        $this->summary()->assertSee(e($expected), false);

        $this->get($expected)->assertOk()->assertSee('Ahtesham Shakeel');
    }

    /* ---------------------------------------------------------------- */
    /* Read-only safety and performance */
    /* ---------------------------------------------------------------- */

    public function test_running_the_summary_writes_nothing(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->wholeDay($enrollment, self::MONDAY, 'Present');

        $before = StudentPrayerAttendance::orderBy('id')->get()
            ->map(fn ($row) => $row->id.'|'.$row->status.'|'.$row->updated_at?->format('Y-m-d H:i:s'))
            ->all();

        $this->summary()->assertOk();
        $this->summary(['department_id' => $this->hifz->id])->assertOk();

        $after = StudentPrayerAttendance::orderBy('id')->get()
            ->map(fn ($row) => $row->id.'|'.$row->status.'|'.$row->updated_at?->format('Y-m-d H:i:s'))
            ->all();

        $this->assertSame($before, $after);
        $this->assertDatabaseCount('student_prayer_attendances', 5);
    }

    public function test_the_query_count_does_not_grow_with_the_students(): void
    {
        $counts = [];

        foreach ([5, 15, 30] as $target) {
            $this->fillClassTo($target);
            $counts[$target] = $this->countSummaryQueries();
        }

        // Aggregating in SQL and doing the interval arithmetic in PHP over
        // one small enrollment query is what keeps these equal.
        $this->assertSame($counts[5], $counts[15]);
        $this->assertSame($counts[5], $counts[30]);
        $this->assertLessThan(20, $counts[30]);
    }

    /**
     * Add madrassa students until the summary covers a given number.
     */
    private function fillClassTo(int $target): void
    {
        $existing = $this->summary()->viewData('group')['students'];

        for ($index = $existing; $index < $target; $index++) {
            $enrollment = $this->madrassaEnrollment($this->student('Student '.$index.' '.uniqid()));

            if ($index % 3 !== 0) {
                $this->wholeDay($enrollment, self::MONDAY, 'Present');
                $this->wholeDay($enrollment, self::TUESDAY, 'Absent');
            }
        }
    }

    /**
     * Count the queries one render of the summary costs.
     *
     * A warm-up request first: the permission and role lookups are cached
     * per process, so measuring the first request of a run would count them
     * once and never again.
     */
    private function countSummaryQueries(): int
    {
        $this->summary()->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->summary()->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_the_summary_does_not_read_the_other_registers(): void
    {
        $this->wholeDay($this->madrassaEnrollment($this->student('Ahtesham Shakeel')), self::MONDAY);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->summary()->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        DB::disableQueryLog();

        $this->assertStringContainsString('student_prayer_attendances', $queries);
        $this->assertStringNotContainsString('`student_attendances`', $queries);
        $this->assertStringNotContainsString('madrassa_daily_records', $queries);
    }

    public function test_the_student_report_is_paginated_and_keeps_its_filters(): void
    {
        for ($index = 0; $index < 30; $index++) {
            $this->madrassaEnrollment($this->student('Student '.$index.' '.uniqid()));
        }

        $first = $this->summary(['department_id' => $this->hifz->id]);

        $first->assertOk();
        $first->assertSee('Showing 1 to 25 of 30 Madrassa students');
        $this->assertCount(25, $first->viewData('students')->items());
        $first->assertSee('department_id='.$this->hifz->id, false);

        $second = $this->summary(['department_id' => $this->hifz->id, 'page' => 2]);

        $this->assertCount(5, $second->viewData('students')->items());
        // The group totals describe the whole session, not the page.
        $this->assertSame(30, $second->viewData('group')['students']);
    }
}
