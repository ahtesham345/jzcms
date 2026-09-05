<?php

namespace Tests\Feature;

use App\Models\StudentAcademicEnrollment;
use App\Models\StudentPrayerAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers the monthly prayer attendance report.
 *
 * The distinction everything here turns on is Recorded against Expected. A
 * working day offers five prayers; a row exists only once somebody has
 * transcribed one from the paper register. A prayer with no row is
 * Unrecorded, never an absence, and a percentage is always Present over
 * Recorded rather than Present over Expected.
 *
 * The other constants carry over from the entry sheet: Madrassa only, and
 * every figure about a student reached through their enrollment, which is
 * what keeps a historical month reporting the placement held then.
 */
class PrayerAttendanceReportTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    /**
     * August 2026 holds 21 working days, so 105 prayers per student.
     */
    private const AUGUST_WORKING_DAYS = 26;

    private const AUGUST_EXPECTED = 130;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMadrassa();
    }

    /* ---------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------- */

    /**
     * Run the report for August 2026.
     *
     * @param  array<string, mixed>  $filters
     */
    private function report(array $filters = [])
    {
        return $this->get(route('prayer-attendance.reports', array_merge([
            'month' => 8,
            'year' => 2026,
        ], $filters)));
    }

    /**
     * Record one prayer.
     */
    private function prayer(
        StudentAcademicEnrollment $enrollment,
        string $date,
        string $prayerName,
        string $status
    ): StudentPrayerAttendance {
        return StudentPrayerAttendance::create([
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => $date,
            'prayer' => $prayerName,
            'status' => $status,
        ]);
    }

    /**
     * Record all five prayers of one day.
     */
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
     * One student's report row.
     */
    private function rowFor($response, int $studentId): ?object
    {
        return $this->rows($response)->get($studentId);
    }

    /* ---------------------------------------------------------------- */
    /* Access */
    /* ---------------------------------------------------------------- */

    public function test_a_guest_cannot_reach_the_report(): void
    {
        auth()->logout();

        $this->get(route('prayer-attendance.reports'))->assertRedirect(route('login'));
    }

    public function test_an_authenticated_admin_can_reach_the_report(): void
    {
        $response = $this->report();

        $response->assertOk();
        $response->assertSee('Prayer Attendance Reports');
        $response->assertSee('August 2026');
    }

    public function test_the_monthly_sheet_links_to_the_report(): void
    {
        $this->get(route('prayer-attendance.index'))
            ->assertOk()
            ->assertSee('Prayer Reports')
            ->assertSee(route('prayer-attendance.reports'), false);
    }

    /* ---------------------------------------------------------------- */
    /* Who is reported */
    /* ---------------------------------------------------------------- */

    public function test_a_student_with_no_records_still_appears_with_zeroes(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student);

        $response = $this->report();
        $row = $this->rowFor($response, $student->id);

        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->recorded);
        $this->assertSame(0, (int) $row->present);
        $this->assertSame(0, (int) $row->absent);
        $this->assertSame(0, (int) $row->fajr_present);

        // No register, no verdict.
        $this->assertNull(StudentPrayerAttendance::attendancePercentage(0, 0));
        $response->assertSee('N/A');
    }

    public function test_a_school_only_student_is_never_reported(): void
    {
        $madrassa = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($madrassa);

        $schoolOnly = $this->student('Usman Tariq', 'School');
        $this->schoolEnrollment($schoolOnly);

        $response = $this->report();

        $this->assertSame([$madrassa->id], $this->rows($response)->keys()->all());
        $response->assertDontSee('Usman Tariq');
        $this->assertSame(1, $response->viewData('group')['students']);
    }

    public function test_a_hifz_plus_school_student_is_reported_once_through_the_madrassa_enrollment(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->wholeDay($madrassa, self::MONDAY, 'Present');

        $response = $this->report();
        $rows = $this->rows($response);

        // One row, and it is the madrassa placement.
        $this->assertCount(1, $rows);
        $this->assertSame($madrassa->id, (int) $rows->first()->enrollment_id);
        $this->assertNotSame($school->id, (int) $rows->first()->enrollment_id);
        $this->assertSame($this->hifzClass->name, $rows->first()->class_name);
        $this->assertSame(5, (int) $rows->first()->recorded);

        $this->assertSame(1, $response->viewData('group')['students']);
    }

    public function test_a_placement_that_ended_before_the_month_is_not_reported(): void
    {
        $current = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($current);

        $left = $this->student('Zaid Khan');
        $this->madrassaEnrollment($left, ['status' => 'Left', 'end_date' => '2026-06-30']);

        $this->assertSame([$current->id], $this->rows($this->report())->keys()->all());
    }

    public function test_a_placement_held_during_a_past_month_is_still_reported(): void
    {
        $student = $this->student('Ahtesham Shakeel');

        // Completed after August, so August must still report the student.
        $this->madrassaEnrollment($student, [
            'status' => 'Completed',
            'end_date' => '2026-12-31',
        ]);

        $this->assertSame([$student->id], $this->rows($this->report())->keys()->all());
    }

    /* ---------------------------------------------------------------- */
    /* Filters */
    /* ---------------------------------------------------------------- */

    public function test_the_department_class_and_section_filters_narrow_the_report(): void
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

        $this->assertCount(3, $this->rows($this->report(['department_id' => $this->hifz->id])));
        $this->assertCount(0, $this->rows($this->report(['department_id' => $this->darsENizami->id])));

        $this->assertSame(
            [$inNazra->id],
            $this->rows($this->report(['academic_class_id' => $this->nazra->id]))->keys()->all()
        );
        $this->assertSame(
            [$inHifzA->id],
            $this->rows($this->report(['section_id' => $this->hifzA->id]))->keys()->all()
        );
    }

    public function test_the_session_filter_narrows_the_report(): void
    {
        $thisSession = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($thisSession);

        $nextSession = $this->student('Zaid Khan');
        $this->madrassaEnrollment($nextSession, [
            'academic_session_id' => $this->nextSession->id,
            'start_date' => '2026-04-01',
        ]);

        $this->assertSame(
            [$thisSession->id],
            $this->rows($this->report(['academic_session_id' => $this->session->id]))->keys()->all()
        );
        $this->assertSame(
            [$nextSession->id],
            $this->rows($this->report(['academic_session_id' => $this->nextSession->id]))->keys()->all()
        );
    }

    public function test_the_filters_combine_with_and_logic(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student);

        // Each filter matches on its own but not both together, so the
        // report is empty rather than one filter quietly winning.
        $this->assertCount(0, $this->rows($this->report([
            'department_id' => $this->darsENizami->id,
            'academic_class_id' => $this->hifzClass->id,
        ])));

        $this->assertCount(1, $this->rows($this->report([
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
        ])));

        // A search that matches, combined with a class that does not.
        $this->assertCount(0, $this->rows($this->report([
            'search' => 'Ahtesham',
            'academic_class_id' => $this->nazra->id,
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
                $this->rows($this->report(['search' => $term]))->keys()->all(),
                "search for {$term}"
            );
        }
    }

    public function test_the_month_filter_decides_which_records_are_counted(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->wholeDay($enrollment, self::MONDAY, 'Present');
        // 1 September 2026 is a Tuesday.
        $this->wholeDay($enrollment, '2026-09-01', 'Absent');

        $august = $this->report(['month' => 8, 'year' => 2026]);
        $september = $this->report(['month' => 9, 'year' => 2026]);

        $this->assertSame(5, (int) $this->rowFor($august, $student->id)->present);
        $this->assertSame(0, (int) $this->rowFor($august, $student->id)->absent);

        $this->assertSame(0, (int) $this->rowFor($september, $student->id)->present);
        $this->assertSame(5, (int) $this->rowFor($september, $student->id)->absent);
    }

    /* ---------------------------------------------------------------- */
    /* The student-wise numbers */
    /* ---------------------------------------------------------------- */

    public function test_each_prayer_is_counted_separately(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // Fajr absent, everything else present, on one day.
        $this->prayer($enrollment, self::MONDAY, 'Fajr', 'Absent');
        $this->prayer($enrollment, self::MONDAY, 'Zuhr', 'Present');
        $this->prayer($enrollment, self::MONDAY, 'Asr', 'Present');
        $this->prayer($enrollment, self::MONDAY, 'Maghrib', 'Present');
        $this->prayer($enrollment, self::MONDAY, 'Isha', 'Present');

        // Fajr present the next day too.
        $this->prayer($enrollment, self::TUESDAY, 'Fajr', 'Present');

        $row = $this->rowFor($this->report(), $student->id);

        $this->assertSame(1, (int) $row->fajr_present);
        $this->assertSame(1, (int) $row->fajr_absent);
        $this->assertSame(1, (int) $row->zuhr_present);
        $this->assertSame(0, (int) $row->zuhr_absent);
        $this->assertSame(1, (int) $row->asr_present);
        $this->assertSame(1, (int) $row->maghrib_present);
        $this->assertSame(1, (int) $row->isha_present);

        $this->assertSame(6, (int) $row->recorded);
        $this->assertSame(5, (int) $row->present);
        $this->assertSame(1, (int) $row->absent);
    }

    public function test_the_student_percentage_is_present_over_recorded(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // Fourteen days of five prayers is seventy recorded. Eleven whole
        // days present plus three absent gives the worked example.
        $this->wholeDay($enrollment, self::MONDAY, 'Present');
        $this->wholeDay($enrollment, self::TUESDAY, 'Present');
        $this->wholeDay($enrollment, self::WEDNESDAY, 'Absent');

        $row = $this->rowFor($this->report(), $student->id);

        $this->assertSame(15, (int) $row->recorded);
        $this->assertSame(10, (int) $row->present);
        $this->assertSame(5, (int) $row->absent);

        // Ten of fifteen, not ten of a hundred and five.
        $this->assertSame(66.67, StudentPrayerAttendance::attendancePercentage(10, 5));
        $this->assertSame('66.67%', StudentPrayerAttendance::formatPercentage(10, 5));
    }

    public function test_unrecorded_prayers_are_never_counted_as_absent(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // One prayer on one day. The other 104 of the month are untouched.
        $this->prayer($enrollment, self::MONDAY, 'Fajr', 'Present');

        $row = $this->rowFor($this->report(), $student->id);

        $this->assertSame(1, (int) $row->recorded);
        $this->assertSame(1, (int) $row->present);
        // Not 104.
        $this->assertSame(0, (int) $row->absent);
        $this->assertSame('100.00%', StudentPrayerAttendance::formatPercentage(1, 0));
    }

    public function test_the_row_names_the_placement_the_records_were_made_under(): void
    {
        $student = $this->student('Ahtesham Shakeel');

        $nazra = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->hifzA->id,
        ]);
        $this->wholeDay($nazra, self::MONDAY, 'Present');

        // Promoted afterwards into a different class and section. The
        // promotion is what completes the Nazra placement and dates its
        // end, so August still falls inside it.
        $student->promote([
            'academic_track' => 'Madrassa',
            'academic_session_id' => $this->nextSession->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'promotion_date' => '2027-04-01',
        ]);

        $row = $this->rowFor($this->report(), $student->id);

        // The August report still reads Nazra / Hifz-A.
        $this->assertSame($nazra->id, (int) $row->enrollment_id);
        $this->assertSame('Nazra', $row->class_name);
        $this->assertSame('Hifz-A', $row->section_name);
        $this->assertSame(5, (int) $row->present);
    }

    /* ---------------------------------------------------------------- */
    /* Expected, recorded and unrecorded */
    /* ---------------------------------------------------------------- */

    public function test_expected_prayers_are_working_days_times_five(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $response = $this->report();

        $this->assertSame(self::AUGUST_WORKING_DAYS, $response->viewData('workingDays'));
        $this->assertSame(self::AUGUST_EXPECTED, $response->viewData('expectedPerStudent'));
        $this->assertSame(self::AUGUST_EXPECTED, $response->viewData('group')['expected']);
    }

    public function test_sundays_are_excluded_from_expected_prayers(): void
    {
        // August 2026 has 31 days, five of which are Sundays. Its Saturdays
        // are worked like any other day.
        $this->assertSame(26, StudentPrayerAttendance::prayerDaysInMonth(2026, 8));
        $this->assertSame(130, StudentPrayerAttendance::expectedPrayersInMonth(2026, 8));

        // February 2026 has 28 days, four Sundays and twenty-four worked.
        $this->assertSame(24, StudentPrayerAttendance::prayerDaysInMonth(2026, 2));
        $this->assertSame(120, StudentPrayerAttendance::expectedPrayersInMonth(2026, 2));
    }

    public function test_the_worked_example_from_the_specification(): void
    {
        $student = $this->student('Ahtesham Shakeel');

        // Enrolled from January, so the placement covers February. A
        // student whose placement had not started yet would rightly not be
        // in the month's report at all.
        $enrollment = $this->madrassaEnrollment($student, ['start_date' => '2026-01-01']);

        // February 2026: twenty-four working days, so a hundred and twenty
        // expected. Enter seventy - fifty-five present, fifteen absent.
        $dates = [];
        $cursor = Carbon::create(2026, 2, 1);

        while ((int) $cursor->month === 2) {
            if (StudentPrayerAttendance::isPrayerDay($cursor)) {
                $dates[] = $cursor->format('Y-m-d');
            }

            $cursor->addDay();
        }

        $this->assertCount(24, $dates);

        $written = 0;

        foreach ($dates as $date) {
            foreach (StudentPrayerAttendance::PRAYERS as $prayerName) {
                if ($written >= 70) {
                    break 2;
                }

                $this->prayer($enrollment, $date, $prayerName, $written < 55 ? 'Present' : 'Absent');
                $written++;
            }
        }

        $group = $this->report(['month' => 2, 'year' => 2026])->viewData('group');

        $this->assertSame(120, $group['expected']);
        $this->assertSame(70, $group['recorded']);
        $this->assertSame(50, $group['unrecorded']);
        $this->assertSame(55, $group['present']);
        $this->assertSame(15, $group['absent']);
        $this->assertSame('78.57%', $group['percentage']);
    }

    /* ---------------------------------------------------------------- */
    /* The group summary */
    /* ---------------------------------------------------------------- */

    public function test_the_group_summary_counts_students_with_and_without_records(): void
    {
        $recorded = $this->student('Ahtesham Shakeel');
        $this->wholeDay($this->madrassaEnrollment($recorded), self::MONDAY, 'Present');

        $this->madrassaEnrollment($this->student('Zaid Khan'));
        $this->madrassaEnrollment($this->student('Owais Raza'));

        $group = $this->report()->viewData('group');

        $this->assertSame(3, $group['students']);
        $this->assertSame(1, $group['students_with_records']);
        $this->assertSame(2, $group['students_without_records']);

        // Three students times a hundred and thirty.
        $this->assertSame(390, $group['expected']);
        $this->assertSame(5, $group['recorded']);
        $this->assertSame(385, $group['unrecorded']);
    }

    public function test_the_group_percentage_is_computed_from_totals_not_averaged(): void
    {
        // One student with a single present prayer (100%), another with
        // ninety-nine of a hundred absent (1%). Averaging the two
        // percentages would give 50.5%; the honest figure is 2 of 101.
        $first = $this->student('Ahtesham Shakeel');
        $firstEnrollment = $this->madrassaEnrollment($first);
        $this->prayer($firstEnrollment, self::MONDAY, 'Fajr', 'Present');

        $second = $this->student('Zaid Khan');
        $secondEnrollment = $this->madrassaEnrollment($second);

        $written = 0;

        foreach (StudentPrayerAttendance::monthDays(2026, 8) as $day) {
            if ($day['is_off_day']) {
                continue;
            }

            foreach (StudentPrayerAttendance::PRAYERS as $prayerName) {
                if ($written >= 100) {
                    break 2;
                }

                $this->prayer($secondEnrollment, $day['date'], $prayerName, $written === 0 ? 'Present' : 'Absent');
                $written++;
            }
        }

        $group = $this->report()->viewData('group');

        $this->assertSame(101, $group['recorded']);
        $this->assertSame(2, $group['present']);
        $this->assertSame(99, $group['absent']);
        // 2 / 101, not the mean of 100% and 1%.
        $this->assertSame('1.98%', $group['percentage']);
    }

    public function test_the_group_breakdown_covers_each_of_the_five_prayers(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->prayer($enrollment, self::MONDAY, 'Fajr', 'Present');
        $this->prayer($enrollment, self::MONDAY, 'Zuhr', 'Absent');
        $this->prayer($enrollment, self::TUESDAY, 'Fajr', 'Absent');

        $breakdown = $this->report()->viewData('group')['by_prayer'];

        // In the order they are prayed, never alphabetically.
        $this->assertSame(
            ['Fajr', 'Zuhr', 'Asr', 'Maghrib', 'Isha'],
            array_keys($breakdown)
        );

        // One student times twenty-six working days.
        $this->assertSame(26, $breakdown['Fajr']['expected']);
        $this->assertSame(2, $breakdown['Fajr']['recorded']);
        $this->assertSame(24, $breakdown['Fajr']['unrecorded']);
        $this->assertSame(1, $breakdown['Fajr']['present']);
        $this->assertSame(1, $breakdown['Fajr']['absent']);
        $this->assertSame('50.00%', $breakdown['Fajr']['percentage']);

        $this->assertSame(0, $breakdown['Zuhr']['present']);
        $this->assertSame(1, $breakdown['Zuhr']['absent']);
        $this->assertSame('0.00%', $breakdown['Zuhr']['percentage']);

        // Never transcribed, so no verdict.
        $this->assertSame(0, $breakdown['Isha']['recorded']);
        $this->assertSame(26, $breakdown['Isha']['unrecorded']);
        $this->assertSame('N/A', $breakdown['Isha']['percentage']);
    }

    public function test_the_group_totals_exclude_school_enrollments(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->wholeDay($madrassa, self::MONDAY, 'Present');
        // Planted on the school side. A prayer should never be written
        // there, but if one were it must not reach any total.
        $this->wholeDay($school, self::MONDAY, 'Absent');

        $group = $this->report()->viewData('group');

        $this->assertSame(1, $group['students']);
        $this->assertSame(5, $group['recorded']);
        $this->assertSame(5, $group['present']);
        $this->assertSame(0, $group['absent']);
        $this->assertSame(self::AUGUST_EXPECTED, $group['expected']);
    }

    /* ---------------------------------------------------------------- */
    /* Navigation */
    /* ---------------------------------------------------------------- */

    public function test_a_student_row_opens_their_existing_history_on_the_reported_month(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student);

        $response = $this->report(['academic_session_id' => $this->session->id]);

        $expected = route('students.prayer-attendance', [
            'student' => $student->id,
            'academic_session_id' => $this->session->id,
            'month' => 8,
            'year' => 2026,
        ]);

        // Asserted on the escaped markup, since Blade writes the ampersands
        // as entities.
        $response->assertSee(e($expected), false);

        // And the link actually opens that student's history.
        $this->get($expected)->assertOk()->assertSee('Ahtesham Shakeel');
    }

    /* ---------------------------------------------------------------- */
    /* Read-only safety */
    /* ---------------------------------------------------------------- */

    public function test_running_the_report_writes_nothing(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->wholeDay($enrollment, self::MONDAY, 'Present');

        $before = StudentPrayerAttendance::orderBy('id')->get()
            ->map(fn ($row) => $row->id.'|'.$row->status.'|'.$row->updated_at?->format('Y-m-d H:i:s'))
            ->all();

        $this->report()->assertOk();
        $this->report(['month' => 2, 'year' => 2026])->assertOk();
        $this->report(['department_id' => $this->hifz->id])->assertOk();

        $after = StudentPrayerAttendance::orderBy('id')->get()
            ->map(fn ($row) => $row->id.'|'.$row->status.'|'.$row->updated_at?->format('Y-m-d H:i:s'))
            ->all();

        $this->assertSame($before, $after);
        $this->assertDatabaseCount('student_prayer_attendances', 5);
    }

    /* ---------------------------------------------------------------- */
    /* Performance */
    /* ---------------------------------------------------------------- */

    public function test_the_query_count_does_not_grow_with_the_students(): void
    {
        $counts = [];

        foreach ([5, 15, 30] as $target) {
            $this->fillClassTo($target);
            $counts[$target] = $this->countReportQueries();
        }

        // Aggregating in SQL is what keeps these equal: the alternative
        // would be a month of prayers per student read into PHP.
        $this->assertSame($counts[5], $counts[15]);
        $this->assertSame($counts[5], $counts[30]);
        $this->assertLessThan(20, $counts[30]);
    }

    /**
     * Add madrassa students until the report covers a given number.
     *
     * Most of them get prayers recorded, so the count covers the rows that
     * aggregate real records as well as the empty ones.
     */
    private function fillClassTo(int $target): void
    {
        $existing = $this->report()->viewData('group')['students'];

        for ($index = $existing; $index < $target; $index++) {
            $enrollment = $this->madrassaEnrollment($this->student('Student '.$index.' '.uniqid()));

            if ($index % 3 !== 0) {
                $this->wholeDay($enrollment, self::MONDAY, 'Present');
                $this->wholeDay($enrollment, self::TUESDAY, 'Absent');
            }
        }
    }

    /**
     * Count the queries one render of the report costs.
     *
     * A warm-up request first: the permission and role lookups are cached
     * per process, so measuring the first request of a run would count them
     * once and never again.
     */
    private function countReportQueries(): int
    {
        $this->report()->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->report()->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_the_report_does_not_read_the_other_registers(): void
    {
        $this->wholeDay($this->madrassaEnrollment($this->student('Ahtesham Shakeel')), self::MONDAY);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->report()->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        DB::disableQueryLog();

        // Three registers, three tables. None is consulted to fill in
        // another.
        $this->assertStringContainsString('student_prayer_attendances', $queries);
        $this->assertStringNotContainsString('`student_attendances`', $queries);
        $this->assertStringNotContainsString('madrassa_daily_records', $queries);
    }

    /* ---------------------------------------------------------------- */
    /* Pagination */
    /* ---------------------------------------------------------------- */

    public function test_the_student_report_is_paginated_and_keeps_its_filters(): void
    {
        for ($index = 0; $index < 30; $index++) {
            $this->madrassaEnrollment($this->student('Student '.$index.' '.uniqid()));
        }

        $first = $this->report(['department_id' => $this->hifz->id]);

        $first->assertOk();
        $first->assertSee('Showing 1 to 25 of 30 Madrassa students');
        $this->assertCount(25, $first->viewData('students')->items());
        $first->assertSee('department_id='.$this->hifz->id, false);

        $second = $this->report(['department_id' => $this->hifz->id, 'page' => 2]);

        $this->assertCount(5, $second->viewData('students')->items());
        // The group totals describe the whole filtered group, not the page.
        $this->assertSame(30, $second->viewData('group')['students']);
    }
}
