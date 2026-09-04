<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentPrayerAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers one student's prayer attendance history.
 *
 * A read-only page over what the monthly sheet has already recorded. Three
 * things must keep being true.
 *
 * It is Madrassa only: a school enrollment is not reachable from this page
 * whatever the query string says, and a school-only student has no prayer
 * register at all.
 *
 * It is one student's: the enrollment ids are derived from the route's
 * student, so no filter can reach across to somebody else's records.
 *
 * And it writes nothing. Drawing a month of five prayers must leave the
 * table exactly as it found it - an unmarked prayer is one nobody has
 * transcribed, not one to be filled in on display.
 */
class StudentPrayerAttendanceHistoryTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMadrassa();
    }

    /* ---------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------- */

    /**
     * Open a student's prayer history.
     *
     * @param  array<string, mixed>  $filters
     */
    private function history(Student $student, array $filters = [])
    {
        return $this->get(route('students.prayer-attendance', ['student' => $student->id] + $filters));
    }

    /**
     * Record one prayer directly, bypassing the entry sheet.
     */
    private function prayer(
        StudentAcademicEnrollment $enrollment,
        string $date = self::MONDAY,
        string $prayerName = StudentPrayerAttendance::PRAYER_FAJR,
        string $status = StudentPrayerAttendance::STATUS_PRESENT,
        ?string $reason = null
    ): StudentPrayerAttendance {
        return StudentPrayerAttendance::create([
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => $date,
            'prayer' => $prayerName,
            'status' => $status,
            'absence_reason' => $reason,
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
     * The records the page listed, in the order it listed them.
     *
     * @return Collection<int, StudentPrayerAttendance>
     */
    private function listed($response)
    {
        return $response->viewData('records')->getCollection();
    }

    /**
     * The grid row for one date.
     *
     * @return array<string, mixed>|null
     */
    private function gridRow($response, string $date): ?array
    {
        foreach ($response->viewData('grid') as $row) {
            if ($row['date'] === $date) {
                return $row;
            }
        }

        return null;
    }

    /* ---------------------------------------------------------------- */
    /* Access */
    /* ---------------------------------------------------------------- */

    public function test_a_guest_cannot_reach_the_history(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->prayer($this->madrassaEnrollment($student));

        auth()->logout();

        $this->history($student)->assertRedirect(route('login'));
    }

    public function test_an_authenticated_admin_can_reach_the_history(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->prayer($this->madrassaEnrollment($student));

        $response = $this->history($student);

        $response->assertOk();
        $response->assertSee('Ahtesham Shakeel');
        $response->assertSee('Prayer Attendance History');
    }

    /* ---------------------------------------------------------------- */
    /* Whose records the page shows */
    /* ---------------------------------------------------------------- */

    public function test_a_madrassa_students_history_is_shown(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->wholeDay($enrollment, self::MONDAY);

        $response = $this->history($student);

        $this->assertCount(5, $this->listed($response));
        $this->assertTrue($response->viewData('hasMadrassaEnrollment'));
    }

    public function test_a_school_only_student_has_no_prayer_register(): void
    {
        $student = $this->student('Usman Tariq', 'School');
        $school = $this->schoolEnrollment($student);

        // A prayer written against a school enrollment should never exist,
        // but if one somehow did the page must still not surface it.
        $this->prayer($school);

        $response = $this->history($student);

        $response->assertOk();
        $this->assertFalse($response->viewData('hasMadrassaEnrollment'));
        $this->assertCount(0, $this->listed($response));
        $this->assertSame(0, $response->viewData('summary')['total']);
        $response->assertSee('This student has no Madrassa enrollment.');
    }

    public function test_a_hifz_plus_school_student_shows_only_madrassa_prayers(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->prayer($madrassa, self::MONDAY, 'Fajr');
        // Planted on the school side to prove the track condition is what
        // excludes it, not the absence of data.
        $this->prayer($school, self::MONDAY, 'Zuhr');

        $response = $this->history($student);

        $listed = $this->listed($response);

        $this->assertCount(1, $listed);
        $this->assertSame($madrassa->id, $listed->first()->student_academic_enrollment_id);
        $this->assertSame('Fajr', $listed->first()->prayer);
        $this->assertSame(1, $response->viewData('summary')['total']);
    }

    public function test_another_students_records_never_appear(): void
    {
        $mine = $this->student('Ahtesham Shakeel');
        $theirs = $this->student('Zaid Khan');

        $myEnrollment = $this->madrassaEnrollment($mine);
        $theirEnrollment = $this->madrassaEnrollment($theirs);

        $this->prayer($myEnrollment, self::MONDAY, 'Fajr');
        $this->wholeDay($theirEnrollment, self::MONDAY);

        $listed = $this->listed($this->history($mine));

        $this->assertCount(1, $listed);
        $this->assertSame($myEnrollment->id, $listed->first()->student_academic_enrollment_id);
    }

    public function test_a_manipulated_session_filter_cannot_reach_another_student(): void
    {
        $mine = $this->student('Ahtesham Shakeel');
        $theirs = $this->student('Zaid Khan');

        $this->prayer($this->madrassaEnrollment($mine), self::MONDAY, 'Fajr');

        // The other student is the only one enrolled in the next session.
        $theirEnrollment = $this->madrassaEnrollment($theirs, [
            'academic_session_id' => $this->nextSession->id,
            'start_date' => '2027-04-01',
        ]);
        $this->prayer($theirEnrollment, '2027-08-02', 'Fajr');

        // Asking for a session this student was never in narrows nothing
        // rather than reaching across.
        $response = $this->history($mine, ['academic_session_id' => $this->nextSession->id]);

        $response->assertOk();
        $this->assertNull($response->viewData('filters')['academic_session_id']);
        $this->assertCount(1, $this->listed($response));
        $this->assertSame($mine->id, $this->listed($response)->first()->studentAcademicEnrollment->student_id);
    }

    public function test_the_session_filter_only_offers_the_students_own_madrassa_sessions(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student);
        // A school enrollment in the same session must not add a session
        // option that would suggest school prayers exist.
        $this->schoolEnrollment($student);

        $sessions = $this->history($student)->viewData('academicSessions');

        $this->assertCount(1, $sessions);
        $this->assertSame($this->session->id, $sessions->first()->id);
    }

    /* ---------------------------------------------------------------- */
    /* The five prayers and their order */
    /* ---------------------------------------------------------------- */

    #[DataProvider('everyPrayer')]
    public function test_every_prayer_appears_in_the_history(string $prayerName): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->prayer($this->madrassaEnrollment($student), self::MONDAY, $prayerName);

        $response = $this->history($student);

        $this->assertSame([$prayerName], $this->listed($response)->pluck('prayer')->all());
        $response->assertSee($prayerName);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function everyPrayer(): array
    {
        return [
            'Fajr' => ['Fajr'],
            'Zuhr' => ['Zuhr'],
            'Asr' => ['Asr'],
            'Maghrib' => ['Maghrib'],
            'Isha' => ['Isha'],
        ];
    }

    public function test_prayers_are_ordered_as_they_are_prayed_not_alphabetically(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // Inserted out of order on purpose: alphabetically this would come
        // back Asr, Fajr, Isha, Maghrib, Zuhr.
        foreach (['Isha', 'Asr', 'Maghrib', 'Fajr', 'Zuhr'] as $prayerName) {
            $this->prayer($enrollment, self::MONDAY, $prayerName);
        }

        $this->assertSame(
            ['Fajr', 'Zuhr', 'Asr', 'Maghrib', 'Isha'],
            $this->listed($this->history($student))->pluck('prayer')->all()
        );
    }

    public function test_the_history_is_newest_day_first_then_by_prayer_order(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->wholeDay($enrollment, self::MONDAY);
        $this->wholeDay($enrollment, self::TUESDAY);

        $listed = $this->listed($this->history($student));

        $rows = $listed->map(fn ($record) => $record->attendance_date->format('Y-m-d').' '.$record->prayer)->all();

        $this->assertSame([
            self::TUESDAY.' Fajr',
            self::TUESDAY.' Zuhr',
            self::TUESDAY.' Asr',
            self::TUESDAY.' Maghrib',
            self::TUESDAY.' Isha',
            self::MONDAY.' Fajr',
            self::MONDAY.' Zuhr',
            self::MONDAY.' Asr',
            self::MONDAY.' Maghrib',
            self::MONDAY.' Isha',
        ], $rows);
    }

    /* ---------------------------------------------------------------- */
    /* Status and reason */
    /* ---------------------------------------------------------------- */

    public function test_present_and_absent_carry_their_own_badges(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $present = $this->prayer($enrollment, self::MONDAY, 'Fajr', 'Present');
        $absent = $this->prayer($enrollment, self::MONDAY, 'Zuhr', 'Absent', 'Sick');

        $response = $this->history($student);

        $this->assertStringContainsString('green', $present->statusBadgeClasses());
        $this->assertStringContainsString('red', $absent->statusBadgeClasses());

        $response->assertSee('Present');
        $response->assertSee('Absent');
    }

    public function test_an_absence_reason_is_displayed(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->prayer($this->madrassaEnrollment($student), self::MONDAY, 'Fajr', 'Absent', 'Family issue');

        $this->history($student)->assertOk()->assertSee('Family issue');
    }

    public function test_an_absence_with_no_reason_says_so_rather_than_inventing_one(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->prayer($this->madrassaEnrollment($student), self::MONDAY, 'Fajr', 'Absent');

        $response = $this->history($student);

        $response->assertSee('No reason provided');
        $this->assertNull($this->listed($response)->first()->absence_reason);
    }

    public function test_a_present_prayer_never_shows_a_reason(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // The entry sheet clears the reason on Present, so a stored Present
        // row carries none. The page must not surface one either way.
        $record = $this->prayer($enrollment, self::MONDAY, 'Fajr', 'Present');

        $response = $this->history($student);

        $this->assertNull($record->fresh()->absence_reason);
        $response->assertDontSee('No reason provided');
    }

    /* ---------------------------------------------------------------- */
    /* The monthly grid */
    /* ---------------------------------------------------------------- */

    #[DataProvider('monthLengths')]
    public function test_the_grid_draws_every_day_of_the_month(int $year, int $month, int $expectedDays): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student, ['start_date' => '2020-01-01']);

        $grid = $this->history($student, ['month' => $month, 'year' => $year])->viewData('grid');

        $this->assertCount($expectedDays, $grid);
        $this->assertSame(1, $grid[0]['day']);
        $this->assertSame($expectedDays, $grid[$expectedDays - 1]['day']);
    }

    /**
     * @return array<string, array<int, int>>
     */
    public static function monthLengths(): array
    {
        return [
            'February, 28 days' => [2026, 2, 28],
            'February, 29 days in a leap year' => [2028, 2, 29],
            'April, 30 days' => [2026, 4, 30],
            'August, 31 days' => [2026, 8, 31],
        ];
    }

    public function test_the_grid_shows_saturday_and_sunday_as_off(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student);

        $response = $this->history($student, ['month' => 8, 'year' => 2026]);

        foreach ([self::SATURDAY, self::SUNDAY] as $weekend) {
            $row = $this->gridRow($response, $weekend);

            $this->assertNotNull($row);
            $this->assertTrue($row['is_off_day']);

            // No status at all, on any of the five.
            foreach (StudentPrayerAttendance::PRAYERS as $prayerName) {
                $this->assertNull($row['cells'][$prayerName]);
            }
        }

        $response->assertSee('OFF');
    }

    public function test_a_weekend_is_never_counted_as_absent(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->prayer($this->madrassaEnrollment($student), self::MONDAY, 'Fajr', 'Present');

        $response = $this->history($student, ['month' => 8, 'year' => 2026]);

        $summary = $response->viewData('summary');

        // August 2026 holds ten weekend days. None of them is in any count.
        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['present']);
        $this->assertSame(0, $summary['absent']);
    }

    public function test_a_prayer_with_no_record_shows_as_unmarked(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->prayer($enrollment, self::MONDAY, 'Fajr', 'Present');
        $this->prayer($enrollment, self::MONDAY, 'Zuhr', 'Absent', 'Sick');

        $row = $this->gridRow($this->history($student, ['month' => 8, 'year' => 2026]), self::MONDAY);

        $this->assertSame('Present', $row['cells']['Fajr']['status']);
        $this->assertSame('Absent', $row['cells']['Zuhr']['status']);
        $this->assertSame('Sick', $row['cells']['Zuhr']['reason']);

        // Never transcribed. Not an absence.
        $this->assertNull($row['cells']['Asr']['status']);
        $this->assertNull($row['cells']['Maghrib']['status']);
        $this->assertNull($row['cells']['Isha']['status']);
    }

    public function test_the_grid_defaults_to_the_most_recent_month_with_records(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->prayer($enrollment, self::MONDAY, 'Fajr');
        // 1 September 2026 is a Tuesday.
        $this->prayer($enrollment, '2026-09-01', 'Fajr');

        $response = $this->history($student);

        $response->assertOk();
        $this->assertSame('September 2026', $response->viewData('gridMonthLabel'));
        $this->assertTrue($response->viewData('gridIsDefaulted'));
    }

    public function test_the_grid_falls_back_to_the_current_month_when_there_are_no_records(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student);

        $response = $this->history($student);

        $response->assertOk();
        $this->assertSame(now()->format('F Y'), $response->viewData('gridMonthLabel'));
    }

    public function test_the_grid_ignores_the_prayer_filter(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->prayer($enrollment, self::MONDAY, 'Fajr', 'Present');
        $this->prayer($enrollment, self::MONDAY, 'Zuhr', 'Absent');

        $response = $this->history($student, ['prayer' => 'Fajr', 'month' => 8, 'year' => 2026]);

        // The table narrows...
        $this->assertSame(['Fajr'], $this->listed($response)->pluck('prayer')->all());

        // ...but the grid still shows Zuhr as Absent rather than as
        // unmarked, which would be a lie about the register.
        $row = $this->gridRow($response, self::MONDAY);

        $this->assertSame('Present', $row['cells']['Fajr']['status']);
        $this->assertSame('Absent', $row['cells']['Zuhr']['status']);
    }

    /* ---------------------------------------------------------------- */
    /* Filters */
    /* ---------------------------------------------------------------- */

    public function test_the_session_filter_narrows_the_history(): void
    {
        $student = $this->student('Ahtesham Shakeel');

        $first = $this->madrassaEnrollment($student);
        $this->prayer($first, self::MONDAY, 'Fajr');

        $second = $this->madrassaEnrollment($student, [
            'academic_session_id' => $this->nextSession->id,
            'start_date' => '2027-04-01',
        ]);
        $this->prayer($second, '2027-08-02', 'Fajr');

        // All sessions.
        $this->assertCount(2, $this->listed($this->history($student)));

        $this->assertSame(
            [$first->id],
            $this->listed($this->history($student, ['academic_session_id' => $this->session->id]))
                ->pluck('student_academic_enrollment_id')->all()
        );
        $this->assertSame(
            [$second->id],
            $this->listed($this->history($student, ['academic_session_id' => $this->nextSession->id]))
                ->pluck('student_academic_enrollment_id')->all()
        );
    }

    public function test_the_month_and_year_filters_narrow_the_history(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->prayer($enrollment, self::MONDAY, 'Fajr');
        $this->prayer($enrollment, '2026-09-01', 'Fajr');

        $this->assertCount(2, $this->listed($this->history($student)));
        $this->assertCount(1, $this->listed($this->history($student, ['month' => 8, 'year' => 2026])));
        $this->assertCount(1, $this->listed($this->history($student, ['month' => 9, 'year' => 2026])));
        $this->assertCount(0, $this->listed($this->history($student, ['month' => 10, 'year' => 2026])));

        // A year on its own covers every month in it.
        $this->assertCount(2, $this->listed($this->history($student, ['year' => 2026])));
        $this->assertCount(0, $this->listed($this->history($student, ['year' => 2027])));
    }

    public function test_the_prayer_filter_narrows_the_history(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->wholeDay($enrollment, self::MONDAY);

        foreach (StudentPrayerAttendance::PRAYERS as $prayerName) {
            $this->assertSame(
                [$prayerName],
                $this->listed($this->history($student, ['prayer' => $prayerName]))->pluck('prayer')->all()
            );
        }

        $this->assertCount(5, $this->listed($this->history($student)));
    }

    public function test_an_unrecognised_prayer_filter_narrows_nothing(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->wholeDay($this->madrassaEnrollment($student), self::MONDAY);

        $response = $this->history($student, ['prayer' => 'Tahajjud']);

        $response->assertOk();
        $this->assertNull($response->viewData('filters')['prayer']);
        $this->assertCount(5, $this->listed($response));
    }

    public function test_the_filters_combine(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->wholeDay($enrollment, self::MONDAY);
        $this->wholeDay($enrollment, '2026-09-01');

        $response = $this->history($student, [
            'academic_session_id' => $this->session->id,
            'month' => 8,
            'year' => 2026,
            'prayer' => 'Asr',
        ]);

        $listed = $this->listed($response);

        $this->assertCount(1, $listed);
        $this->assertSame('Asr', $listed->first()->prayer);
        $this->assertSame(self::MONDAY, $listed->first()->attendance_date->format('Y-m-d'));

        // A combination that agrees on nothing returns nothing rather than
        // one filter quietly winning. October holds no records at all,
        // though August and September both do.
        $this->assertCount(0, $this->listed($this->history($student, [
            'month' => 10,
            'year' => 2026,
            'prayer' => 'Asr',
            'academic_session_id' => $this->session->id,
        ])));
    }

    /* ---------------------------------------------------------------- */
    /* Pagination */
    /* ---------------------------------------------------------------- */

    public function test_the_history_is_paginated_at_twenty_five(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // Six working days of five prayers is thirty records.
        foreach ([self::MONDAY, self::TUESDAY, self::WEDNESDAY, self::THURSDAY, self::FRIDAY, '2026-08-10'] as $date) {
            $this->wholeDay($enrollment, $date);
        }

        $first = $this->history($student);

        $first->assertOk();
        $first->assertSee('Showing 1 to 25 of 30 prayer records');
        $this->assertCount(25, $first->viewData('records')->items());

        $this->assertCount(5, $this->history($student, ['page' => 2])->viewData('records')->items());
    }

    public function test_pagination_keeps_the_active_filters(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        foreach ([self::MONDAY, self::TUESDAY, self::WEDNESDAY, self::THURSDAY, self::FRIDAY, '2026-08-10'] as $date) {
            $this->wholeDay($enrollment, $date);
        }

        // Thirty records with a year filter: two pages, so the paginator
        // actually renders links, and they must carry the filter.
        $first = $this->history($student, ['year' => 2026]);

        $this->assertCount(25, $first->viewData('records')->items());
        $first->assertSee('year=2026', false);
        $first->assertSee('page=2', false);

        $second = $this->history($student, ['page' => 2, 'year' => 2026]);

        $this->assertSame(2026, $second->viewData('filters')['year']);
        $this->assertCount(5, $second->viewData('records')->items());

        // A filter that fits on one page still survives into the request,
        // even though there are no links to carry it.
        $filtered = $this->history($student, ['prayer' => 'Fajr', 'year' => 2026]);

        $this->assertSame('Fajr', $filtered->viewData('filters')['prayer']);
        $this->assertCount(6, $filtered->viewData('records')->items());
    }

    /* ---------------------------------------------------------------- */
    /* Historical placement */
    /* ---------------------------------------------------------------- */

    public function test_a_record_keeps_its_placement_after_the_student_is_promoted(): void
    {
        $student = $this->student('Ahtesham Shakeel');

        $nazra = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->hifzA->id,
        ]);
        $this->prayer($nazra, self::MONDAY, 'Fajr');

        $student->promote([
            'academic_track' => 'Madrassa',
            'academic_session_id' => $this->nextSession->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'promotion_date' => '2027-04-01',
        ]);

        $student->refresh();

        // The student has moved on.
        $current = $student->activeEnrollmentForTrack('Madrassa');
        $this->assertSame($this->hifzClass->id, $current->academic_class_id);
        $this->assertSame($this->hifzB->id, $current->section_id);

        // The record has not.
        $listed = $this->listed($this->history($student))->first();
        $enrollment = $listed->studentAcademicEnrollment;

        $this->assertSame($nazra->id, $listed->student_academic_enrollment_id);
        $this->assertSame($this->session->id, $enrollment->academic_session_id);
        $this->assertSame('2026-2027', $enrollment->academicSession->name);
        $this->assertSame('Hifz', $enrollment->department->name);
        $this->assertSame('Nazra', $enrollment->academicClass->name);
        $this->assertSame('Hifz-A', $enrollment->section->name);
    }

    public function test_records_from_several_madrassa_enrollments_appear_with_their_own_placements(): void
    {
        $student = $this->student('Ahtesham Shakeel');

        $nazra = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);
        $this->prayer($nazra, self::MONDAY, 'Fajr');

        $promoted = $student->promote([
            'academic_track' => 'Madrassa',
            'academic_session_id' => $this->nextSession->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'promotion_date' => '2027-04-01',
        ]);

        // 2027-08-02 is a Monday.
        $this->prayer($promoted, '2027-08-02', 'Fajr');

        // A school enrollment alongside, to prove it stays out.
        $school = $this->schoolEnrollment($student);
        $this->prayer($school, self::TUESDAY, 'Fajr');

        $response = $this->history($student);
        $listed = $this->listed($response);

        // Both madrassa placements, newest first, and no school row.
        $this->assertCount(2, $listed);
        $this->assertSame(2, $response->viewData('enrollmentCount'));
        $this->assertNotContains($school->id, $listed->pluck('student_academic_enrollment_id')->all());

        $this->assertSame('Hifz', $listed->first()->studentAcademicEnrollment->academicClass->name);
        $this->assertSame('Hifz-B', $listed->first()->studentAcademicEnrollment->section->name);
        $this->assertSame('Nazra', $listed->last()->studentAcademicEnrollment->academicClass->name);
        $this->assertSame('No section', $listed->last()->studentAcademicEnrollment->section?->name ?? 'No section');
    }

    /* ---------------------------------------------------------------- */
    /* Summary */
    /* ---------------------------------------------------------------- */

    public function test_the_summary_counts_present_and_absent_per_prayer(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // Monday: all five present. Tuesday: Fajr absent, Zuhr absent, the
        // rest present. Wednesday: only Fajr, present.
        $this->wholeDay($enrollment, self::MONDAY, 'Present');

        $this->prayer($enrollment, self::TUESDAY, 'Fajr', 'Absent', 'Sick');
        $this->prayer($enrollment, self::TUESDAY, 'Zuhr', 'Absent');
        $this->prayer($enrollment, self::TUESDAY, 'Asr', 'Present');
        $this->prayer($enrollment, self::TUESDAY, 'Maghrib', 'Present');
        $this->prayer($enrollment, self::TUESDAY, 'Isha', 'Present');

        $this->prayer($enrollment, self::WEDNESDAY, 'Fajr', 'Present');

        $summary = $this->history($student)->viewData('summary');

        $this->assertSame(11, $summary['total']);
        $this->assertSame(9, $summary['present']);
        $this->assertSame(2, $summary['absent']);

        $this->assertSame(['present' => 2, 'absent' => 1, 'total' => 3], $summary['by_prayer']['Fajr']);
        $this->assertSame(['present' => 1, 'absent' => 1, 'total' => 2], $summary['by_prayer']['Zuhr']);
        $this->assertSame(['present' => 2, 'absent' => 0, 'total' => 2], $summary['by_prayer']['Asr']);
        $this->assertSame(['present' => 2, 'absent' => 0, 'total' => 2], $summary['by_prayer']['Maghrib']);
        $this->assertSame(['present' => 2, 'absent' => 0, 'total' => 2], $summary['by_prayer']['Isha']);
    }

    public function test_the_summary_does_not_count_unmarked_prayers(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // One prayer on one day. The other four of that day, and every
        // other day of the month, are untranscribed.
        $this->prayer($enrollment, self::MONDAY, 'Fajr', 'Present');

        $summary = $this->history($student)->viewData('summary');

        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['present']);
        $this->assertSame(0, $summary['absent']);
        $this->assertSame(['present' => 0, 'absent' => 0, 'total' => 0], $summary['by_prayer']['Isha']);
    }

    public function test_the_summary_follows_the_period_filters(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->wholeDay($enrollment, self::MONDAY, 'Present');
        $this->wholeDay($enrollment, '2026-09-01', 'Absent');

        $this->assertSame(10, $this->history($student)->viewData('summary')['total']);

        $august = $this->history($student, ['month' => 8, 'year' => 2026])->viewData('summary');

        $this->assertSame(5, $august['total']);
        $this->assertSame(5, $august['present']);
        $this->assertSame(0, $august['absent']);
    }

    public function test_the_summary_ignores_the_prayer_filter_so_the_breakdown_stays_meaningful(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->wholeDay($this->madrassaEnrollment($student), self::MONDAY, 'Present');

        $response = $this->history($student, ['prayer' => 'Fajr']);

        // The table narrows to one prayer...
        $this->assertCount(1, $this->listed($response));

        // ...while the panel keeps reporting all five, so four of them do
        // not read as "no records exist".
        $summary = $response->viewData('summary');

        $this->assertSame(5, $summary['total']);
        $this->assertSame(1, $summary['by_prayer']['Isha']['present']);
    }

    /* ---------------------------------------------------------------- */
    /* Read-only safety */
    /* ---------------------------------------------------------------- */

    public function test_visiting_the_page_creates_and_changes_nothing(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->prayer($enrollment, self::MONDAY, 'Fajr', 'Present');
        $this->prayer($enrollment, self::MONDAY, 'Zuhr', 'Absent', 'Sick');

        $before = StudentPrayerAttendance::orderBy('id')->get()
            // Flattened to scalars: comparing the models directly would
            // compare Carbon instances by identity and never match.
            ->map(fn ($row) => [
                'id' => $row->id,
                'enrollment' => $row->student_academic_enrollment_id,
                'date' => $row->attendance_date->format('Y-m-d'),
                'prayer' => $row->prayer,
                'status' => $row->status,
                'reason' => $row->absence_reason,
                'updated_at' => $row->updated_at?->format('Y-m-d H:i:s'),
            ])
            ->toArray();

        // Every view of the page: the default, a month with records, a
        // month without, and a filtered one.
        $this->history($student)->assertOk();
        $this->history($student, ['month' => 8, 'year' => 2026])->assertOk();
        $this->history($student, ['month' => 12, 'year' => 2026])->assertOk();
        $this->history($student, ['prayer' => 'Isha'])->assertOk();

        $after = StudentPrayerAttendance::orderBy('id')->get()
            // Flattened to scalars: comparing the models directly would
            // compare Carbon instances by identity and never match.
            ->map(fn ($row) => [
                'id' => $row->id,
                'enrollment' => $row->student_academic_enrollment_id,
                'date' => $row->attendance_date->format('Y-m-d'),
                'prayer' => $row->prayer,
                'status' => $row->status,
                'reason' => $row->absence_reason,
                'updated_at' => $row->updated_at?->format('Y-m-d H:i:s'),
            ])
            ->toArray();

        $this->assertSame($before, $after);
        $this->assertDatabaseCount('student_prayer_attendances', 2);
    }

    public function test_drawing_a_month_does_not_fill_in_unmarked_prayers(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student);

        // A whole month drawn for a student with nothing on file. Every
        // cell is unmarked, and unmarked must stay the absence of a row.
        $this->history($student, ['month' => 8, 'year' => 2026])->assertOk();

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    public function test_the_page_offers_no_way_to_change_a_record(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->prayer($this->madrassaEnrollment($student), self::MONDAY, 'Fajr', 'Absent', 'Sick');

        $response = $this->history($student);

        // Nothing on the page writes. The store route shares its URL with
        // the monthly sheet the page legitimately links to, so what is
        // asserted is that no form submits to it - the only form in the
        // markup is the layout's logout, and the filter form is a GET.
        $response->assertDontSee('action="'.route('prayer-attendance.store').'"', false);
        $response->assertDontSee('Mark Present');
        $response->assertDontSee('Mark Absent');
        $response->assertDontSee('Edit Record');
        $response->assertDontSee('Delete');
        $response->assertDontSee('@method(\'PUT\')', false);
        $response->assertDontSee('name="_method"', false);
    }

    /* ---------------------------------------------------------------- */
    /* Navigation */
    /* ---------------------------------------------------------------- */

    public function test_the_student_profile_offers_the_history_to_a_madrassa_student(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student);

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('View Prayer Attendance')
            ->assertSee(route('students.prayer-attendance', $student->id), false);
    }

    public function test_the_student_profile_hides_the_history_from_a_school_only_student(): void
    {
        $student = $this->student('Usman Tariq', 'School');
        $this->schoolEnrollment($student);

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertDontSee('View Prayer Attendance')
            ->assertDontSee(route('students.prayer-attendance', $student->id), false);
    }

    public function test_a_hifz_plus_school_profile_still_offers_the_history(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $this->madrassaEnrollment($student);
        $this->schoolEnrollment($student);

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('View Prayer Attendance');
    }

    public function test_the_history_links_back_to_the_profile_and_the_monthly_sheet(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student);

        $response = $this->history($student);

        $response->assertSee('Back to Student Profile');
        $response->assertSee('Prayer Attendance Monthly Sheet');
        $response->assertSee(route('students.show', $student->id).'#prayer-attendance', false);
    }

    public function test_the_monthly_sheet_links_to_a_students_history(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student);

        $this->get(route('prayer-attendance.index', [
            'academic_session_id' => $this->session->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'month' => 8,
            'year' => 2026,
        ]))->assertOk()->assertSee(route('students.prayer-attendance', $student->id), false);
    }

    /* ---------------------------------------------------------------- */
    /* Performance */
    /* ---------------------------------------------------------------- */

    public function test_the_query_count_does_not_grow_with_the_number_of_records(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // One working day of five prayers.
        $this->wholeDay($enrollment, self::MONDAY);
        $withOneDay = $this->countHistoryQueries($student);

        // Four more days, so twenty-five records on the page instead of five.
        foreach ([self::TUESDAY, self::WEDNESDAY, self::THURSDAY, self::FRIDAY] as $date) {
            $this->wholeDay($enrollment, $date);
        }

        $withFiveDays = $this->countHistoryQueries($student);

        // Eager loading and the grouped summary are what make these equal:
        // without them each record would fetch its own enrollment, session,
        // department, class and section.
        $this->assertSame($withOneDay, $withFiveDays);

        // And the fixed cost stays a page's worth of queries: the grid, the
        // grouped summary, the paginated table and its eager loads.
        $this->assertLessThan(25, $withFiveDays);
    }

    /**
     * Count the queries one render of the history costs.
     *
     * A warm-up request first: the permission and role lookups are cached
     * per process, so measuring the first request of a run would count them
     * once and never again.
     */
    private function countHistoryQueries(Student $student): int
    {
        $this->history($student)->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->history($student)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_the_history_does_not_read_academic_attendance_or_daily_records(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->prayer($this->madrassaEnrollment($student), self::MONDAY, 'Fajr');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->history($student)->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        DB::disableQueryLog();

        // Three registers, three tables. None of them is consulted to fill
        // in another.
        $this->assertStringContainsString('student_prayer_attendances', $queries);
        $this->assertStringNotContainsString('student_attendances`', $queries);
        $this->assertStringNotContainsString('madrassa_daily_records', $queries);
    }
}
