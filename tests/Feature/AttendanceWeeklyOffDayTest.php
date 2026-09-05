<?php

namespace Tests\Feature;

use App\Models\MadrassaDailyRecord;
use App\Models\StudentAttendance;
use App\Models\StudentPrayerAttendance;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers the weekly attendance calendar: Monday to Saturday, Sunday off.
 *
 * The institution used to sit five days and take Saturday off with Sunday.
 * It now sits six: Sunday is the only weekly off day. This describes that
 * rule directly - which days are workable, which are refused, and what the
 * counts come to - rather than through any one report, because everything
 * that counts a working day reads it from the same place.
 *
 * August 2026 is the month used throughout: 31 days with five Sundays (2nd,
 * 9th, 16th, 23rd, 30th), so 26 working days. Its 1st is a Saturday.
 */
class AttendanceWeeklyOffDayTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    private const YEAR = 2026;

    private const MONTH = 8;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMadrassa();
    }

    /* ---------------------------------------------------------------- */
    /* The calendar itself */
    /* ---------------------------------------------------------------- */

    public function test_sunday_is_the_only_off_day(): void
    {
        $this->assertSame([CarbonInterface::SUNDAY], StudentAttendance::OFF_DAYS);
    }

    public function test_saturday_is_a_working_day(): void
    {
        $this->assertTrue(StudentAttendance::isAttendanceDay(self::SATURDAY));
        $this->assertNull(StudentAttendance::offDayName(self::SATURDAY));
    }

    public function test_sunday_is_not_a_working_day(): void
    {
        $this->assertFalse(StudentAttendance::isAttendanceDay(self::SUNDAY));
        $this->assertSame('Sunday', StudentAttendance::offDayName(self::SUNDAY));
    }

    public function test_monday_to_friday_remain_working_days(): void
    {
        foreach ([self::MONDAY, self::TUESDAY, self::WEDNESDAY, self::THURSDAY, self::FRIDAY] as $day) {
            $this->assertTrue(
                StudentAttendance::isAttendanceDay($day),
                "{$day} should be a working day."
            );
            $this->assertNull(StudentAttendance::offDayName($day));
        }
    }

    public function test_every_day_of_a_week_but_sunday_is_worked(): void
    {
        // Monday 3 August to Sunday 9 August 2026.
        $cursor = Carbon::parse(self::MONDAY);
        $worked = 0;

        for ($i = 0; $i < 7; $i++) {
            if (StudentAttendance::isAttendanceDay($cursor)) {
                $worked++;
                $this->assertNotSame('Sunday', $cursor->format('l'));
            } else {
                $this->assertSame('Sunday', $cursor->format('l'));
            }

            $cursor->addDay();
        }

        $this->assertSame(6, $worked);
    }

    /* ---------------------------------------------------------------- */
    /* Counting */
    /* ---------------------------------------------------------------- */

    public function test_the_month_calendar_marks_only_sundays_off(): void
    {
        $days = collect(StudentAttendance::monthDays(self::YEAR, self::MONTH));

        $this->assertCount(31, $days);

        $offDays = $days->where('is_off_day', true)->pluck('date')->values()->all();

        $this->assertSame(
            ['2026-08-02', '2026-08-09', '2026-08-16', '2026-08-23', '2026-08-30'],
            $offDays
        );
    }

    public function test_teaching_days_in_a_month_count_saturdays(): void
    {
        // 31 days less five Sundays.
        $this->assertSame(26, StudentAttendance::teachingDaysInMonth(self::YEAR, self::MONTH));

        // September 2026: 30 days, four Sundays.
        $this->assertSame(26, StudentAttendance::teachingDaysInMonth(2026, 9));

        // February 2026: 28 days, four Sundays.
        $this->assertSame(24, StudentAttendance::teachingDaysInMonth(2026, 2));
    }

    public function test_teaching_days_in_a_range_count_saturdays(): void
    {
        // A whole week is six.
        $this->assertSame(6, StudentAttendance::teachingDaysBetween(self::MONDAY, '2026-08-09'));

        // A Saturday on its own is one working day; a Sunday is none.
        $this->assertSame(1, StudentAttendance::teachingDaysBetween(self::SATURDAY, self::SATURDAY));
        $this->assertSame(0, StudentAttendance::teachingDaysBetween(self::SUNDAY, self::SUNDAY));

        // Saturday and Sunday together come to the Saturday alone.
        $this->assertSame(1, StudentAttendance::teachingDaysBetween(self::SATURDAY, self::SUNDAY));

        // A whole month, matching the monthly count.
        $this->assertSame(26, StudentAttendance::teachingDaysBetween('2026-08-01', '2026-08-31'));
    }

    public function test_opportunities_follow_the_track_over_the_new_week(): void
    {
        $teachingDays = StudentAttendance::teachingDaysInMonth(self::YEAR, self::MONTH);

        // School registers once a day, the madrassa three times.
        $this->assertSame(26, StudentAttendance::opportunitiesForTrack($teachingDays, 'School'));
        $this->assertSame(78, StudentAttendance::opportunitiesForTrack($teachingDays, 'Madrassa'));
    }

    /* ---------------------------------------------------------------- */
    /* Recording on a Saturday */
    /* ---------------------------------------------------------------- */

    public function test_school_attendance_can_be_recorded_on_a_saturday(): void
    {
        $enrollment = $this->schoolEnrollment();

        $this->saveAttendance($enrollment, 'School', self::SATURDAY, 'Morning')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_attendances', [
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => self::SATURDAY,
            'attendance_period' => 'Morning',
        ]);
    }

    public function test_every_madrassa_period_can_be_recorded_on_a_saturday(): void
    {
        foreach (StudentAttendance::PERIODS_BY_TRACK['Madrassa'] as $period) {
            $enrollment = $this->madrassaEnrollment($this->student("Student {$period}"));

            $this->saveAttendance($enrollment, 'Madrassa', self::SATURDAY, $period)
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('student_attendances', [
                'student_academic_enrollment_id' => $enrollment->id,
                'attendance_date' => self::SATURDAY,
                'attendance_period' => $period,
            ]);
        }

        $this->assertDatabaseCount('student_attendances', 3);
    }

    /* ---------------------------------------------------------------- */
    /* Sunday stays closed */
    /* ---------------------------------------------------------------- */

    public function test_school_attendance_cannot_be_recorded_on_a_sunday(): void
    {
        $enrollment = $this->schoolEnrollment();

        $this->saveAttendance($enrollment, 'School', self::SUNDAY, 'Morning')
            ->assertSessionHasErrors('attendance.0.attendance_date');

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_no_madrassa_period_can_be_recorded_on_a_sunday(): void
    {
        $enrollment = $this->madrassaEnrollment();

        foreach (StudentAttendance::PERIODS_BY_TRACK['Madrassa'] as $period) {
            $this->saveAttendance($enrollment, 'Madrassa', self::SUNDAY, $period)
                ->assertSessionHasErrors('attendance.0.attendance_date');
        }

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_the_refusal_names_sunday_as_the_weekly_off_day(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $response = $this->saveAttendance($enrollment, 'Madrassa', self::SUNDAY, 'Morning');

        $errors = session('errors')->get('attendance.0.attendance_date');

        $this->assertStringContainsString('Sunday', $errors[0]);
        $this->assertStringContainsString('weekly off day', $errors[0]);
        $this->assertStringNotContainsString('Saturday', $errors[0]);

        $response->assertRedirect();
    }

    /* ---------------------------------------------------------------- */
    /* The rule is shared, not copied */
    /* ---------------------------------------------------------------- */

    public function test_prayer_attendance_reads_the_same_calendar(): void
    {
        $this->assertTrue(StudentPrayerAttendance::isPrayerDay(self::SATURDAY));
        $this->assertFalse(StudentPrayerAttendance::isPrayerDay(self::SUNDAY));
        $this->assertSame('Sunday', StudentPrayerAttendance::offDayName(self::SUNDAY));
        $this->assertSame(26, StudentPrayerAttendance::prayerDaysInMonth(self::YEAR, self::MONTH));
    }

    public function test_the_madrassa_daily_record_reads_the_same_calendar(): void
    {
        $this->assertTrue(MadrassaDailyRecord::isRecordableDay(self::SATURDAY));
        $this->assertFalse(MadrassaDailyRecord::isRecordableDay(self::SUNDAY));
        $this->assertSame('Sunday', MadrassaDailyRecord::offDayName(self::SUNDAY));
        $this->assertNull(MadrassaDailyRecord::offDayName(self::SATURDAY));
    }

    /* ---------------------------------------------------------------- */
    /* Reports count the Saturday */
    /* ---------------------------------------------------------------- */

    public function test_the_monthly_sheet_summary_counts_saturdays(): void
    {
        $this->madrassaEnrollment();

        $summary = $this->openSheet('Madrassa', 'Morning')->assertOk()->viewData('summary');

        $this->assertSame(26, $summary['teaching_days']);
    }

    public function test_the_printed_sheet_shows_saturday_as_a_working_column(): void
    {
        $this->madrassaEnrollment();

        $response = $this->get(route('attendance.print', [
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'attendance_period' => 'Morning',
            'month' => self::MONTH,
            'year' => self::YEAR,
        ]))->assertOk();

        $days = collect($response->viewData('days'))->keyBy('date');

        $this->assertFalse($days[self::SATURDAY]['is_off_day']);
        $this->assertTrue($days[self::SUNDAY]['is_off_day']);

        // One OFF per Sunday on the single student row.
        $this->assertSame(5, substr_count($response->getContent(), '>OFF</td>'));
    }

    /* ---------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------- */

    private function openSheet(string $track, string $period)
    {
        return $this->get(route('attendance.index', $this->group($track, $period)));
    }

    /**
     * @return array<string, mixed>
     */
    private function group(string $track, string $period): array
    {
        $madrassa = $track === 'Madrassa';

        return [
            'academic_session_id' => $this->session->id,
            'academic_track' => $track,
            'department_id' => $madrassa ? $this->hifz->id : $this->school->id,
            'academic_class_id' => $madrassa ? $this->hifzClass->id : $this->primary->id,
            'section_id' => $madrassa ? $this->hifzA->id : $this->primaryB->id,
            'attendance_period' => $period,
            'month' => self::MONTH,
            'year' => self::YEAR,
        ];
    }

    /**
     * Post one marked cell.
     *
     * The sheet travels as one JSON field, the same way the browser sends
     * it, so this exercises the real submission shape.
     */
    private function saveAttendance($enrollment, string $track, string $date, string $period)
    {
        return $this->post(route('attendance.store'), [
            ...$this->group($track, $period),
            'sheet' => json_encode([[
                'student_academic_enrollment_id' => $enrollment->id,
                'attendance_date' => $date,
                'status' => 'Present',
                'absence_reason' => null,
            ]]),
        ]);
    }
}
