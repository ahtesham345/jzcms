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
 * Covers one student's attendance history.
 *
 * A reading page over the rows the monthly sheet writes. Two things matter
 * most here: a dual-track student's two enrollments must never be shown as
 * one history, and a day nobody transcribed must never appear as a record.
 */
class StudentAttendanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    /** August 2026. */
    private const MONDAY = '2026-08-03';

    private const TUESDAY = '2026-08-04';

    private const WEDNESDAY = '2026-08-05';

    private AcademicSession $session;

    private AcademicSession $otherSession;

    private Department $hifz;

    private Department $school;

    private AcademicClass $hifzClass;

    private AcademicClass $nazra;

    private AcademicClass $primary;

    private Section $hifzA;

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
     * Record one attendance row directly.
     *
     * The same table and the same model the monthly sheet writes through:
     * this chunk adds no second way of storing attendance.
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
     * Open a student's attendance history.
     */
    private function open(Student $student, array $filters = [])
    {
        return $this->get(route('students.attendance', array_merge(
            ['student' => $student->id],
            $filters
        )));
    }

    /**
     * The August 2026 filters, which is the month the fixtures use.
     *
     * @return array<string, mixed>
     */
    private function august(array $overrides = []): array
    {
        return array_merge(['month' => 8, 'year' => 2026], $overrides);
    }

    /* ---------------------------------------------------------------- */
    /* Access */
    /* ---------------------------------------------------------------- */

    public function test_the_history_page_requires_authentication(): void
    {
        $student = $this->student();

        auth()->logout();

        $this->open($student)->assertRedirect(route('login'));
    }

    public function test_an_unknown_student_returns_404(): void
    {
        $this->get(route('students.attendance', ['student' => 999999]))->assertNotFound();
    }

    public function test_the_history_page_loads(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $this->record($enrollment, self::MONDAY);

        $this->open($enrollment->student, $this->august())
            ->assertOk()
            ->assertSee('Attendance History')
            ->assertSee('Ahmed Ali')
            ->assertSee($enrollment->student->registration_number);
    }

    public function test_the_student_profile_links_to_the_attendance_history(): void
    {
        $student = $this->student();
        $this->madrassaEnrollment($student);

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('View Attendance History')
            ->assertSee(route('students.attendance', $student->id), false);
    }

    /* ---------------------------------------------------------------- */
    /* Student scoping */
    /* ---------------------------------------------------------------- */

    public function test_only_the_selected_students_attendance_is_shown(): void
    {
        $mine = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $theirs = $this->madrassaEnrollment($this->student('Hassan Raza'));

        $this->record($mine, self::MONDAY);
        $theirs = $this->record($theirs, self::MONDAY);

        $records = $this->open($mine->student, $this->august())->assertOk()->viewData('records');

        $this->assertCount(1, $records);
        $this->assertSame($mine->id, $records->first()->student_academic_enrollment_id);
        $this->assertNotContains($theirs->id, $records->pluck('id')->all());
    }

    public function test_another_students_attendance_cannot_be_reached_through_the_filters(): void
    {
        $mine = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $other = $this->madrassaEnrollment($this->student('Hassan Raza'));

        $this->record($other, self::MONDAY);

        // Every filter combination is still scoped to the student in the
        // route: the enrollment ids only ever come from that student.
        foreach ([
            [],
            ['academic_session_id' => $this->session->id],
            ['academic_track' => 'Madrassa'],
            ['attendance_period' => 'Morning'],
            ['student_id' => $other->student_id],
            ['student_academic_enrollment_id' => $other->id],
        ] as $extra) {
            $records = $this->open($mine->student, $this->august($extra))->assertOk()->viewData('records');

            $this->assertCount(0, $records);
        }
    }

    /* ---------------------------------------------------------------- */
    /* Track and period */
    /* ---------------------------------------------------------------- */

    public function test_school_attendance_displays(): void
    {
        $enrollment = $this->schoolEnrollment($this->student('Ahmed Ali'));
        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        $this->record($enrollment, self::TUESDAY, 'Morning', 'Absent', 'Sick');

        $response = $this->open($enrollment->student, $this->august(['academic_track' => 'School']))->assertOk();

        $this->assertCount(2, $response->viewData('records'));
        $response->assertSee('Morning')->assertSee('Absent')->assertSee('Sick');
    }

    public function test_school_offers_the_morning_period_only(): void
    {
        $enrollment = $this->schoolEnrollment();
        $this->record($enrollment, self::MONDAY);

        $response = $this->open($enrollment->student, $this->august(['academic_track' => 'School']))->assertOk();

        $this->assertSame(['Morning'], $response->viewData('availablePeriods'));
        $response->assertDontSee('value="Afternoon"', false)
            ->assertDontSee('value="Evening"', false);
    }

    public function test_switching_to_school_forces_the_period_back_to_morning(): void
    {
        $student = $this->student('Ahmed Ali');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->record($madrassa, self::MONDAY, 'Evening');
        $this->record($school, self::MONDAY, 'Morning');

        // Evening carried over from the madrassa view. School has no
        // evening, so the filter is pinned back to Morning rather than
        // silently returning nothing.
        $response = $this->open($student, $this->august([
            'academic_track' => 'School',
            'attendance_period' => 'Evening',
        ]))->assertOk();

        $this->assertSame('Morning', $response->viewData('filters')['attendance_period']);
        $this->assertCount(1, $response->viewData('records'));
        $this->assertSame('Morning', $response->viewData('records')->first()->attendance_period);
    }

    public function test_each_madrassa_period_displays(): void
    {
        $enrollment = $this->madrassaEnrollment();

        foreach (['Morning', 'Afternoon', 'Evening'] as $period) {
            $this->record($enrollment, self::MONDAY, $period);
        }

        foreach (['Morning', 'Afternoon', 'Evening'] as $period) {
            $records = $this->open($enrollment->student, $this->august([
                'academic_track' => 'Madrassa',
                'attendance_period' => $period,
            ]))->assertOk()->viewData('records');

            $this->assertCount(1, $records);
            $this->assertSame($period, $records->first()->attendance_period);
        }
    }

    public function test_madrassa_all_periods_displays_all_three(): void
    {
        $enrollment = $this->madrassaEnrollment();

        foreach (['Morning', 'Afternoon', 'Evening'] as $period) {
            $this->record($enrollment, self::MONDAY, $period);
        }

        $response = $this->open($enrollment->student, $this->august([
            'academic_track' => 'Madrassa',
            'attendance_period' => 'all',
        ]))->assertOk();

        $this->assertSame(['Morning', 'Afternoon', 'Evening'], $response->viewData('availablePeriods'));
        $this->assertSame(
            ['Morning', 'Afternoon', 'Evening'],
            $response->viewData('records')->pluck('attendance_period')->all()
        );
    }

    public function test_the_track_filter_selects_which_enrollment_is_read(): void
    {
        $student = $this->student('Ahmed Ali');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->record($madrassa, self::MONDAY, 'Morning', 'Absent', 'Sick');
        $this->record($school, self::MONDAY, 'Morning', 'Present');

        $madrassaRecords = $this->open($student, $this->august(['academic_track' => 'Madrassa']))->viewData('records');
        $schoolRecords = $this->open($student, $this->august(['academic_track' => 'School']))->viewData('records');

        $this->assertSame([$madrassa->id], $madrassaRecords->pluck('student_academic_enrollment_id')->all());
        $this->assertSame([$school->id], $schoolRecords->pluck('student_academic_enrollment_id')->all());
    }

    /* ---------------------------------------------------------------- */
    /* Dual track */
    /* ---------------------------------------------------------------- */

    public function test_a_dual_track_students_school_attendance_stays_separate(): void
    {
        $student = $this->student('Ahmed Ali');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        // The same day, marked differently on each track.
        $this->record($school, self::MONDAY, 'Morning', 'Present');
        $this->record($madrassa, self::MONDAY, 'Morning', 'Absent', 'Sick');
        $this->record($madrassa, self::MONDAY, 'Afternoon', 'Present');
        $this->record($madrassa, self::MONDAY, 'Evening', 'Present');

        $records = $this->open($student, $this->august(['academic_track' => 'School']))->assertOk()->viewData('records');

        // One record, the school one, and nothing borrowed from madrassa.
        $this->assertCount(1, $records);
        $this->assertSame('Present', $records->first()->status);
        $this->assertSame($school->id, $records->first()->student_academic_enrollment_id);
    }

    public function test_a_dual_track_students_madrassa_attendance_stays_separate(): void
    {
        $student = $this->student('Ahmed Ali');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->record($school, self::MONDAY, 'Morning', 'Present');
        $this->record($madrassa, self::MONDAY, 'Morning', 'Absent', 'Sick');
        $this->record($madrassa, self::MONDAY, 'Afternoon', 'Present');
        $this->record($madrassa, self::MONDAY, 'Evening', 'Present');

        $response = $this->open($student, $this->august([
            'academic_track' => 'Madrassa',
            'attendance_period' => 'all',
        ]))->assertOk();

        $records = $response->viewData('records');

        $this->assertCount(3, $records);
        $this->assertSame([$madrassa->id, $madrassa->id, $madrassa->id], $records->pluck('student_academic_enrollment_id')->all());
        $this->assertSame(['Absent', 'Present', 'Present'], $records->pluck('status')->all());

        // Three records for one day, told apart by their period.
        $this->assertSame(['Morning', 'Afternoon', 'Evening'], $records->pluck('attendance_period')->all());
    }

    /* ---------------------------------------------------------------- */
    /* Session, month and year */
    /* ---------------------------------------------------------------- */

    public function test_the_academic_session_filter_uses_the_enrollment_relationship(): void
    {
        $student = $this->student('Ahmed Ali');

        $thisYear = $this->madrassaEnrollment($student, ['status' => 'Completed']);
        $nextYear = $this->madrassaEnrollment($student, [
            'academic_session_id' => $this->otherSession->id,
            'academic_class_id' => $this->nazra->id,
            'start_date' => '2027-04-01',
        ]);

        $this->record($thisYear, self::MONDAY);
        $this->record($nextYear, self::TUESDAY);

        $current = $this->open($student, $this->august(['academic_session_id' => $this->session->id]))->viewData('records');
        $this->assertSame([$thisYear->id], $current->pluck('student_academic_enrollment_id')->all());

        $other = $this->open($student, $this->august(['academic_session_id' => $this->otherSession->id]))->viewData('records');
        $this->assertSame([$nextYear->id], $other->pluck('student_academic_enrollment_id')->all());

        // Both sessions when none is chosen.
        $this->assertCount(2, $this->open($student, $this->august())->viewData('records'));
    }

    public function test_the_month_filter_works(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->record($enrollment, self::MONDAY);
        $this->record($enrollment, '2026-09-07');

        $this->assertCount(1, $this->open($enrollment->student, ['month' => 8, 'year' => 2026])->viewData('records'));
        $this->assertCount(1, $this->open($enrollment->student, ['month' => 9, 'year' => 2026])->viewData('records'));
        $this->assertCount(0, $this->open($enrollment->student, ['month' => 10, 'year' => 2026])->viewData('records'));
    }

    public function test_the_year_filter_works(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->record($enrollment, self::MONDAY);
        $this->record($enrollment, '2027-08-02');

        $this->assertCount(1, $this->open($enrollment->student, ['month' => 8, 'year' => 2026])->viewData('records'));
        $this->assertCount(1, $this->open($enrollment->student, ['month' => 8, 'year' => 2027])->viewData('records'));
    }

    /* ---------------------------------------------------------------- */
    /* Display */
    /* ---------------------------------------------------------------- */

    public function test_an_absence_shows_its_reason(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $this->record($enrollment, self::MONDAY, 'Morning', 'Absent', 'Family issue');

        $this->open($enrollment->student, $this->august())
            ->assertOk()
            ->assertSee('Absent')
            ->assertSee('Family issue');
    }

    public function test_a_present_record_shows_no_absence_reason(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // A reason left on a Present row could only be stale; the page must
        // not surface it whatever the column holds.
        $record = $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        DB::table('student_attendances')->where('id', $record->id)->update(['absence_reason' => 'Stale reason']);

        $this->open($enrollment->student, $this->august())
            ->assertOk()
            ->assertSee('Present')
            ->assertDontSee('Stale reason');
    }

    public function test_unmarked_days_never_appear_as_attendance_records(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // One day out of a whole month.
        $this->record($enrollment, self::MONDAY);

        $response = $this->open($enrollment->student, $this->august())->assertOk();

        $this->assertCount(1, $response->viewData('records'));
        $this->assertSame(1, $response->viewData('summary')['total']);

        // And looking at the page creates nothing.
        $this->assertDatabaseCount('student_attendances', 1);
    }

    public function test_the_empty_state_is_shown_when_nothing_is_recorded(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->open($enrollment->student, $this->august())
            ->assertOk()
            ->assertSee('No attendance records found for the selected filters.');

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_the_summary_counts_the_selected_filters(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        $this->record($enrollment, self::TUESDAY, 'Morning', 'Present');
        $this->record($enrollment, self::WEDNESDAY, 'Morning', 'Absent', 'Sick');
        $this->record($enrollment, self::MONDAY, 'Evening', 'Absent', 'Sick');
        // Another month, which the filters exclude.
        $this->record($enrollment, '2026-09-07', 'Morning', 'Present');

        $all = $this->open($enrollment->student, $this->august(['attendance_period' => 'all']))->viewData('summary');
        $this->assertSame(['total' => 4, 'present' => 2, 'absent' => 2], $all);

        $morning = $this->open($enrollment->student, $this->august(['attendance_period' => 'Morning']))->viewData('summary');
        $this->assertSame(['total' => 3, 'present' => 2, 'absent' => 1], $morning);
    }

    /* ---------------------------------------------------------------- */
    /* Ordering */
    /* ---------------------------------------------------------------- */

    public function test_records_are_ordered_newest_date_first(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->record($enrollment, self::MONDAY);
        $this->record($enrollment, self::WEDNESDAY);
        $this->record($enrollment, self::TUESDAY);

        $dates = $this->open($enrollment->student, $this->august())
            ->viewData('records')
            ->map(fn ($record) => $record->attendance_date->format('Y-m-d'))
            ->all();

        $this->assertSame([self::WEDNESDAY, self::TUESDAY, self::MONDAY], $dates);
    }

    public function test_same_day_madrassa_periods_are_ordered_morning_afternoon_evening(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // Written out of order on purpose: the ordering must come from the
        // query, not from the order they happened to be entered.
        $this->record($enrollment, self::MONDAY, 'Evening');
        $this->record($enrollment, self::MONDAY, 'Morning');
        $this->record($enrollment, self::MONDAY, 'Afternoon');

        $this->record($enrollment, self::TUESDAY, 'Afternoon');
        $this->record($enrollment, self::TUESDAY, 'Morning');

        $ordered = $this->open($enrollment->student, $this->august(['attendance_period' => 'all']))
            ->viewData('records')
            ->map(fn ($record) => $record->attendance_date->format('d').' '.$record->attendance_period)
            ->all();

        $this->assertSame([
            '04 Morning', '04 Afternoon',
            '03 Morning', '03 Afternoon', '03 Evening',
        ], $ordered);
    }

    /* ---------------------------------------------------------------- */
    /* Historical context */
    /* ---------------------------------------------------------------- */

    public function test_historical_context_survives_a_promotion(): void
    {
        $student = $this->student('Ahmed Ali');
        $old = $this->madrassaEnrollment($student, ['academic_class_id' => $this->nazra->id]);

        $this->record($old, self::MONDAY, 'Morning', 'Absent', 'Sick');

        // Promoted into the next class and session; the old placement is
        // closed but the attendance it carries is untouched.
        $student->promote([
            'academic_track' => 'Madrassa',
            'academic_session_id' => $this->otherSession->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'promotion_date' => '2026-09-01',
        ]);

        $record = $this->open($student, $this->august())->assertOk()->viewData('records')->first();

        // The record still names the class it was taken in, not the one the
        // student has since moved to.
        $this->assertSame($old->id, $record->student_academic_enrollment_id);
        $this->assertSame($this->nazra->id, $record->studentAcademicEnrollment->academic_class_id);
        $this->assertSame('Completed', $record->studentAcademicEnrollment->status);
        $this->assertSame('Sick', $record->absence_reason);
    }

    public function test_inactive_master_data_does_not_hide_historical_attendance(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $this->record($enrollment, self::MONDAY);

        // The department, class and section are all retired afterwards.
        $this->hifz->update(['status' => false]);
        $this->hifzClass->update(['status' => false]);
        $this->hifzA->update(['status' => false]);

        $response = $this->open($enrollment->student, $this->august())->assertOk();

        $this->assertCount(1, $response->viewData('records'));
        $response->assertSee($this->hifzClass->name)->assertSee($this->hifzA->name);
    }

    public function test_the_session_filter_offers_sessions_the_student_was_enrolled_in(): void
    {
        $student = $this->student('Ahmed Ali');
        $this->madrassaEnrollment($student);

        $sessions = $this->open($student, $this->august())->assertOk()->viewData('academicSessions');

        $this->assertSame([$this->session->id], $sessions->pluck('id')->all());
    }

    /* ---------------------------------------------------------------- */
    /* Pagination and performance */
    /* ---------------------------------------------------------------- */

    public function test_the_history_is_paginated(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $this->recordAugust($enrollment);

        $first = $this->open($enrollment->student, $this->august(['attendance_period' => 'all']))->assertOk();

        $this->assertSame(63, $first->viewData('records')->total());
        $this->assertCount(25, $first->viewData('records'));

        $last = $this->open($enrollment->student, $this->august(['attendance_period' => 'all', 'page' => 3]))->assertOk();

        $this->assertCount(13, $last->viewData('records'));
        $this->assertSame(3, $last->viewData('records')->currentPage());
    }

    public function test_filters_survive_pagination(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $this->recordAugust($enrollment);

        $response = $this->open($enrollment->student, $this->august([
            'attendance_period' => 'all',
            'academic_track' => 'Madrassa',
            'page' => 2,
        ]))->assertOk();

        $records = $response->viewData('records');

        // Page two of the same filtered set, not an unfiltered page two.
        $this->assertSame(63, $records->total());
        $this->assertSame(2, $records->currentPage());
        $this->assertCount(25, $records);

        // The links carry every filter forward.
        foreach (['attendance_period=all', 'academic_track=Madrassa', 'month=8', 'year=2026'] as $parameter) {
            $this->assertStringContainsString($parameter, urldecode($records->previousPageUrl()));
            $this->assertStringContainsString($parameter, urldecode($records->nextPageUrl()));
        }

        // And a narrower period filter still narrows the whole set, not
        // just the page being looked at.
        $morning = $this->open($enrollment->student, $this->august(['attendance_period' => 'Morning']))->viewData('records');

        $this->assertSame(21, $morning->total());
        $this->assertSame(['Morning'], $morning->pluck('attendance_period')->unique()->values()->all());
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_records(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->record($enrollment, self::MONDAY, 'Morning');
        $small = $this->queriesToOpenTheHistory($enrollment->student);

        $this->recordAugust($enrollment, skipExisting: true);
        $large = $this->queriesToOpenTheHistory($enrollment->student);

        $this->assertSame(
            63,
            $this->open($enrollment->student, $this->august(['attendance_period' => 'all']))->viewData('records')->total()
        );

        // Sixty-three records instead of one, and no extra queries: the
        // enrollment and its master data are eager loaded, not fetched per
        // row.
        $this->assertLessThanOrEqual($small, $large);
        $this->assertLessThan(25, $large);
    }

    /**
     * Fill August 2026 with every teaching day across all three periods.
     */
    private function recordAugust(StudentAcademicEnrollment $enrollment, bool $skipExisting = false): void
    {
        foreach (StudentAttendance::monthDays(2026, 8) as $day) {
            if ($day['is_off_day']) {
                continue;
            }

            foreach (['Morning', 'Afternoon', 'Evening'] as $period) {
                if ($skipExisting && $day['date'] === self::MONDAY && $period === 'Morning') {
                    continue;
                }

                $this->record($enrollment, $day['date'], $period);
            }
        }
    }

    /**
     * Count the queries one history page costs.
     */
    private function queriesToOpenTheHistory(Student $student): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->open($student, $this->august(['attendance_period' => 'all']))->assertOk();

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    }
}
