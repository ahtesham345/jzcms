<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentPromotionTest extends TestCase
{
    use RefreshDatabase;

    private AcademicSession $session2026;

    private AcademicSession $session2027;

    private Department $hifz;

    private Department $school;

    private AcademicClass $nazra;

    private AcademicClass $hifzClass;

    private AcademicClass $fifth;

    private AcademicClass $sixth;

    private Section $nazraA;

    private Section $hifzB;

    private Section $fifthA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->seed(\Database\Seeders\AdmissionDepartmentClassSeeder::class);

        $this->session2026 = AcademicSession::create([
            'name' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'status' => true,
        ]);
        $this->session2027 = AcademicSession::create([
            'name' => '2027-2028', 'start_date' => '2027-04-01', 'end_date' => '2028-03-31',
            'status' => true,
        ]);

        $this->hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->school = Department::where('name', 'School')->firstOrFail();

        $this->nazra = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Nazra')->firstOrFail();
        $this->hifzClass = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Hifz')->firstOrFail();
        $this->fifth = AcademicClass::where('department_id', $this->school->id)->where('name', 'Primary Section')->firstOrFail();
        $this->sixth = AcademicClass::where('department_id', $this->school->id)->where('name', 'Middle Section')->firstOrFail();

        $this->nazraA = $this->section('Nazra-A', $this->nazra);
        $this->hifzB = $this->section('Hifz-B', $this->hifzClass);
        $this->fifthA = $this->section('Primary-A', $this->fifth);
    }

    private function section(string $name, AcademicClass $class): Section
    {
        return Section::create([
            'name' => $name,
            'code' => strtoupper(substr($name, 0, 6)),
            'academic_class_id' => $class->id,
            'status' => true,
        ]);
    }

    private function student(array $overrides = []): Student
    {
        static $sequence = 0;
        $sequence++;

        return Student::create(array_merge([
            'registration_number' => 'STD-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'full_name' => 'Ahmed Ali',
            'father_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03007654321',
            'admission_date' => '2026-04-01',
            'academic_session_id' => $this->session2026->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->nazraA->id,
            'student_status' => 'Active',
            'student_type' => 'Hifz',
            'resident_type' => 'Local Resident',
        ], $overrides));
    }

    private function enroll(Student $student, array $overrides = []): StudentAcademicEnrollment
    {
        return $student->academicEnrollments()->create(array_merge([
            'academic_session_id' => $this->session2026->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->nazraA->id,
            'start_date' => '2026-04-01',
            'status' => 'Active',
        ], $overrides));
    }

    /**
     * A single-track madrassa student with one active enrollment.
     */
    private function enrolledStudent(array $studentOverrides = []): Student
    {
        $student = $this->student($studentOverrides);
        $this->enroll($student);

        return $student;
    }

    /**
     * A Hifz + School student holding one active enrollment per track.
     *
     * The student row mirrors the madrassa side, as the admission approval
     * flow records it.
     */
    private function dualTrackStudent(): Student
    {
        $student = $this->student([
            'student_type' => 'Hifz + School',
            'full_name' => 'Dual Track Student',
        ]);

        $this->enroll($student, ['academic_track' => 'Madrassa']);
        $this->enroll($student, [
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->fifth->id,
            'section_id' => $this->fifthA->id,
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
            'academic_session_id' => $this->session2027->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'promotion_date' => '2027-04-01',
            'notes' => 'Promoted to Hifz.',
        ], $overrides);
    }

    private function promote(Student $student, array $overrides = [])
    {
        return $this->post(route('students.promote.store', $student->id), $this->payload($overrides));
    }

    /* ---------------------------------------------------------------- */
    /* A successful promotion                                           */
    /* ---------------------------------------------------------------- */

    public function test_a_student_can_be_promoted(): void
    {
        $student = $this->enrolledStudent();
        $original = $student->academicEnrollments()->sole();

        $this->promote($student)
            ->assertRedirect(route('students.show', $student->id))
            ->assertSessionHas('success');

        $this->assertSame(2, $student->academicEnrollments()->count());

        // The old enrollment is completed as at the promotion date, and
        // nothing else about it moved.
        $original->refresh();
        $this->assertSame('Completed', $original->status);
        $this->assertSame('2027-04-01', $original->end_date->format('Y-m-d'));
        $this->assertSame($this->session2026->id, $original->academic_session_id);
        $this->assertSame($this->nazra->id, $original->academic_class_id);
        $this->assertSame($this->nazraA->id, $original->section_id);
        $this->assertSame('2026-04-01', $original->start_date->format('Y-m-d'));

        // The new enrollment carries every target field.
        $promoted = $student->activeEnrollmentForTrack('Madrassa');
        $this->assertNotNull($promoted);
        $this->assertSame('Active', $promoted->status);
        $this->assertSame($this->session2027->id, $promoted->academic_session_id);
        $this->assertSame($this->hifz->id, $promoted->department_id);
        $this->assertSame($this->hifzClass->id, $promoted->academic_class_id);
        $this->assertSame($this->hifzB->id, $promoted->section_id);
        $this->assertSame('Madrassa', $promoted->academic_track);
        $this->assertSame('2027-04-01', $promoted->start_date->format('Y-m-d'));
        $this->assertNull($promoted->end_date);
        $this->assertSame('Promoted to Hifz.', $promoted->notes);
    }

    public function test_the_student_current_placement_is_updated(): void
    {
        $student = $this->enrolledStudent();

        $this->promote($student)->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertSame($this->session2027->id, $student->academic_session_id);
        $this->assertSame($this->hifz->id, $student->department_id);
        $this->assertSame($this->hifzClass->id, $student->academic_class_id);
        $this->assertSame($this->hifzB->id, $student->section_id);
    }

    public function test_the_target_section_may_be_null(): void
    {
        $student = $this->enrolledStudent();
        // Gardan has no sections at all.
        $gardan = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Gardan')->firstOrFail();

        $this->promote($student, [
            'academic_class_id' => $gardan->id,
            'section_id' => null,
        ])->assertSessionHasNoErrors();

        $promoted = $student->activeEnrollmentForTrack('Madrassa');
        $this->assertSame($gardan->id, $promoted->academic_class_id);
        $this->assertNull($promoted->section_id);
        $this->assertNull($student->fresh()->section_id);
    }

    public function test_the_old_history_is_preserved_across_several_promotions(): void
    {
        $student = $this->enrolledStudent();
        $session2028 = AcademicSession::create([
            'name' => '2028-2029', 'start_date' => '2028-04-01', 'end_date' => '2029-03-31', 'status' => true,
        ]);

        $this->promote($student)->assertSessionHasNoErrors();
        $this->promote($student, [
            'academic_session_id' => $session2028->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->nazraA->id,
            'promotion_date' => '2028-04-01',
        ])->assertSessionHasNoErrors();

        $history = $student->academicEnrollments()->orderBy('id')->get();
        $this->assertCount(3, $history);

        // Two completed rows and exactly one active.
        $this->assertSame(['Completed', 'Completed', 'Active'], $history->pluck('status')->all());
        $this->assertSame(
            [$this->session2026->id, $this->session2027->id, $session2028->id],
            $history->pluck('academic_session_id')->all()
        );
        $this->assertSame('2027-04-01', $history[0]->end_date->format('Y-m-d'));
        $this->assertSame('2028-04-01', $history[1]->end_date->format('Y-m-d'));
        $this->assertCount(1, $student->fresh()->activeAcademicEnrollments);
    }

    public function test_the_history_table_shows_the_progression(): void
    {
        $student = $this->enrolledStudent();
        $this->promote($student)->assertSessionHasNoErrors();

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('Academic History', false)
            ->assertSee('2026-2027', false)
            ->assertSee('2027-2028', false)
            ->assertSee('Nazra', false)
            ->assertSee('Completed', false)
            ->assertSee('Active', false)
            ->assertSee('01 Apr, 2027', false)
            ->assertSee('Promoted to Hifz.', false);
    }

    /* ---------------------------------------------------------------- */
    /* Hifz + School: each track promoted on its own                    */
    /* ---------------------------------------------------------------- */

    public function test_promoting_the_madrassa_track_leaves_the_school_track_untouched(): void
    {
        $student = $this->dualTrackStudent();
        $schoolBefore = $student->activeEnrollmentForTrack('School')->toArray();

        $this->promote($student, ['academic_track' => 'Madrassa'])->assertSessionHasNoErrors();

        // Madrassa progressed.
        $madrassa = $student->activeEnrollmentForTrack('Madrassa');
        $this->assertSame($this->session2027->id, $madrassa->academic_session_id);
        $this->assertSame($this->hifzClass->id, $madrassa->academic_class_id);

        // The completed madrassa row is in the history.
        $this->assertSame(1, $student->academicEnrollments()
            ->where('academic_track', 'Madrassa')->where('status', 'Completed')->count());

        // School is byte-for-byte what it was.
        $schoolAfter = $student->activeEnrollmentForTrack('School')->toArray();
        $this->assertSame($schoolBefore, $schoolAfter);
        $this->assertSame(1, $student->academicEnrollments()->where('academic_track', 'School')->count());

        // Both tracks are still active.
        $this->assertCount(2, $student->fresh()->activeAcademicEnrollments);
    }

    public function test_promoting_the_school_track_leaves_the_madrassa_track_untouched(): void
    {
        $student = $this->dualTrackStudent();
        $madrassaBefore = $student->activeEnrollmentForTrack('Madrassa')->toArray();

        $this->promote($student, [
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->sixth->id,
            'section_id' => null,
        ])->assertSessionHasNoErrors();

        $schoolNow = $student->activeEnrollmentForTrack('School');
        $this->assertSame($this->sixth->id, $schoolNow->academic_class_id);
        $this->assertSame($this->session2027->id, $schoolNow->academic_session_id);

        // Madrassa is exactly as it was.
        $this->assertSame($madrassaBefore, $student->activeEnrollmentForTrack('Madrassa')->toArray());
        $this->assertSame(1, $student->academicEnrollments()->where('academic_track', 'Madrassa')->count());

        $this->assertCount(2, $student->fresh()->activeAcademicEnrollments);
    }

    public function test_a_school_promotion_does_not_move_a_dual_track_students_placement(): void
    {
        // The student row mirrors the madrassa side, so promoting School
        // must not overwrite it with school values.
        $student = $this->dualTrackStudent();

        $this->promote($student, [
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->sixth->id,
            'section_id' => null,
        ])->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertSame($this->hifz->id, $student->department_id);
        $this->assertSame($this->nazra->id, $student->academic_class_id);
        $this->assertSame($this->nazraA->id, $student->section_id);
        $this->assertSame($this->session2026->id, $student->academic_session_id);
    }

    public function test_a_madrassa_promotion_moves_a_dual_track_students_placement(): void
    {
        $student = $this->dualTrackStudent();

        $this->promote($student, ['academic_track' => 'Madrassa'])->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertSame($this->hifzClass->id, $student->academic_class_id);
        $this->assertSame($this->hifzB->id, $student->section_id);
        $this->assertSame($this->session2027->id, $student->academic_session_id);
    }

    public function test_both_tracks_can_be_promoted_in_turn(): void
    {
        $student = $this->dualTrackStudent();

        $this->promote($student, ['academic_track' => 'Madrassa'])->assertSessionHasNoErrors();
        $this->promote($student, [
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->sixth->id,
            'section_id' => null,
        ])->assertSessionHasNoErrors();

        $this->assertSame(4, $student->academicEnrollments()->count());
        $this->assertCount(2, $student->fresh()->activeAcademicEnrollments);
        $this->assertSame(2, $student->academicEnrollments()->where('status', 'Completed')->count());
    }

    /* ---------------------------------------------------------------- */
    /* Rejected promotions                                              */
    /* ---------------------------------------------------------------- */

    public function test_a_track_without_an_active_enrollment_is_rejected(): void
    {
        $student = $this->enrolledStudent();

        $this->promote($student, [
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->sixth->id,
            'section_id' => null,
        ])->assertSessionHasErrors('academic_track');

        $this->assertSame(1, $student->academicEnrollments()->count());
        $this->assertSame('Active', $student->academicEnrollments()->sole()->status);
    }

    public function test_a_class_from_another_department_is_rejected(): void
    {
        $student = $this->enrolledStudent();

        $this->promote($student, [
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->sixth->id,
            'section_id' => null,
        ])->assertSessionHasErrors('academic_class_id');

        $this->assertNothingChanged($student);
    }

    public function test_a_section_from_another_class_is_rejected(): void
    {
        $student = $this->enrolledStudent();

        $this->promote($student, ['section_id' => $this->fifthA->id])
            ->assertSessionHasErrors('section_id');

        $this->assertNothingChanged($student);
    }

    public function test_an_inactive_department_is_rejected(): void
    {
        $student = $this->enrolledStudent();
        $this->hifz->update(['status' => false]);

        $this->promote($student)->assertSessionHasErrors('department_id');

        $this->assertNothingChanged($student);
    }

    public function test_an_inactive_class_is_rejected(): void
    {
        $student = $this->enrolledStudent();
        $this->hifzClass->update(['status' => false]);

        $this->promote($student)->assertSessionHasErrors('academic_class_id');

        $this->assertNothingChanged($student);
    }

    public function test_an_inactive_section_is_rejected(): void
    {
        $student = $this->enrolledStudent();
        $this->hifzB->update(['status' => false]);

        $this->promote($student)->assertSessionHasErrors('section_id');

        $this->assertNothingChanged($student);
    }

    public function test_an_invalid_or_inactive_session_is_rejected(): void
    {
        $student = $this->enrolledStudent();

        $this->promote($student, ['academic_session_id' => 999999])
            ->assertSessionHasErrors('academic_session_id');

        $this->session2027->update(['status' => false]);
        $this->promote($student)->assertSessionHasErrors('academic_session_id');

        $this->assertNothingChanged($student);
    }

    public function test_an_identical_target_placement_is_rejected(): void
    {
        $student = $this->enrolledStudent();

        $this->promote($student, [
            'academic_session_id' => $this->session2026->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->nazraA->id,
        ])->assertSessionHasErrors('academic_class_id');

        $this->assertNothingChanged($student);
    }

    public function test_a_duplicate_session_and_track_is_rejected(): void
    {
        $student = $this->enrolledStudent();

        // A completed enrollment already occupies 2027 on this track.
        $this->enroll($student, [
            'academic_session_id' => $this->session2027->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => null,
            'status' => 'Completed',
            'start_date' => '2027-04-01',
            'end_date' => '2027-06-01',
        ]);

        $this->promote($student)->assertSessionHasErrors('academic_session_id');

        $this->assertSame(2, $student->academicEnrollments()->count());
        $this->assertSame('Active', $student->activeEnrollmentForTrack('Madrassa')->status);
    }

    public function test_the_unique_constraint_still_guards_the_table(): void
    {
        $student = $this->enrolledStudent();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        // Straight past validation and the model.
        $this->enroll($student, ['status' => 'Completed']);
    }

    public function test_a_promotion_date_before_the_current_enrollment_is_rejected(): void
    {
        $student = $this->enrolledStudent();

        $this->promote($student, ['promotion_date' => '2026-01-01'])
            ->assertSessionHasErrors('promotion_date');

        $this->assertNothingChanged($student);
    }

    public function test_required_fields_are_enforced(): void
    {
        $student = $this->enrolledStudent();

        $this->post(route('students.promote.store', $student->id), [])
            ->assertSessionHasErrors(['academic_track', 'academic_session_id', 'department_id', 'academic_class_id', 'promotion_date']);

        $this->promote($student, ['academic_track' => 'College'])
            ->assertSessionHasErrors('academic_track');

        $this->assertNothingChanged($student);
    }

    public function test_a_student_that_is_not_active_cannot_be_promoted(): void
    {
        foreach (['Passed', 'Left'] as $status) {
            $student = $this->enrolledStudent(['student_status' => $status]);

            $this->get(route('students.promote', $student->id))
                ->assertRedirect(route('students.show', $student->id))
                ->assertSessionHas('error');

            $this->promote($student)
                ->assertRedirect(route('students.show', $student->id))
                ->assertSessionHas('error');

            $this->assertSame(1, $student->academicEnrollments()->count());
            $this->assertSame('Active', $student->academicEnrollments()->sole()->status);
        }
    }

    /* ---------------------------------------------------------------- */
    /* Transaction                                                      */
    /* ---------------------------------------------------------------- */

    public function test_a_failure_creating_the_new_enrollment_rolls_everything_back(): void
    {
        $student = $this->enrolledStudent();
        $before = $student->academicEnrollments()->sole()->toArray();

        // Fails after the old enrollment has been completed.
        StudentAcademicEnrollment::creating(function () {
            throw new \RuntimeException('Enrollment creation failed');
        });

        try {
            $this->withoutExceptionHandling()->promote($student);
            $this->fail('The promotion should have failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('Enrollment creation failed', $e->getMessage());
        } finally {
            StudentAcademicEnrollment::flushEventListeners();
        }

        // The old enrollment is still Active: its completion rolled back.
        $this->assertSame(1, $student->academicEnrollments()->count());
        $this->assertSame($before, $student->academicEnrollments()->sole()->toArray());

        // And the student placement never moved.
        $student->refresh();
        $this->assertSame($this->session2026->id, $student->academic_session_id);
        $this->assertSame($this->nazra->id, $student->academic_class_id);
        $this->assertSame($this->nazraA->id, $student->section_id);
    }

    public function test_a_failure_updating_the_placement_rolls_the_enrollments_back(): void
    {
        $student = $this->enrolledStudent();

        Student::updating(function () {
            throw new \RuntimeException('Placement update failed');
        });

        try {
            $this->withoutExceptionHandling()->promote($student);
            $this->fail('The promotion should have failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('Placement update failed', $e->getMessage());
        } finally {
            Student::flushEventListeners();
        }

        // Neither the completion nor the new enrollment survived.
        $this->assertSame(1, $student->academicEnrollments()->count());
        $this->assertSame('Active', $student->academicEnrollments()->sole()->status);
        $this->assertNull($student->academicEnrollments()->sole()->end_date);
        $this->assertSame($this->nazra->id, $student->fresh()->academic_class_id);
    }

    public function test_the_promotion_runs_inside_a_transaction(): void
    {
        $student = $this->enrolledStudent();

        $level = null;
        StudentAcademicEnrollment::creating(function () use (&$level) {
            $level = DB::transactionLevel();
        });

        $student->promote($this->payload());

        $this->assertNotNull($level);
        $this->assertGreaterThan(0, $level, 'The new enrollment must be written inside a transaction');

        StudentAcademicEnrollment::flushEventListeners();
    }

    /* ---------------------------------------------------------------- */
    /* UI and security                                                  */
    /* ---------------------------------------------------------------- */

    public function test_the_promotion_form_renders(): void
    {
        $student = $this->enrolledStudent();

        $this->get(route('students.promote', $student->id))
            ->assertOk()
            ->assertSee('Promote Student', false)
            ->assertSee('Confirm the promotion', false)
            ->assertSee('name="academic_track"', false)
            ->assertSee('name="academic_session_id"', false)
            ->assertSee('name="department_id"', false)
            ->assertSee('name="academic_class_id"', false)
            ->assertSee('name="section_id"', false)
            ->assertSee('name="promotion_date"', false)
            ->assertSee('name="notes"', false)
            // The section is optional, so it carries no required marker.
            ->assertSee('Target Section</label>', false);
    }

    public function test_the_profile_offers_the_promotion_action(): void
    {
        $student = $this->enrolledStudent();

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee(route('students.promote', $student->id), false)
            ->assertSee('Promote Student', false);
    }

    public function test_the_form_reports_a_student_with_nothing_to_promote(): void
    {
        $student = $this->student();

        $this->get(route('students.promote', $student->id))
            ->assertOk()
            ->assertSee('No active enrollment', false)
            ->assertDontSee('name="academic_session_id"', false);
    }

    public function test_promotion_requires_authentication(): void
    {
        $student = $this->enrolledStudent();
        auth()->logout();

        $this->get(route('students.promote', $student->id))->assertRedirect(route('login'));
        $this->post(route('students.promote.store', $student->id), $this->payload())
            ->assertRedirect(route('login'));

        $this->assertSame(1, $student->academicEnrollments()->count());
        $this->assertSame('Active', $student->academicEnrollments()->sole()->status);
    }

    public function test_an_unknown_student_cannot_be_promoted(): void
    {
        $this->get(route('students.promote', 999999))->assertNotFound();
        $this->post(route('students.promote.store', 999999), $this->payload())->assertNotFound();
    }

    public function test_one_students_promotion_does_not_touch_another(): void
    {
        $promoted = $this->enrolledStudent(['full_name' => 'Promoted']);
        $bystander = $this->enrolledStudent(['full_name' => 'Bystander']);
        $before = $bystander->academicEnrollments()->sole()->toArray();

        $this->promote($promoted)->assertSessionHasNoErrors();

        $this->assertSame($before, $bystander->academicEnrollments()->sole()->toArray());
        $this->assertSame($this->nazra->id, $bystander->fresh()->academic_class_id);
        $this->assertSame(1, $bystander->academicEnrollments()->count());
    }

    /**
     * Assert a rejected promotion left the student exactly as it was.
     */
    private function assertNothingChanged(Student $student): void
    {
        $this->assertSame(1, $student->academicEnrollments()->count(), 'No enrollment may be created');

        $enrollment = $student->academicEnrollments()->sole();
        $this->assertSame('Active', $enrollment->status, 'The current enrollment must stay active');
        $this->assertNull($enrollment->end_date, 'The current enrollment must not be completed');

        $student->refresh();
        $this->assertSame($this->session2026->id, $student->academic_session_id);
        $this->assertSame($this->nazra->id, $student->academic_class_id);
    }
}
