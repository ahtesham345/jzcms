<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use Database\Seeders\AdmissionDepartmentClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Covers promoting a madrassa student between stages mid-session.
 *
 * The institution runs one academic session across the school and the
 * madrassa, but the two progress differently. A school class runs for the
 * year; a madrassa stage finishes when the student finishes it, which may be
 * three months in. Nothing about the session's end date may stand in the way
 * of recording that.
 *
 * The session itself is untouched by this: the promotion still files into an
 * academic session, and the enrollment history still records which one.
 */
class MadrassaStagePromotionTest extends TestCase
{
    use RefreshDatabase;

    /** The session the Imam described: 5 April 2026 to 5 April 2027. */
    private AcademicSession $session;

    private AcademicSession $nextSession;

    private Department $hifz;

    private Department $school;

    private AcademicClass $nazra;

    private AcademicClass $hifzClass;

    private AcademicClass $gardan;

    private AcademicClass $primary;

    private AcademicClass $middle;

    private Section $nazraA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->seed(AdmissionDepartmentClassSeeder::class);

        $this->session = AcademicSession::create([
            'name' => '2026-2027',
            'start_date' => '2026-04-05',
            'end_date' => '2027-04-05',
            'is_current' => true,
            'status' => true,
        ]);

        $this->nextSession = AcademicSession::create([
            'name' => '2027-2028',
            'start_date' => '2027-04-06',
            'end_date' => '2028-04-05',
            'status' => true,
        ]);

        $this->hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->school = Department::where('name', 'School')->firstOrFail();

        $this->nazra = $this->class($this->hifz, 'Nazra');
        $this->hifzClass = $this->class($this->hifz, 'Hifz');
        $this->gardan = $this->class($this->hifz, 'Gardan');
        $this->primary = $this->class($this->school, 'Primary Section');
        $this->middle = $this->class($this->school, 'Middle Section');

        $this->nazraA = Section::create([
            'name' => 'Nazra-A',
            'code' => 'NZRA',
            'academic_class_id' => $this->nazra->id,
            'status' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function class(Department $department, string $name): AcademicClass
    {
        return AcademicClass::where('department_id', $department->id)
            ->where('name', $name)
            ->firstOrFail();
    }

    /**
     * A student in Nazra since May 2026, part way through the session.
     */
    private function nazraStudent(): Student
    {
        $student = Student::create([
            'registration_number' => 'STD-2026-'.str_pad((string) (Student::count() + 1), 4, '0', STR_PAD_LEFT),
            'full_name' => 'Ahmed Ali',
            'father_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03007654321',
            'admission_date' => '2026-05-01',
            'academic_session_id' => $this->session->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->nazraA->id,
            'student_status' => 'Active',
            'student_type' => 'Hifz',
            'resident_type' => 'Local Resident',
        ]);

        $student->academicEnrollments()->create([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->nazraA->id,
            'start_date' => '2026-05-01',
            'status' => 'Active',
        ]);

        return $student;
    }

    /**
     * A school student holding one enrollment for the year.
     */
    private function schoolStudent(): Student
    {
        $student = Student::create([
            'registration_number' => 'STD-2026-'.str_pad((string) (Student::count() + 1), 4, '0', STR_PAD_LEFT),
            'full_name' => 'Bilal Ahmed',
            'father_name' => 'Ahmed Raza',
            'gender' => 'Male',
            'father_mobile' => '03004445555',
            'emergency_contact' => '03007654321',
            'admission_date' => '2026-04-05',
            'academic_session_id' => $this->session->id,
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'student_status' => 'Active',
            'student_type' => 'School',
            'resident_type' => 'Local Resident',
        ]);

        $student->academicEnrollments()->create([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'start_date' => '2026-04-05',
            'status' => 'Active',
        ]);

        return $student;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'academic_track' => 'Madrassa',
            // The session the institution is running. The whole point: the
            // promotion files into the session it happens in, not the next.
            'academic_session_id' => $this->session->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => null,
            'promotion_date' => '2026-07-15',
            'notes' => 'Nazra completed.',
        ], $overrides);
    }

    private function promote(Student $student, array $overrides = [])
    {
        return $this->post(route('students.promote.store', $student->id), $this->payload($overrides));
    }

    /* ---------------------------------------------------------------- */
    /* Scenario 1 and 2: the stage finishes before the session does */
    /* ---------------------------------------------------------------- */

    public function test_a_madrassa_student_can_be_promoted_before_the_session_ends(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();

        $this->promote($student)
            ->assertRedirect(route('students.show', $student->id))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $student->refresh();

        // The student is in the next stage.
        $this->assertSame($this->hifzClass->id, $student->academic_class_id);
        $this->assertNull($student->section_id);

        $active = $student->activeEnrollmentForTrack('Madrassa');
        $this->assertSame($this->hifzClass->id, $active->academic_class_id);
        $this->assertSame($this->hifz->id, $active->department_id);

        // The promotion date is the day the Imam performed it, not the
        // session's end date.
        $this->assertSame('2026-07-15', $active->start_date->format('Y-m-d'));
        $this->assertNull($active->end_date);
    }

    public function test_the_session_is_still_running_when_the_promotion_happens(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();

        $this->promote($student)->assertSessionHasNoErrors();

        // Stated explicitly: the session had months left to run.
        $this->assertTrue($this->session->end_date->isAfter(Carbon::parse('2026-07-15')));
        $this->assertSame('2027-04-05', $this->session->end_date->format('Y-m-d'));
        $this->assertTrue($this->session->fresh()->is_current);
    }

    public function test_the_history_records_the_session_and_the_completed_stage(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();

        $this->promote($student)->assertSessionHasNoErrors();

        $history = $student->academicEnrollments()->orderBy('id')->get();
        $this->assertCount(2, $history);

        // The stage the student came from, kept exactly as it was recorded
        // and closed on the day of the promotion.
        $previous = $history->first();
        $this->assertSame('Completed', $previous->status);
        $this->assertSame($this->nazra->id, $previous->academic_class_id);
        $this->assertSame($this->nazraA->id, $previous->section_id);
        $this->assertSame('2026-05-01', $previous->start_date->format('Y-m-d'));
        $this->assertSame('2026-07-15', $previous->end_date->format('Y-m-d'));
        $this->assertSame($this->session->id, $previous->academic_session_id);

        // The stage the student moved into, in the same running session.
        $current = $history->last();
        $this->assertSame('Active', $current->status);
        $this->assertSame($this->hifzClass->id, $current->academic_class_id);
        $this->assertSame($this->session->id, $current->academic_session_id);
        $this->assertSame('Madrassa', $current->academic_track);
        $this->assertSame('Nazra completed.', $current->notes);
    }

    public function test_the_profile_shows_both_stages_of_the_history(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();
        $this->promote($student)->assertSessionHasNoErrors();

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('Nazra')
            ->assertSee('Completed');
    }

    /* ---------------------------------------------------------------- */
    /* Scenario 2: more than one stage in one session */
    /* ---------------------------------------------------------------- */

    public function test_a_madrassa_student_can_be_promoted_twice_in_one_session(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();

        // July: Nazra completed.
        $this->promote($student)->assertSessionHasNoErrors();

        // October: the next stage completed too.
        Carbon::setTestNow('2026-10-20');

        $this->promote($student, [
            'academic_class_id' => $this->gardan->id,
            'promotion_date' => '2026-10-20',
            'notes' => 'Hifz stage completed.',
        ])->assertSessionHasNoErrors();

        $student->refresh();

        $this->assertSame($this->gardan->id, $student->academic_class_id);

        $history = $student->academicEnrollments()->orderBy('id')->get();
        $this->assertCount(3, $history);

        // Every row belongs to the one running session.
        $this->assertSame(
            [$this->session->id, $this->session->id, $this->session->id],
            $history->pluck('academic_session_id')->all()
        );

        $this->assertSame(['Completed', 'Completed', 'Active'], $history->pluck('status')->all());
        $this->assertSame(
            [$this->nazra->id, $this->hifzClass->id, $this->gardan->id],
            $history->pluck('academic_class_id')->all()
        );

        // Exactly one active enrollment on the track throughout.
        $this->assertCount(1, $student->activeAcademicEnrollments);
    }

    /* ---------------------------------------------------------------- */
    /* Scenario 4: a stage finished just before the session ends */
    /* ---------------------------------------------------------------- */

    public function test_a_promotion_days_before_the_session_ends_is_allowed(): void
    {
        Carbon::setTestNow('2027-04-01');

        $student = $this->nazraStudent();

        $this->promote($student, ['promotion_date' => '2027-04-01'])
            ->assertSessionHasNoErrors();

        $active = $student->activeEnrollmentForTrack('Madrassa');
        $this->assertSame($this->hifzClass->id, $active->academic_class_id);
        $this->assertSame('2027-04-01', $active->start_date->format('Y-m-d'));
        $this->assertSame($this->session->id, $active->academic_session_id);
    }

    /* ---------------------------------------------------------------- */
    /* Scenario 5: promoting into the following session still works */
    /* ---------------------------------------------------------------- */

    public function test_a_madrassa_student_can_still_be_promoted_into_the_next_session(): void
    {
        Carbon::setTestNow('2027-04-10');

        $student = $this->nazraStudent();

        $this->promote($student, [
            'academic_session_id' => $this->nextSession->id,
            'promotion_date' => '2027-04-10',
        ])->assertSessionHasNoErrors();

        $active = $student->activeEnrollmentForTrack('Madrassa');
        $this->assertSame($this->nextSession->id, $active->academic_session_id);
        $this->assertSame($this->hifzClass->id, $active->academic_class_id);
    }

    /* ---------------------------------------------------------------- */
    /* Scenario 3: the school is unchanged */
    /* ---------------------------------------------------------------- */

    public function test_a_school_student_still_gets_one_enrollment_per_session(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->schoolStudent();

        $this->post(route('students.promote.store', $student->id), [
            'academic_track' => 'School',
            'academic_session_id' => $this->session->id,
            'department_id' => $this->school->id,
            'academic_class_id' => $this->middle->id,
            'promotion_date' => '2026-07-15',
        ])->assertSessionHasErrors('academic_session_id');

        // Nothing moved.
        $this->assertSame(1, $student->academicEnrollments()->count());
        $this->assertSame($this->primary->id, $student->fresh()->academic_class_id);
        $this->assertSame($this->primary->id, $student->activeEnrollmentForTrack('School')->academic_class_id);
    }

    public function test_a_school_student_is_promoted_into_the_next_session_as_before(): void
    {
        Carbon::setTestNow('2027-04-10');

        $student = $this->schoolStudent();

        $this->post(route('students.promote.store', $student->id), [
            'academic_track' => 'School',
            'academic_session_id' => $this->nextSession->id,
            'department_id' => $this->school->id,
            'academic_class_id' => $this->middle->id,
            'promotion_date' => '2027-04-10',
        ])->assertSessionHasNoErrors();

        $active = $student->activeEnrollmentForTrack('School');
        $this->assertSame($this->nextSession->id, $active->academic_session_id);
        $this->assertSame($this->middle->id, $active->academic_class_id);
    }

    public function test_the_school_rule_is_enforced_below_the_form_as_well(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->schoolStudent();

        // Straight to the model, past the form request.
        $this->expectException(ValidationException::class);

        try {
            $student->promote([
                'academic_track' => 'School',
                'academic_session_id' => $this->session->id,
                'department_id' => $this->school->id,
                'academic_class_id' => $this->middle->id,
                'promotion_date' => '2026-07-15',
            ]);
        } finally {
            $this->assertSame(1, $student->academicEnrollments()->count());
            $this->assertSame('Active', $student->activeEnrollmentForTrack('School')->status);
        }
    }

    public function test_the_madrassa_track_of_a_dual_student_moves_without_the_school_track(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();
        $student->update(['student_type' => 'Hifz + School']);

        $student->academicEnrollments()->create([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'start_date' => '2026-05-01',
            'status' => 'Active',
        ]);

        $this->promote($student)->assertSessionHasNoErrors();

        // The madrassa side moved within the session.
        $this->assertSame($this->hifzClass->id, $student->activeEnrollmentForTrack('Madrassa')->academic_class_id);

        // The school side is exactly where it was.
        $school = $student->activeEnrollmentForTrack('School');
        $this->assertSame($this->primary->id, $school->academic_class_id);
        $this->assertSame($this->session->id, $school->academic_session_id);
        $this->assertSame('2026-05-01', $school->start_date->format('Y-m-d'));
    }

    /* ---------------------------------------------------------------- */
    /* Scenario 5: invalid promotions are still refused */
    /* ---------------------------------------------------------------- */

    public function test_promoting_into_the_same_stage_is_still_rejected(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();

        $this->promote($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->nazraA->id,
        ])->assertSessionHasErrors('academic_class_id');

        $this->assertSame(1, $student->academicEnrollments()->count());
    }

    public function test_a_class_from_another_department_is_still_rejected(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();

        $this->promote($student, ['academic_class_id' => $this->primary->id])
            ->assertSessionHasErrors('academic_class_id');

        $this->assertSame(1, $student->academicEnrollments()->count());
    }

    public function test_an_inactive_class_is_still_rejected(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();
        $this->hifzClass->update(['status' => false]);

        $this->promote($student)->assertSessionHasErrors('academic_class_id');

        $this->assertSame(1, $student->academicEnrollments()->count());
    }

    public function test_a_track_with_no_active_enrollment_is_still_rejected(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();

        $this->promote($student, [
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->middle->id,
        ])->assertSessionHasErrors('academic_track');

        $this->assertSame(1, $student->academicEnrollments()->count());
    }

    public function test_a_promotion_date_before_the_current_stage_started_is_still_rejected(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();

        // The Nazra enrollment started on 1 May 2026.
        $this->promote($student, ['promotion_date' => '2026-04-20'])
            ->assertSessionHasErrors('promotion_date');

        $this->assertSame(1, $student->academicEnrollments()->count());
    }

    public function test_an_inactive_session_is_still_rejected(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();
        $this->nextSession->update(['status' => false]);

        $this->promote($student, ['academic_session_id' => $this->nextSession->id])
            ->assertSessionHasErrors('academic_session_id');

        $this->assertSame(1, $student->academicEnrollments()->count());
    }

    public function test_a_student_who_has_left_still_cannot_be_promoted(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();
        $student->update(['student_status' => 'Left', 'leaving_reason' => 'Moved away.']);

        $this->promote($student)
            ->assertRedirect(route('students.show', $student->id))
            ->assertSessionHas('error');

        $this->assertSame(1, $student->academicEnrollments()->count());
    }

    public function test_a_double_submitted_promotion_does_not_promote_twice(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();

        $this->promote($student)->assertSessionHasNoErrors();

        // The same form posted again. The student is now in the stage it
        // asks for, so it records no progression and is refused - the guard
        // that used to be a side effect of the session rule.
        $this->promote($student)->assertSessionHasErrors('academic_class_id');

        $this->assertSame(2, $student->academicEnrollments()->count());
    }

    public function test_only_one_active_madrassa_enrollment_is_ever_held(): void
    {
        Carbon::setTestNow('2026-07-15');

        $student = $this->nazraStudent();
        $this->promote($student)->assertSessionHasNoErrors();

        $active = StudentAcademicEnrollment::where('student_id', $student->id)
            ->where('academic_track', 'Madrassa')
            ->where('status', 'Active')
            ->count();

        $this->assertSame(1, $active);
    }
}
