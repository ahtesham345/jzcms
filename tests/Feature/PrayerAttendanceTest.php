<?php

namespace Tests\Feature;

use App\Models\StudentAcademicEnrollment;
use App\Models\StudentAttendance;
use App\Models\StudentPrayerAttendance;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers the monthly prayer attendance sheet.
 *
 * The five daily prayers are the madrassa's register, kept on paper through
 * the month and transcribed afterwards, so the sheet is a month of columns
 * with five marks in each day. What it writes is still one row per student,
 * day and prayer.
 *
 * Two things must keep being true. It is Madrassa only - a school-only
 * student has no prayer sheet to appear on, and the school side of a
 * Hifz + School student can never carry a prayer. And it is a separate
 * register from academic attendance: neither table is read to fill in the
 * other, and nothing here changes how student_attendances behaves.
 */
class PrayerAttendanceTest extends TestCase
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
     * The filters that draw the default sheet: August 2026, Hifz class.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sheetFilters(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'month' => 8,
            'year' => 2026,
        ], $overrides);
    }

    /**
     * Load the monthly sheet.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function loadSheet(array $overrides = [])
    {
        return $this->get(route('prayer-attendance.index', $this->sheetFilters($overrides)));
    }

    /**
     * Submit changed cells to the sheet.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $overrides
     */
    private function save(array $rows, array $overrides = [])
    {
        return $this->post(route('prayer-attendance.store'), $this->sheetFilters($overrides) + [
            'sheet' => json_encode($rows),
        ]);
    }

    /**
     * One submitted cell.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function cell(
        StudentAcademicEnrollment $enrollment,
        string $date = self::MONDAY,
        string $prayer = StudentPrayerAttendance::PRAYER_FAJR,
        string $status = StudentPrayerAttendance::STATUS_PRESENT,
        array $overrides = []
    ): array {
        return array_merge([
            'student_academic_enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'attendance_date' => $date,
            'prayer' => $prayer,
            'status' => $status,
            'absence_reason' => null,
        ], $overrides);
    }

    /**
     * The enrollment ids the loaded sheet drew.
     *
     * @return array<int, int>
     */
    private function sheetEnrollmentIds($response): array
    {
        $sheet = $response->viewData('sheet');

        return $sheet === null ? [] : $sheet->pluck('id')->all();
    }

    /* ---------------------------------------------------------------- */
    /* Access */
    /* ---------------------------------------------------------------- */

    public function test_a_guest_cannot_reach_the_prayer_sheet(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        auth()->logout();

        $this->get(route('prayer-attendance.index'))->assertRedirect(route('login'));
        $this->save([$this->cell($enrollment)])->assertRedirect(route('login'));

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    public function test_an_authenticated_admin_can_open_the_page(): void
    {
        $response = $this->get(route('prayer-attendance.index'));

        $response->assertOk();
        $response->assertSee('Monthly Prayer Attendance Entry');
        // Nothing is drawn until a group is chosen.
        $this->assertNull($response->viewData('sheet'));
    }

    /* ---------------------------------------------------------------- */
    /* Who appears on the sheet */
    /* ---------------------------------------------------------------- */

    public function test_madrassa_students_appear_on_the_sheet(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $response = $this->loadSheet();

        $response->assertOk();
        $response->assertSee('Ahtesham Shakeel');
        $this->assertSame([$enrollment->id], $this->sheetEnrollmentIds($response));
    }

    public function test_school_only_students_do_not_appear_on_the_sheet(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $this->schoolEnrollment($this->student('Usman Tariq', 'School'));

        // Asked for on the school side too, so the absence is not just the
        // class filter doing the work.
        $bySchoolClass = $this->loadSheet([
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
        ]);

        $this->assertCount(1, $this->sheetEnrollmentIds($this->loadSheet()));
        $this->assertSame([], $this->sheetEnrollmentIds($bySchoolClass));
        $bySchoolClass->assertDontSee('Usman Tariq');
    }

    public function test_a_hifz_plus_school_student_appears_through_the_madrassa_enrollment_only(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $response = $this->loadSheet();

        // One row, and it is the madrassa one.
        $this->assertSame([$madrassa->id], $this->sheetEnrollmentIds($response));
        $this->assertNotContains($school->id, $this->sheetEnrollmentIds($response));

        // And what gets written stays on the madrassa side.
        $this->save([$this->cell($madrassa)])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_prayer_attendances', [
            'student_academic_enrollment_id' => $madrassa->id,
        ]);
        $this->assertDatabaseMissing('student_prayer_attendances', [
            'student_academic_enrollment_id' => $school->id,
        ]);
    }

    public function test_a_completed_or_left_enrollment_does_not_appear(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $this->madrassaEnrollment($this->student('Zaid Khan'), ['status' => 'Completed', 'end_date' => '2026-06-30']);
        $this->madrassaEnrollment($this->student('Owais Raza'), ['status' => 'Left', 'end_date' => '2026-06-30']);

        $response = $this->loadSheet();

        $this->assertCount(1, $this->sheetEnrollmentIds($response));
        $response->assertSee('Ahtesham Shakeel');
        $response->assertDontSee('Zaid Khan');
    }

    public function test_contradictory_filters_return_no_students(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        // A department and a class from different branches. The student
        // matches each on its own but not both together.
        $response = $this->loadSheet([
            'department_id' => $this->darsENizami->id,
            'academic_class_id' => $this->hifzClass->id,
        ]);

        $response->assertOk();
        $this->assertSame([], $this->sheetEnrollmentIds($response));
    }

    public function test_the_section_filter_narrows_the_sheet(): void
    {
        $inA = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'), ['section_id' => $this->hifzA->id]);
        $inB = $this->madrassaEnrollment($this->student('Zaid Khan'), ['section_id' => $this->hifzB->id]);

        $this->assertSame([$inA->id], $this->sheetEnrollmentIds($this->loadSheet(['section_id' => $this->hifzA->id])));
        $this->assertSame([$inB->id], $this->sheetEnrollmentIds($this->loadSheet(['section_id' => $this->hifzB->id])));
        // No section chosen draws the whole class.
        $this->assertCount(2, $this->sheetEnrollmentIds($this->loadSheet()));
    }

    /* ---------------------------------------------------------------- */
    /* The five prayers */
    /* ---------------------------------------------------------------- */

    public function test_the_sheet_offers_all_five_prayers(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $response = $this->loadSheet();

        $this->assertSame(
            ['Fajr', 'Zuhr', 'Asr', 'Maghrib', 'Isha'],
            $response->viewData('prayers')
        );

        foreach (StudentPrayerAttendance::PRAYERS as $prayer) {
            $response->assertSee($prayer);
        }
    }

    #[DataProvider('everyPrayer')]
    public function test_every_prayer_can_be_saved(string $prayer): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([$this->cell($enrollment, self::MONDAY, $prayer)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_prayer_attendances', [
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => self::MONDAY,
            'prayer' => $prayer,
            'status' => StudentPrayerAttendance::STATUS_PRESENT,
        ]);
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

    public function test_all_five_prayers_of_one_day_are_saved_independently(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([
            $this->cell($enrollment, self::MONDAY, 'Fajr', 'Present'),
            $this->cell($enrollment, self::MONDAY, 'Zuhr', 'Present'),
            $this->cell($enrollment, self::MONDAY, 'Asr', 'Absent', ['absence_reason' => 'Sick']),
            $this->cell($enrollment, self::MONDAY, 'Maghrib', 'Present'),
            $this->cell($enrollment, self::MONDAY, 'Isha', 'Present'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_prayer_attendances', 5);
        $this->assertSame(4, StudentPrayerAttendance::where('status', 'Present')->count());
        $this->assertSame(
            'Sick',
            StudentPrayerAttendance::where('prayer', 'Asr')->firstOrFail()->absence_reason
        );
    }

    public function test_an_invalid_prayer_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([$this->cell($enrollment, self::MONDAY, 'Tahajjud')])
            ->assertSessionHasErrors('prayers.0.prayer');

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([$this->cell($enrollment, self::MONDAY, 'Fajr', 'Late')])
            ->assertSessionHasErrors('prayers.0.status');

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    /* ---------------------------------------------------------------- */
    /* Saving, editing and the absence reason */
    /* ---------------------------------------------------------------- */

    public function test_present_and_absent_can_both_be_saved(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([
            $this->cell($enrollment, self::MONDAY, 'Fajr', 'Present'),
            $this->cell($enrollment, self::TUESDAY, 'Fajr', 'Absent'),
        ])->assertSessionHasNoErrors();

        $this->assertSame('Present', StudentPrayerAttendance::where('attendance_date', self::MONDAY)->firstOrFail()->status);
        $this->assertSame('Absent', StudentPrayerAttendance::where('attendance_date', self::TUESDAY)->firstOrFail()->status);
    }

    public function test_an_absence_reason_is_optional_and_is_stored_when_given(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([
            $this->cell($enrollment, self::MONDAY, 'Fajr', 'Absent', ['absence_reason' => 'Family issue']),
            // A paper prayer register often records only the mark.
            $this->cell($enrollment, self::TUESDAY, 'Fajr', 'Absent'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            'Family issue',
            StudentPrayerAttendance::where('attendance_date', self::MONDAY)->firstOrFail()->absence_reason
        );
        $this->assertNull(
            StudentPrayerAttendance::where('attendance_date', self::TUESDAY)->firstOrFail()->absence_reason
        );
    }

    public function test_changing_absent_to_present_clears_the_reason(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([$this->cell($enrollment, self::MONDAY, 'Fajr', 'Absent', ['absence_reason' => 'Sick'])])
            ->assertSessionHasNoErrors();

        $this->assertSame('Sick', StudentPrayerAttendance::firstOrFail()->absence_reason);

        // Corrected to Present. A reason must never outlive the absence it
        // explained, even if the browser sends it again.
        $this->save([$this->cell($enrollment, self::MONDAY, 'Fajr', 'Present', ['absence_reason' => 'Sick'])])
            ->assertSessionHasNoErrors();

        $record = StudentPrayerAttendance::firstOrFail();

        $this->assertSame('Present', $record->status);
        $this->assertNull($record->absence_reason);
        $this->assertDatabaseCount('student_prayer_attendances', 1);
    }

    public function test_nothing_is_saved_when_no_cells_are_submitted(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        // Loading and saving an untouched month must not invent a single
        // Present, which is the whole reason unmarked is not a stored value.
        $this->loadSheet()->assertOk();
        $this->save([])->assertSessionHasNoErrors()->assertSessionHas('success', 'No changes to save.');

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    public function test_a_month_can_be_entered_over_several_sittings(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([$this->cell($enrollment, self::MONDAY, 'Fajr', 'Present')])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('student_prayer_attendances', 1);

        // A later sitting adds more without disturbing the first.
        $this->save([
            $this->cell($enrollment, self::TUESDAY, 'Fajr', 'Present'),
            $this->cell($enrollment, self::TUESDAY, 'Zuhr', 'Absent'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_prayer_attendances', 3);
        $this->assertDatabaseHas('student_prayer_attendances', [
            'attendance_date' => self::MONDAY,
            'prayer' => 'Fajr',
            'status' => 'Present',
        ]);
    }

    public function test_saved_records_load_back_into_the_sheet(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([
            $this->cell($enrollment, self::MONDAY, 'Fajr', 'Present'),
            $this->cell($enrollment, self::MONDAY, 'Asr', 'Absent', ['absence_reason' => 'Sick']),
        ])->assertSessionHasNoErrors();

        $cells = $this->loadSheet()->viewData('initialCells');

        $present = StudentPrayerAttendance::cellKey($enrollment->id, self::MONDAY, 'Fajr');
        $absent = StudentPrayerAttendance::cellKey($enrollment->id, self::MONDAY, 'Asr');
        $untouched = StudentPrayerAttendance::cellKey($enrollment->id, self::MONDAY, 'Isha');

        $this->assertSame(['status' => 'Present', 'reason' => ''], $cells[$present]);
        $this->assertSame(['status' => 'Absent', 'reason' => 'Sick'], $cells[$absent]);
        // Never transcribed, so it comes back empty rather than Present.
        $this->assertSame(['status' => '', 'reason' => ''], $cells[$untouched]);
    }

    public function test_resaving_updates_rather_than_duplicating(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([$this->cell($enrollment, self::MONDAY, 'Fajr', 'Present')])->assertSessionHasNoErrors();
        $this->save([$this->cell($enrollment, self::MONDAY, 'Fajr', 'Absent', ['absence_reason' => 'Sick'])])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_prayer_attendances', 1);

        $record = StudentPrayerAttendance::firstOrFail();

        $this->assertSame('Absent', $record->status);
        $this->assertSame('Sick', $record->absence_reason);
    }

    public function test_unmarking_a_saved_prayer_removes_it(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([
            $this->cell($enrollment, self::MONDAY, 'Fajr', 'Present'),
            $this->cell($enrollment, self::MONDAY, 'Zuhr', 'Present'),
        ])->assertSessionHasNoErrors();

        // A mark typed against the wrong prayer is taken back, which leaves
        // it genuinely untranscribed rather than as a judgement.
        $this->save([$this->cell($enrollment, self::MONDAY, 'Fajr', StudentPrayerAttendance::STATUS_UNMARKED)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_prayer_attendances', 1);
        $this->assertDatabaseMissing('student_prayer_attendances', [
            'student_academic_enrollment_id' => $enrollment->id,
            'prayer' => 'Fajr',
        ]);
        $this->assertDatabaseHas('student_prayer_attendances', [
            'student_academic_enrollment_id' => $enrollment->id,
            'prayer' => 'Zuhr',
        ]);
    }

    public function test_unmarking_something_never_recorded_is_a_no_op(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([$this->cell($enrollment, self::MONDAY, 'Fajr', StudentPrayerAttendance::STATUS_UNMARKED)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    public function test_the_same_cell_cannot_be_submitted_twice_in_one_save(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([
            $this->cell($enrollment, self::MONDAY, 'Fajr', 'Present'),
            $this->cell($enrollment, self::MONDAY, 'Fajr', 'Absent'),
        ])->assertSessionHasErrors('prayers.1.student_academic_enrollment_id');

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    public function test_the_database_refuses_a_duplicate_prayer(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $attributes = [
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => self::MONDAY,
            'prayer' => 'Fajr',
            'status' => 'Present',
        ];

        StudentPrayerAttendance::create($attributes);

        $this->expectException(QueryException::class);

        StudentPrayerAttendance::create($attributes);
    }

    public function test_the_same_prayer_on_the_same_day_is_allowed_for_two_students(): void
    {
        $first = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $second = $this->madrassaEnrollment($this->student('Zaid Khan'));

        $this->save([
            $this->cell($first, self::MONDAY, 'Fajr', 'Present'),
            $this->cell($second, self::MONDAY, 'Fajr', 'Absent'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_prayer_attendances', 2);
    }

    /* ---------------------------------------------------------------- */
    /* Weekends */
    /* ---------------------------------------------------------------- */

    public function test_saturday_is_accepted(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([$this->cell($enrollment, self::SATURDAY)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_prayer_attendances', [
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => self::SATURDAY,
        ]);
    }

    public function test_sunday_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([$this->cell($enrollment, self::SUNDAY)])
            ->assertSessionHasErrors('prayers.0.attendance_date');

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    public function test_the_sheet_marks_the_off_day_and_gives_it_no_cells(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $response = $this->loadSheet();

        $response->assertOk();
        $response->assertSee('OFF');

        $cells = $response->viewData('initialCells');

        // No cell exists for a Sunday, so there is nothing to click and
        // nothing that could be submitted. Saturday is offered in full.
        foreach (StudentPrayerAttendance::PRAYERS as $prayer) {
            $this->assertArrayNotHasKey(
                StudentPrayerAttendance::cellKey($enrollment->id, self::SUNDAY, $prayer),
                $cells
            );
            $this->assertArrayHasKey(
                StudentPrayerAttendance::cellKey($enrollment->id, self::SATURDAY, $prayer),
                $cells
            );
        }

        $this->assertArrayHasKey(
            StudentPrayerAttendance::cellKey($enrollment->id, self::MONDAY, 'Fajr'),
            $cells
        );
    }

    public function test_the_weekend_rule_is_the_one_the_institution_already_uses(): void
    {
        // Read from the academic register rather than restated, so the two
        // can never drift into disagreeing about which days are off.
        $this->assertSame(
            StudentAttendance::offDayName(self::SATURDAY),
            StudentPrayerAttendance::offDayName(self::SATURDAY)
        );
        $this->assertFalse(StudentPrayerAttendance::isPrayerDay(self::SUNDAY));
        $this->assertTrue(StudentPrayerAttendance::isPrayerDay(self::MONDAY));
    }

    /* ---------------------------------------------------------------- */
    /* The month */
    /* ---------------------------------------------------------------- */

    public function test_a_date_outside_the_selected_month_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        // A working day, but in September while the sheet says August.
        $this->save([$this->cell($enrollment, '2026-09-01')])
            ->assertSessionHasErrors('prayers.0.attendance_date');

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    public function test_a_historical_month_can_be_entered(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'), [
            'start_date' => '2020-01-01',
        ]);

        // 2020-05-04 is a Monday. Long past, and entirely normal: the
        // register is transcribed after the month has ended.
        $this->save([$this->cell($enrollment, '2020-05-04')], ['month' => 5, 'year' => 2020])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_prayer_attendances', ['attendance_date' => '2020-05-04']);
    }

    #[DataProvider('monthLengths')]
    public function test_the_sheet_draws_every_day_of_the_month(int $year, int $month, int $expectedDays): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'), ['start_date' => '2020-01-01']);

        $days = $this->loadSheet(['month' => $month, 'year' => $year])->viewData('days');

        $this->assertCount($expectedDays, $days);
        $this->assertSame(1, $days[0]['day']);
        $this->assertSame($expectedDays, $days[$expectedDays - 1]['day']);
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

    public function test_a_leap_day_can_be_recorded(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'), [
            'start_date' => '2020-01-01',
        ]);

        // 29 February 2028 is a Tuesday.
        $this->save([$this->cell($enrollment, '2028-02-29')], ['month' => 2, 'year' => 2028])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_prayer_attendances', ['attendance_date' => '2028-02-29']);
    }

    public function test_the_thirty_first_of_a_thirty_one_day_month_can_be_recorded(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        // 31 August 2026 is a Monday.
        $this->save([$this->cell($enrollment, '2026-08-31')])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_prayer_attendances', ['attendance_date' => '2026-08-31']);
    }

    /* ---------------------------------------------------------------- */
    /* Security */
    /* ---------------------------------------------------------------- */

    public function test_a_school_enrollment_is_rejected_server_side(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        // The browser is never what decides this: the track is read back
        // from the enrollment row.
        $this->save([$this->cell($school)])
            ->assertSessionHasErrors('prayers.0.student_academic_enrollment_id');

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    public function test_an_enrollment_belonging_to_another_student_is_rejected(): void
    {
        $mine = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $theirs = $this->madrassaEnrollment($this->student('Zaid Khan'));

        // My student id, somebody else's enrollment.
        $this->save([$this->cell($theirs, self::MONDAY, 'Fajr', 'Present', [
            'student_id' => $mine->student_id,
        ])])->assertSessionHasErrors('prayers.0.student_academic_enrollment_id');

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    public function test_an_enrollment_that_does_not_exist_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([$this->cell($enrollment, self::MONDAY, 'Fajr', 'Present', [
            'student_academic_enrollment_id' => $enrollment->id + 999,
        ])])->assertSessionHasErrors('prayers.0.student_academic_enrollment_id');

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    public function test_an_enrollment_from_another_class_group_is_rejected(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        // In Nazra, while the sheet was drawn for the Hifz class.
        $elsewhere = $this->madrassaEnrollment($this->student('Zaid Khan'), [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $this->save([$this->cell($elsewhere)])
            ->assertSessionHasErrors('prayers.0.student_academic_enrollment_id');

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    public function test_a_non_active_enrollment_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'), [
            'status' => 'Completed',
            'end_date' => '2026-12-31',
        ]);

        $this->save([$this->cell($enrollment)])
            ->assertSessionHasErrors('prayers.0.student_academic_enrollment_id');

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    public function test_a_save_without_a_class_group_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->post(route('prayer-attendance.store'), [
            'month' => 8,
            'year' => 2026,
            'sheet' => json_encode([$this->cell($enrollment)]),
        ])->assertSessionHasErrors(['academic_session_id', 'department_id', 'academic_class_id']);

        $this->assertDatabaseCount('student_prayer_attendances', 0);
    }

    /* ---------------------------------------------------------------- */
    /* Historical records */
    /* ---------------------------------------------------------------- */

    public function test_existing_records_survive_master_data_being_retired(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->save([$this->cell($enrollment, self::MONDAY, 'Fajr', 'Present')])->assertSessionHasNoErrors();

        // The class and section are closed later in the year. A record made
        // while they were open must stay exactly as it was.
        $this->hifzClass->update(['status' => false]);
        $this->hifzA->update(['status' => false]);

        $record = StudentPrayerAttendance::firstOrFail();

        $this->assertSame('Present', $record->status);
        $this->assertSame($enrollment->id, $record->student_academic_enrollment_id);
        $this->assertSame('Hifz', $record->studentAcademicEnrollment->academicClass->name);
        $this->assertDatabaseCount('student_prayer_attendances', 1);
    }

    public function test_records_keep_the_placement_they_were_made_under(): void
    {
        $student = $this->student('Ahtesham Shakeel');

        $nazra = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->hifzA->id,
        ]);

        StudentPrayerAttendance::create([
            'student_academic_enrollment_id' => $nazra->id,
            'attendance_date' => self::MONDAY,
            'prayer' => 'Fajr',
            'status' => 'Present',
        ]);

        $student->promote([
            'academic_track' => 'Madrassa',
            'academic_session_id' => $this->nextSession->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'promotion_date' => '2027-04-01',
        ]);

        $record = StudentPrayerAttendance::firstOrFail();

        $this->assertSame($nazra->id, $record->student_academic_enrollment_id);
        $this->assertSame('Nazra', $record->studentAcademicEnrollment->academicClass->name);
        $this->assertSame('Hifz-A', $record->studentAcademicEnrollment->section->name);
    }

    /* ---------------------------------------------------------------- */
    /* Separation from academic attendance */
    /* ---------------------------------------------------------------- */

    public function test_prayer_attendance_does_not_touch_the_academic_register(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        StudentAttendance::create([
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => self::MONDAY,
            'attendance_period' => 'Morning',
            'status' => 'Absent',
            'absence_reason' => 'Sick',
        ]);

        $this->save([$this->cell($enrollment, self::MONDAY, 'Fajr', 'Present')])->assertSessionHasNoErrors();

        // Two registers, two tables. Marking a prayer says nothing about the
        // class the student sat in, and does not alter it.
        $academic = StudentAttendance::firstOrFail();

        $this->assertSame('Absent', $academic->status);
        $this->assertSame('Sick', $academic->absence_reason);
        $this->assertDatabaseCount('student_attendances', 1);
        $this->assertDatabaseCount('student_prayer_attendances', 1);
    }

    public function test_the_prayer_sheet_never_reads_academic_attendance(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        StudentAttendance::create([
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => self::MONDAY,
            'attendance_period' => 'Morning',
            'status' => 'Present',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->loadSheet()->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        DB::disableQueryLog();

        // Whether the student prayed is answered by the prayer register
        // alone. Nothing is inferred from whether they were in class.
        $this->assertStringNotContainsString('student_attendances', $queries);
        $this->assertStringContainsString('student_prayer_attendances', $queries);
    }

    /* ---------------------------------------------------------------- */
    /* Performance */
    /* ---------------------------------------------------------------- */

    public function test_the_sheet_query_count_does_not_grow_with_the_class(): void
    {
        $counts = [];

        foreach ([5, 15, 30] as $target) {
            $this->fillClassTo($target);
            $counts[$target] = $this->countSheetQueries();
        }

        // A month of five prayers for thirty students is thousands of cells.
        // Equality is what proves none of them costs its own query.
        $this->assertSame($counts[5], $counts[15]);
        $this->assertSame($counts[5], $counts[30]);
        $this->assertLessThan(20, $counts[30]);
    }

    /**
     * Add students to the sheet's class until it holds a given number.
     *
     * Some of them get prayers recorded, so the count covers the rows that
     * load existing marks as well as the empty ones.
     */
    private function fillClassTo(int $target): void
    {
        $existing = count($this->sheetEnrollmentIds($this->loadSheet()));

        for ($index = $existing; $index < $target; $index++) {
            $enrollment = $this->madrassaEnrollment($this->student('Student '.$index.' '.uniqid()));

            if ($index % 2 === 0) {
                StudentPrayerAttendance::create([
                    'student_academic_enrollment_id' => $enrollment->id,
                    'attendance_date' => self::MONDAY,
                    'prayer' => 'Fajr',
                    'status' => 'Present',
                ]);
            }
        }
    }

    /**
     * Count the queries one render of the sheet costs.
     *
     * A warm-up request first: the permission and role lookups are cached
     * per process, so measuring the first request of a run would count them
     * once and never again.
     */
    private function countSheetQueries(): int
    {
        $this->loadSheet()->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->loadSheet()->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_a_whole_months_save_costs_a_bounded_number_of_queries(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $rows = [];

        foreach ($this->loadSheet()->viewData('days') as $day) {
            if ($day['is_off_day']) {
                continue;
            }

            foreach (StudentPrayerAttendance::PRAYERS as $prayer) {
                $rows[] = $this->cell($enrollment, $day['date'], $prayer, 'Present');
            }
        }

        // Twenty-six working days times five prayers.
        $this->assertCount(130, $rows);

        $this->save($rows)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_prayer_attendances', 130);
    }
}
