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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Covers the monthly attendance sheet.
 *
 * Attendance is kept on paper through the month and transcribed afterwards,
 * so the sheet is a month of columns. What it writes is still one row per
 * student, day and period, which is why the dual-track cases matter: a
 * Hifz + School student holds two active enrollments and entering one month
 * must never touch the other.
 */
class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    /** August 2026: the 1st is a Saturday (a working day), the 2nd a Sunday. */
    private const YEAR = 2026;

    private const MONTH = 8;

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

    private function student(string $name = 'Ahmed Ali'): Student
    {
        static $sequence = 0;
        $sequence++;

        return Student::create([
            'registration_number' => 'STD-2026-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
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
        ]);
    }

    /**
     * Enroll a student on the madrassa track.
     */
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

    /**
     * Enroll a student on the school track.
     */
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
     * The group filters naming the madrassa sheet.
     *
     * @return array<string, mixed>
     */
    private function madrassaGroup(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
        ], $overrides);
    }

    /**
     * The group filters naming the school sheet.
     *
     * @return array<string, mixed>
     */
    private function schoolGroup(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->primaryB->id,
        ], $overrides);
    }

    /**
     * One marked cell of the sheet.
     *
     * @return array<string, mixed>
     */
    private function cell(StudentAcademicEnrollment $enrollment, string $date, string $status = 'Present', ?string $reason = null): array
    {
        return [
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => $date,
            'status' => $status,
            'absence_reason' => $reason,
        ];
    }

    /**
     * Post a month of marked cells.
     *
     * The sheet travels as one JSON field, the same way the browser sends
     * it, so the tests exercise the real submission shape.
     */
    private function save(array $group, array $cells, array $overrides = [])
    {
        return $this->post(route('attendance.store'), array_merge($group, [
            'month' => self::MONTH,
            'year' => self::YEAR,
            'attendance_period' => 'Morning',
            'sheet' => json_encode($cells),
        ], $overrides));
    }

    /**
     * Open a monthly sheet.
     */
    private function open(array $group, array $overrides = [])
    {
        return $this->get(route('attendance.index', array_filter(array_merge($group, [
            'month' => self::MONTH,
            'year' => self::YEAR,
        ], $overrides), fn ($value) => $value !== null)));
    }

    /* ---------------------------------------------------------------- */
    /* Schema */
    /* ---------------------------------------------------------------- */

    public function test_the_attendance_table_is_unchanged_and_still_daily(): void
    {
        $this->assertTrue(Schema::hasTable('student_attendances'));

        // Still one row per student, day and period. The monthly sheet is a
        // way of entering these rows, not a different way of storing them.
        $this->assertTrue(Schema::hasColumns('student_attendances', [
            'id',
            'student_academic_enrollment_id',
            'attendance_date',
            'attendance_period',
            'status',
            'absence_reason',
            'notes',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_attendance_belongs_to_an_academic_enrollment(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $attendance = StudentAttendance::create([
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => self::MONDAY,
            'attendance_period' => 'Morning',
            'status' => 'Present',
        ]);

        $attendance->refresh();

        $this->assertSame($enrollment->id, $attendance->studentAcademicEnrollment->id);
        $this->assertSame($enrollment->student_id, $attendance->student->id);
        $this->assertSame($this->session->id, $attendance->academicSession->id);
        $this->assertSame($this->hifz->id, $attendance->department->id);
        $this->assertSame($this->hifzClass->id, $attendance->academicClass->id);
        $this->assertSame($this->hifzA->id, $attendance->section->id);
    }

    public function test_the_duplicate_database_constraint_is_enforced(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $attributes = [
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => self::MONDAY,
            'attendance_period' => 'Morning',
            'status' => 'Present',
        ];

        StudentAttendance::create($attributes);

        $this->expectException(QueryException::class);

        StudentAttendance::create($attributes);
    }

    /* ---------------------------------------------------------------- */
    /* Loading a month */
    /* ---------------------------------------------------------------- */

    public function test_the_monthly_school_sheet_loads(): void
    {
        $enrollment = $this->schoolEnrollment($this->student('Ahmed Ali'));

        $response = $this->open($this->schoolGroup())->assertOk();

        $this->assertSame([$enrollment->id], $response->viewData('sheet')->pluck('id')->all());
        $this->assertSame('Morning', $response->viewData('filters')['attendance_period']);
        $response->assertSee('August 2026')->assertSee('Ahmed Ali');
    }

    public function test_the_monthly_madrassa_sheet_loads(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $response = $this->open($this->madrassaGroup())->assertOk();

        $this->assertSame([$enrollment->id], $response->viewData('sheet')->pluck('id')->all());
        $response->assertSee('August 2026')->assertSee('Ahmed Ali');
    }

    public function test_a_month_is_not_required_to_be_a_specific_date(): void
    {
        $this->madrassaEnrollment();

        // No date anywhere in the request: a month and a group is enough.
        $response = $this->get(route('attendance.index', $this->madrassaGroup()))->assertOk();

        $this->assertNotNull($response->viewData('sheet'));
        // Falls back to the month being worked in rather than refusing.
        $this->assertSame((int) now()->month, $response->viewData('filters')['month']);
    }

    /* ---------------------------------------------------------------- */
    /* Month lengths */
    /* ---------------------------------------------------------------- */

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function monthLengths(): array
    {
        return [
            'February 2027 has 28 days' => [2027, 2, 28],
            'February 2028 is a leap year with 29' => [2028, 2, 29],
            'April has 30 days' => [2026, 4, 30],
            'August has 31 days' => [2026, 8, 31],
        ];
    }

    #[DataProvider('monthLengths')]
    public function test_the_sheet_renders_the_right_number_of_days(int $year, int $month, int $expected): void
    {
        $this->madrassaEnrollment();

        $days = $this->open($this->madrassaGroup(), ['year' => $year, 'month' => $month])
            ->assertOk()
            ->viewData('days');

        $this->assertCount($expected, $days);
        $this->assertSame(1, $days[0]['day']);
        $this->assertSame($expected, $days[$expected - 1]['day']);
    }

    public function test_february_2027_has_twenty_eight_days(): void
    {
        $this->assertCount(28, StudentAttendance::monthDays(2027, 2));
    }

    public function test_february_2028_has_twenty_nine_days(): void
    {
        $this->assertCount(29, StudentAttendance::monthDays(2028, 2));
    }

    public function test_a_thirty_day_month_renders_thirty_days(): void
    {
        $this->assertCount(30, StudentAttendance::monthDays(2026, 4));
    }

    public function test_a_thirty_one_day_month_renders_thirty_one_days(): void
    {
        $this->assertCount(31, StudentAttendance::monthDays(2026, 8));
    }

    /* ---------------------------------------------------------------- */
    /* The weekly off day */
    /* ---------------------------------------------------------------- */

    public function test_sunday_is_the_only_off_day_shown(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $response = $this->open($this->madrassaGroup())->assertOk();
        $days = collect($response->viewData('days'))->keyBy('date');

        $this->assertTrue($days[self::SUNDAY]['is_off_day']);

        // Saturday works like any other day now.
        $this->assertFalse($days[self::SATURDAY]['is_off_day']);
        $this->assertFalse($days[self::MONDAY]['is_off_day']);

        $response->assertSee('OFF');

        // Off days are not cells: there is nothing on the sheet to mark.
        $cells = $response->viewData('cells');
        $this->assertArrayNotHasKey(StudentAttendance::cellKey($enrollment->id, self::SUNDAY), $cells);
        $this->assertArrayHasKey(StudentAttendance::cellKey($enrollment->id, self::SATURDAY), $cells);
        $this->assertArrayHasKey(StudentAttendance::cellKey($enrollment->id, self::MONDAY), $cells);
    }

    public function test_every_weekday_and_saturday_is_a_teaching_day(): void
    {
        $days = collect(StudentAttendance::monthDays(self::YEAR, self::MONTH))->keyBy('date');

        // The first full week of August 2026: Monday the 3rd to Sunday the
        // 9th. Only the Sunday is off.
        foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07', '2026-08-08'] as $working) {
            $this->assertFalse($days[$working]['is_off_day'], "{$working} should be a working day.");
        }

        $this->assertTrue($days['2026-08-09']['is_off_day']);

        // Five Sundays in the month, so 26 of its 31 days are taught.
        $this->assertSame(26, StudentAttendance::teachingDaysInMonth(self::YEAR, self::MONTH));
    }

    public function test_the_grid_renders_one_control_per_teaching_cell_and_none_on_the_off_day(): void
    {
        $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $this->madrassaEnrollment($this->student('Hassan Raza'));

        $html = $this->open($this->madrassaGroup())->assertOk()->getContent();

        // Two students across twenty-six teaching days, and nothing
        // clickable on the five Sundays.
        $this->assertSame(52, substr_count($html, 'w-7 h-7 rounded border-2'));
        $this->assertSame(5, substr_count($html, '>OFF<'));
    }

    public function test_sunday_attendance_cannot_be_saved(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::SUNDAY)])
            ->assertSessionHasErrors('attendance.0.attendance_date');

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_saturday_attendance_can_be_saved(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::SATURDAY)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_attendances', [
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => self::SATURDAY,
            'status' => 'Present',
        ]);
    }

    public function test_a_sunday_cell_is_rejected_even_among_valid_days(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // The transaction is all or nothing, so the good days go with it.
        $this->save($this->madrassaGroup(), [
            $this->cell($enrollment, self::MONDAY),
            $this->cell($enrollment, self::SUNDAY),
            $this->cell($enrollment, self::TUESDAY),
        ])->assertSessionHasErrors('attendance.1.attendance_date');

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_a_saturday_cell_saves_alongside_the_weekdays(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($this->madrassaGroup(), [
            $this->cell($enrollment, self::MONDAY),
            $this->cell($enrollment, self::SATURDAY),
            $this->cell($enrollment, self::TUESDAY),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_attendances', 3);
    }

    public function test_off_days_are_not_counted_as_unmarked(): void
    {
        $this->madrassaEnrollment();

        $summary = $this->open($this->madrassaGroup())->assertOk()->viewData('summary');

        // August 2026 has 26 working days - every day but its five Sundays -
        // and one student on the sheet.
        $this->assertSame(26, $summary['teaching_days']);
        $this->assertSame(1, $summary['students']);
        $this->assertSame(26, $summary['unmarked']);
    }

    /* ---------------------------------------------------------------- */
    /* Track and period */
    /* ---------------------------------------------------------------- */

    public function test_school_offers_the_morning_period_only(): void
    {
        $response = $this->open($this->schoolGroup())->assertOk();

        $this->assertSame(['Morning'], $response->viewData('availablePeriods'));
    }

    public function test_madrassa_offers_morning_afternoon_and_evening(): void
    {
        $this->madrassaEnrollment();

        $response = $this->open($this->madrassaGroup())->assertOk();

        $this->assertSame(['Morning', 'Afternoon', 'Evening'], $response->viewData('availablePeriods'));

        // All three are reachable as period tabs on the loaded sheet.
        foreach (['Morning', 'Afternoon', 'Evening'] as $period) {
            $response->assertSee("attendance_period={$period}", false);
        }
    }

    public function test_school_cannot_be_saved_for_the_afternoon_or_evening(): void
    {
        $enrollment = $this->schoolEnrollment();

        foreach (['Afternoon', 'Evening'] as $period) {
            $this->save(
                $this->schoolGroup(),
                [$this->cell($enrollment, self::MONDAY)],
                ['attendance_period' => $period]
            )->assertSessionHasErrors('attendance_period');
        }

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_each_madrassa_period_saves_independently(): void
    {
        $enrollment = $this->madrassaEnrollment();

        foreach (['Morning', 'Afternoon', 'Evening'] as $period) {
            $this->save(
                $this->madrassaGroup(),
                [$this->cell($enrollment, self::MONDAY)],
                ['attendance_period' => $period]
            )->assertSessionHasNoErrors();
        }

        $this->assertSame(3, StudentAttendance::where('student_academic_enrollment_id', $enrollment->id)->count());

        // And each period's sheet only ever loads its own records.
        $morning = $this->open($this->madrassaGroup(), ['attendance_period' => 'Morning'])->viewData('existingAttendance');
        $this->assertCount(1, $morning);
        $this->assertSame('Morning', $morning->first()->attendance_period);
    }

    public function test_a_track_and_period_mismatch_is_rejected_server_side(): void
    {
        $this->assertFalse(StudentAttendance::periodAllowedForTrack('Evening', 'School'));
        $this->assertTrue(StudentAttendance::periodAllowedForTrack('Evening', 'Madrassa'));

        $school = $this->schoolEnrollment();

        // A browser claiming a school student sits on the madrassa track, to
        // reach the evening period. The track is read from the enrollment.
        $this->save(
            $this->madrassaGroup(),
            [$this->cell($school, self::MONDAY)],
            ['attendance_period' => 'Evening']
        )->assertSessionHasErrors();

        $this->assertDatabaseCount('student_attendances', 0);
    }

    /* ---------------------------------------------------------------- */
    /* Cells */
    /* ---------------------------------------------------------------- */

    public function test_existing_attendance_is_loaded_into_the_right_cells(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($this->madrassaGroup(), [
            $this->cell($enrollment, self::MONDAY, 'Present'),
            $this->cell($enrollment, self::TUESDAY, 'Absent', 'Sick'),
        ])->assertSessionHasNoErrors();

        $cells = $this->open($this->madrassaGroup())->assertOk()->viewData('cells');

        // Marked days come back as they were entered, and everything else
        // stays open for the rest of the transcription.
        $this->assertSame('Present', $cells[StudentAttendance::cellKey($enrollment->id, self::MONDAY)]['status']);
        $this->assertSame('Absent', $cells[StudentAttendance::cellKey($enrollment->id, self::TUESDAY)]['status']);
        $this->assertSame('Sick', $cells[StudentAttendance::cellKey($enrollment->id, self::TUESDAY)]['reason']);
        $this->assertSame('', $cells[StudentAttendance::cellKey($enrollment->id, self::WEDNESDAY)]['status']);
    }

    public function test_a_present_cell_renders_as_present(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::MONDAY, 'Present')]);

        $cell = $this->open($this->madrassaGroup())->viewData('cells')[StudentAttendance::cellKey($enrollment->id, self::MONDAY)];

        $this->assertSame(['status' => 'Present', 'reason' => ''], $cell);
    }

    public function test_an_absent_cell_renders_as_absent_with_its_reason(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::MONDAY, 'Absent', 'Family issue')]);

        $cell = $this->open($this->madrassaGroup())->viewData('cells')[StudentAttendance::cellKey($enrollment->id, self::MONDAY)];

        $this->assertSame(['status' => 'Absent', 'reason' => 'Family issue'], $cell);
    }

    public function test_an_unmarked_cell_renders_as_unmarked(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $cell = $this->open($this->madrassaGroup())->viewData('cells')[StudentAttendance::cellKey($enrollment->id, self::MONDAY)];

        $this->assertSame(['status' => '', 'reason' => ''], $cell);
        $this->assertDatabaseCount('student_attendances', 0);
    }

    /* ---------------------------------------------------------------- */
    /* Status and reason */
    /* ---------------------------------------------------------------- */

    public function test_absent_requires_a_reason(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::MONDAY, 'Absent')])
            ->assertSessionHasErrors('attendance.0.absence_reason');

        $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::MONDAY, 'Absent', '')])
            ->assertSessionHasErrors('attendance.0.absence_reason');

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_absent_saves_with_a_reason(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::MONDAY, 'Absent', 'Sick')])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_attendances', [
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => self::MONDAY,
            'attendance_period' => 'Morning',
            'status' => 'Absent',
            'absence_reason' => 'Sick',
        ]);
    }

    public function test_present_stores_no_absence_reason(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // A reason left over from a correction is cleared by the server.
        $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::MONDAY, 'Present', 'Sick')])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_attendances', [
            'student_academic_enrollment_id' => $enrollment->id,
            'status' => 'Present',
            'absence_reason' => null,
        ]);
    }

    public function test_correcting_an_absence_to_present_clears_the_reason(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::MONDAY, 'Absent', 'Sick')]);
        $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::MONDAY, 'Present')])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_attendances', 1);
        $this->assertDatabaseHas('student_attendances', [
            'status' => 'Present',
            'absence_reason' => null,
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* Saving a month */
    /* ---------------------------------------------------------------- */

    public function test_a_partial_month_can_be_saved_and_continued_later(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // The first sitting: the first week of the paper register.
        $this->save($this->madrassaGroup(), [
            $this->cell($enrollment, self::MONDAY),
            $this->cell($enrollment, self::TUESDAY),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_attendances', 2);

        // A later sitting picks up where it stopped, and what was already
        // entered is still there.
        $this->save($this->madrassaGroup(), [
            $this->cell($enrollment, self::WEDNESDAY, 'Absent', 'Sick'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_attendances', 3);

        $cells = $this->open($this->madrassaGroup())->viewData('cells');
        $this->assertSame('Present', $cells[StudentAttendance::cellKey($enrollment->id, self::MONDAY)]['status']);
        $this->assertSame('Absent', $cells[StudentAttendance::cellKey($enrollment->id, self::WEDNESDAY)]['status']);
    }

    public function test_unmarked_cells_do_not_block_saving(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // One day out of twenty-one, and the rest left blank.
        $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::MONDAY)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_attendances', 1);
    }

    public function test_a_sheet_with_nothing_changed_saves_without_error(): void
    {
        $this->madrassaEnrollment();

        $this->save($this->madrassaGroup(), [])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_unmarked_cells_are_never_turned_into_present(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::MONDAY)])
            ->assertSessionHasNoErrors();

        // Twenty-six teaching days in the month, one of them entered.
        $this->assertSame(1, StudentAttendance::count());
        $this->assertSame(25, $this->open($this->madrassaGroup())->viewData('summary')['unmarked']);
    }

    public function test_existing_attendance_is_updated_rather_than_duplicated(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::MONDAY, 'Present')]);
        $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::MONDAY, 'Absent', 'Sick')])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_attendances', 1);
        $this->assertDatabaseHas('student_attendances', [
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => self::MONDAY,
            'status' => 'Absent',
            'absence_reason' => 'Sick',
        ]);
    }

    public function test_the_same_cell_cannot_be_submitted_twice(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($this->madrassaGroup(), [
            $this->cell($enrollment, self::MONDAY, 'Present'),
            $this->cell($enrollment, self::MONDAY, 'Absent', 'Sick'),
        ])->assertSessionHasErrors('attendance.1.student_academic_enrollment_id');

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_the_monthly_save_runs_in_a_transaction(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // Straight at the writer, past the validation that would normally
        // have caught this: the point is that the transaction, not the
        // request, is what keeps a half-written month from happening.
        try {
            StudentAttendance::recordSheet('Morning', [
                ['student_academic_enrollment_id' => $enrollment->id, 'attendance_date' => self::MONDAY, 'status' => 'Present'],
                ['student_academic_enrollment_id' => $enrollment->id, 'attendance_date' => self::TUESDAY, 'status' => 'Present'],
                // No such enrollment: the foreign key refuses it.
                ['student_academic_enrollment_id' => 999999, 'attendance_date' => self::WEDNESDAY, 'status' => 'Present'],
            ]);

            $this->fail('The sheet was expected to fail on the unknown enrollment.');
        } catch (QueryException) {
            // Expected.
        }

        // Nothing at all, rather than the two days already written.
        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_a_whole_month_of_several_students_saves_together(): void
    {
        $enrollments = collect(range(1, 4))
            ->map(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        $teachingDays = collect(StudentAttendance::monthDays(self::YEAR, self::MONTH))
            ->reject(fn ($day) => $day['is_off_day'])
            ->pluck('date');

        $cells = $enrollments
            ->crossJoin($teachingDays)
            ->map(fn ($pair) => $this->cell($pair[0], $pair[1]))
            ->all();

        $this->save($this->madrassaGroup(), $cells)->assertSessionHasNoErrors();

        // Four students across twenty-six teaching days.
        $this->assertDatabaseCount('student_attendances', 104);
        $this->assertSame(0, $this->open($this->madrassaGroup())->viewData('summary')['unmarked']);
    }

    /* ---------------------------------------------------------------- */
    /* Historical and out-of-month dates */
    /* ---------------------------------------------------------------- */

    public function test_a_historical_month_can_be_entered(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // A month well in the past, which is the normal case: the paper
        // register is transcribed after the month has ended.
        $this->save(
            $this->madrassaGroup(),
            [$this->cell($enrollment, '2026-04-06')],
            ['month' => 4, 'year' => 2026]
        )->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_attendances', [
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => '2026-04-06',
        ]);
    }

    public function test_a_date_is_not_blocked_for_being_later_than_today(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // The last weekday of the month, whether or not it has arrived: the
        // sheet transcribes a register, it does not police the calendar.
        $this->save($this->madrassaGroup(), [$this->cell($enrollment, '2026-08-31')])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_attendances', [
            'attendance_date' => '2026-08-31',
        ]);
    }

    public function test_a_date_outside_the_selected_month_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // September, submitted against the August sheet.
        $this->save($this->madrassaGroup(), [$this->cell($enrollment, '2026-09-01')])
            ->assertSessionHasErrors('attendance.0.attendance_date');

        $this->assertDatabaseCount('student_attendances', 0);
    }

    /* ---------------------------------------------------------------- */
    /* Groups, sections and filters */
    /* ---------------------------------------------------------------- */

    public function test_the_section_filter_shows_only_that_section(): void
    {
        $inSection = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $otherSection = $this->madrassaEnrollment($this->student('Bilal Khan'), ['section_id' => $this->hifzB->id]);

        $sheet = $this->open($this->madrassaGroup())->assertOk()->viewData('sheet');

        $this->assertSame([$inSection->id], $sheet->pluck('id')->all());
        $this->assertNotContains($otherSection->id, $sheet->pluck('id')->all());
    }

    public function test_a_blank_section_draws_the_whole_class(): void
    {
        $sectionA = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $sectionB = $this->madrassaEnrollment($this->student('Bilal Khan'), ['section_id' => $this->hifzB->id]);
        $noSection = $this->madrassaEnrollment($this->student('Usman Tariq'), ['section_id' => null]);

        $sheet = $this->open($this->madrassaGroup(['section_id' => null]))->assertOk()->viewData('sheet');

        $this->assertEqualsCanonicalizing(
            [$sectionA->id, $sectionB->id, $noSection->id],
            $sheet->pluck('id')->all()
        );
    }

    public function test_the_sheet_filters_on_the_enrollment_relationships(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->assertCount(0, $this->open($this->madrassaGroup(['academic_session_id' => $this->otherSession->id]))->viewData('sheet'));
        $this->assertCount(0, $this->open($this->madrassaGroup(['academic_track' => 'School']))->viewData('sheet'));
        $this->assertCount(0, $this->open($this->madrassaGroup(['department_id' => $this->school->id]))->viewData('sheet'));
        $this->assertCount(0, $this->open($this->madrassaGroup(['academic_class_id' => $this->nazra->id]))->viewData('sheet'));

        $this->assertSame([$enrollment->id], $this->open($this->madrassaGroup())->viewData('sheet')->pluck('id')->all());
    }

    public function test_inactive_enrollments_do_not_appear_on_the_sheet(): void
    {
        $active = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $this->madrassaEnrollment($this->student('Completed Student'), ['status' => 'Completed']);
        $this->madrassaEnrollment($this->student('Left Student'), ['status' => 'Left']);

        $sheet = $this->open($this->madrassaGroup())->assertOk()->viewData('sheet');

        $this->assertSame([$active->id], $sheet->pluck('id')->all());
    }

    public function test_students_are_listed_in_a_deterministic_order(): void
    {
        $third = $this->madrassaEnrollment(tap($this->student('Zaid'))->update(['roll_number' => '03']));
        $first = $this->madrassaEnrollment(tap($this->student('Ahmed'))->update(['roll_number' => '01']));
        $second = $this->madrassaEnrollment(tap($this->student('Bilal'))->update(['roll_number' => '02']));

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $this->open($this->madrassaGroup())->viewData('sheet')->pluck('id')->all()
        );
    }

    /* ---------------------------------------------------------------- */
    /* Dual track */
    /* ---------------------------------------------------------------- */

    public function test_a_dual_track_student_appears_on_the_madrassa_sheet(): void
    {
        $student = $this->student('Ahmed Ali');
        $madrassa = $this->madrassaEnrollment($student);
        $this->schoolEnrollment($student);

        $sheet = $this->open($this->madrassaGroup())->assertOk()->viewData('sheet');

        $this->assertSame([$madrassa->id], $sheet->pluck('id')->all());
    }

    public function test_a_dual_track_student_appears_on_the_school_sheet(): void
    {
        $student = $this->student('Ahmed Ali');
        $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $sheet = $this->open($this->schoolGroup())->assertOk()->viewData('sheet');

        $this->assertSame([$school->id], $sheet->pluck('id')->all());
    }

    public function test_madrassa_attendance_never_changes_school_attendance(): void
    {
        $student = $this->student('Ahmed Ali');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->save($this->madrassaGroup(), [
            $this->cell($madrassa, self::MONDAY, 'Absent', 'Sick'),
            $this->cell($madrassa, self::TUESDAY),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, StudentAttendance::where('student_academic_enrollment_id', $madrassa->id)->count());
        $this->assertSame(0, StudentAttendance::where('student_academic_enrollment_id', $school->id)->count());

        $this->assertCount(0, $this->open($this->schoolGroup())->viewData('existingAttendance'));
    }

    public function test_school_attendance_never_changes_madrassa_attendance(): void
    {
        $student = $this->student('Ahmed Ali');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->save($this->schoolGroup(), [$this->cell($school, self::MONDAY)])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, StudentAttendance::where('student_academic_enrollment_id', $school->id)->count());
        $this->assertSame(0, StudentAttendance::where('student_academic_enrollment_id', $madrassa->id)->count());

        // And the madrassa track still has all three of its own periods.
        $this->save(
            $this->madrassaGroup(),
            [$this->cell($madrassa, self::MONDAY)],
            ['attendance_period' => 'Evening']
        )->assertSessionHasNoErrors();

        $this->assertSame(1, StudentAttendance::where('student_academic_enrollment_id', $madrassa->id)->count());
        $this->assertSame(2, StudentAttendance::count());
    }

    /* ---------------------------------------------------------------- */
    /* Security and integrity */
    /* ---------------------------------------------------------------- */

    public function test_an_authenticated_admin_can_open_the_attendance_page(): void
    {
        $this->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Monthly Attendance Entry')
            ->assertSee('Select Attendance Sheet');
    }

    public function test_a_guest_cannot_open_the_attendance_page(): void
    {
        auth()->logout();

        $this->get(route('attendance.index'))->assertRedirect(route('login'));
        $this->post(route('attendance.store'), [])->assertRedirect(route('login'));
    }

    public function test_attendance_cannot_be_recorded_for_an_inactive_enrollment(): void
    {
        $completed = $this->madrassaEnrollment($this->student('Completed Student'), ['status' => 'Completed']);
        $left = $this->madrassaEnrollment($this->student('Left Student'), ['status' => 'Left']);

        foreach ([$completed, $left] as $enrollment) {
            $this->save($this->madrassaGroup(), [$this->cell($enrollment, self::MONDAY)])
                ->assertSessionHasErrors('attendance.0.student_academic_enrollment_id');
        }

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_attendance_cannot_be_recorded_against_an_enrollment_from_another_group(): void
    {
        $outsider = $this->madrassaEnrollment($this->student('Bilal Khan'), ['section_id' => $this->hifzB->id]);

        $this->save($this->madrassaGroup(), [$this->cell($outsider, self::MONDAY)])
            ->assertSessionHasErrors('attendance.0.student_academic_enrollment_id');

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_an_invalid_enrollment_id_is_rejected(): void
    {
        $this->save($this->madrassaGroup(), [
            ['student_academic_enrollment_id' => 999999, 'attendance_date' => self::MONDAY, 'status' => 'Present'],
        ])->assertSessionHasErrors('attendance.0.student_academic_enrollment_id');

        $this->save($this->madrassaGroup(), [
            ['student_academic_enrollment_id' => 'not-an-id', 'attendance_date' => self::MONDAY, 'status' => 'Present'],
        ])->assertSessionHasErrors('attendance.0.student_academic_enrollment_id');

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_a_sheet_that_is_not_valid_json_is_rejected_rather_than_guessed_at(): void
    {
        $this->madrassaEnrollment();

        $this->post(route('attendance.store'), $this->madrassaGroup() + [
            'month' => self::MONTH,
            'year' => self::YEAR,
            'attendance_period' => 'Morning',
            'sheet' => 'not json at all',
        ])->assertSessionHasNoErrors();

        // Unreadable rows are simply not rows: nothing is invented from them.
        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_the_sheet_does_not_run_a_query_per_student_or_per_day(): void
    {
        collect(range(1, 3))->each(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        $small = $this->queriesToOpenTheSheet();

        collect(range(4, 25))->each(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        $large = $this->queriesToOpenTheSheet();

        $this->assertCount(25, $this->open($this->madrassaGroup())->viewData('sheet'));

        // Eight times the students over a month of columns, and no more
        // queries: the month is loaded in one read, not one per cell.
        $this->assertLessThanOrEqual($small, $large);
        $this->assertLessThan(25, $large);
    }

    /**
     * Count the queries one sheet costs to draw.
     *
     * Logging is switched on around the request alone, so the students set
     * up for the comparison are not counted as part of drawing it.
     */
    private function queriesToOpenTheSheet(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->open($this->madrassaGroup())->assertOk();

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    }
}
