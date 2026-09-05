<?php

namespace Tests\Feature;

use App\Models\StudentAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers the absence reasons the attendance sheet records.
 *
 * The reason is free text on a nullable varchar, so the rules are only that
 * an absence must carry one and that it fits the column. The list on the
 * model is a set of suggestions the sheet offers as one click; it narrows
 * nothing, which is what lets an administrator write down what actually
 * happened.
 *
 * "Absent" was added to that list. It is a reason, not a status: the status
 * is Absent already, and the two-value status list is untouched.
 */
class AttendanceAbsenceReasonTest extends TestCase
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
    /* The list */
    /* ---------------------------------------------------------------- */

    public function test_absent_is_offered_as_an_absence_reason(): void
    {
        $this->assertContains('Absent', StudentAttendance::ABSENCE_REASONS);
    }

    public function test_the_existing_reasons_are_all_still_offered(): void
    {
        foreach (['Sick', 'Family issue', 'Emergency', 'Personal reason', 'Other'] as $reason) {
            $this->assertContains($reason, StudentAttendance::ABSENCE_REASONS);
        }
    }

    public function test_absent_is_a_reason_and_not_a_status(): void
    {
        // The status list is untouched by the new reason.
        $this->assertSame(['Present', 'Absent'], StudentAttendance::STATUSES);
    }

    public function test_the_sheet_offers_every_reason_in_its_datalist(): void
    {
        $this->madrassaEnrollment();

        $html = $this->openSheet()->assertOk()->getContent();

        foreach (StudentAttendance::ABSENCE_REASONS as $reason) {
            $this->assertStringContainsString(
                '<option value="'.$reason.'">',
                $html,
                "The sheet should suggest {$reason}."
            );
        }
    }

    /* ---------------------------------------------------------------- */
    /* Saving */
    /* ---------------------------------------------------------------- */

    public function test_an_absence_can_be_saved_with_the_reason_absent(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($enrollment, self::MONDAY, 'Absent', 'Absent')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_attendances', [
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => self::MONDAY,
            'status' => 'Absent',
            'absence_reason' => 'Absent',
        ]);
    }

    public function test_every_offered_reason_can_be_saved(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $dates = [self::MONDAY, self::TUESDAY, self::WEDNESDAY, self::THURSDAY, self::FRIDAY, self::SATURDAY];

        foreach (StudentAttendance::ABSENCE_REASONS as $index => $reason) {
            $this->save($enrollment, $dates[$index], 'Absent', $reason)
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('student_attendances', [
                'attendance_date' => $dates[$index],
                'status' => 'Absent',
                'absence_reason' => $reason,
            ]);
        }
    }

    public function test_an_absence_reason_can_be_edited_to_absent(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($enrollment, self::MONDAY, 'Absent', 'Sick')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_attendances', ['absence_reason' => 'Sick']);

        // Re-saving the same cell corrects the reason in place.
        $this->save($enrollment, self::MONDAY, 'Absent', 'Absent')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('student_attendances', 1);
        $this->assertDatabaseHas('student_attendances', [
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => self::MONDAY,
            'absence_reason' => 'Absent',
        ]);
    }

    public function test_a_reason_outside_the_suggestions_is_still_accepted(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // The list is suggestions, not a closed set.
        $this->save($enrollment, self::MONDAY, 'Absent', 'Attending a funeral in Multan')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_attendances', [
            'absence_reason' => 'Attending a funeral in Multan',
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* What is still refused */
    /* ---------------------------------------------------------------- */

    public function test_an_absence_without_a_reason_is_still_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($enrollment, self::MONDAY, 'Absent', null)
            ->assertSessionHasErrors('attendance.0.absence_reason');

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_a_reason_longer_than_the_column_is_still_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($enrollment, self::MONDAY, 'Absent', str_repeat('a', 256))
            ->assertSessionHasErrors('attendance.0.absence_reason');

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_an_invalid_status_is_still_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // "Absent" belongs in the reason, never in the status.
        $this->save($enrollment, self::MONDAY, 'Late', 'Absent')
            ->assertSessionHasErrors('attendance.0.status');

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_a_present_record_keeps_no_reason(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // A reason only ever describes an absence.
        $this->save($enrollment, self::MONDAY, 'Present', 'Absent')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_attendances', [
            'student_academic_enrollment_id' => $enrollment->id,
            'status' => 'Present',
            'absence_reason' => null,
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* Reading it back */
    /* ---------------------------------------------------------------- */

    public function test_the_student_history_shows_the_absent_reason(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($enrollment, self::MONDAY, 'Absent', 'Absent')->assertSessionHasNoErrors();

        $this->get(route('students.attendance', [
            'student' => $enrollment->student_id,
            'month' => self::MONTH,
            'year' => self::YEAR,
        ]))->assertOk()->assertSee('Absent');
    }

    public function test_the_printed_history_shows_the_absent_reason(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->save($enrollment, self::MONDAY, 'Absent', 'Absent')->assertSessionHasNoErrors();

        $records = $this->get(route('students.attendance.print', [
            'student' => $enrollment->student_id,
            'academic_track' => 'Madrassa',
            'attendance_period' => 'Morning',
            'month' => self::MONTH,
            'year' => self::YEAR,
        ]))->assertOk()->viewData('records');

        $marked = collect($records)->firstWhere('absence_reason', 'Absent');

        $this->assertNotNull($marked, 'The printed history should carry the reason.');
        $this->assertSame('Absent', $marked->status);
    }

    /* ---------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    private function group(): array
    {
        return [
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'attendance_period' => 'Morning',
            'month' => self::MONTH,
            'year' => self::YEAR,
        ];
    }

    private function openSheet()
    {
        return $this->get(route('attendance.index', $this->group()));
    }

    private function save($enrollment, string $date, string $status, ?string $reason)
    {
        return $this->post(route('attendance.store'), [
            ...$this->group(),
            'sheet' => json_encode([[
                'student_academic_enrollment_id' => $enrollment->id,
                'attendance_date' => $date,
                'status' => $status,
                'absence_reason' => $reason,
            ]]),
        ]);
    }
}
