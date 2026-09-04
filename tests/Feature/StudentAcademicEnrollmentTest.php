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

class StudentAcademicEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private AcademicSession $session;

    private AcademicSession $nextSession;

    private Department $hifz;

    private Department $school;

    private AcademicClass $nazra;

    private AcademicClass $hifzClass;

    private AcademicClass $ninth;

    private Section $nazraA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->seed(\Database\Seeders\AdmissionDepartmentClassSeeder::class);

        $this->session = AcademicSession::create([
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
            'status' => true,
        ]);

        $this->nextSession = AcademicSession::create([
            'name' => '2027-2028',
            'start_date' => '2027-04-01',
            'end_date' => '2028-03-31',
            'status' => true,
        ]);

        $this->hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->school = Department::where('name', 'School')->firstOrFail();

        $this->nazra = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Nazra')->firstOrFail();
        $this->hifzClass = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Hifz')->firstOrFail();
        $this->ninth = AcademicClass::where('department_id', $this->school->id)->where('name', '9th')->firstOrFail();

        $this->nazraA = Section::create([
            'name' => 'Nazra-A',
            'code' => 'NZR-A',
            'academic_class_id' => $this->nazra->id,
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
            'academic_session_id' => $this->session->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'student_status' => 'Active',
            'student_type' => 'Hifz',
            'resident_type' => 'Local Resident',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->nazraA->id,
            'start_date' => '2026-04-01',
            'status' => 'Active',
            'notes' => 'Started in Nazra.',
        ], $overrides);
    }

    private function enroll(Student $student, array $overrides = [])
    {
        return $this->post(route('students.enrollments.store', $student->id), $this->payload($overrides));
    }

    /* ---------------------------------------------------------------- */
    /* Basic enrollment                                                 */
    /* ---------------------------------------------------------------- */

    public function test_an_enrollment_can_be_created(): void
    {
        $student = $this->student();

        $this->enroll($student)
            ->assertRedirect(route('students.show', $student->id))
            ->assertSessionHas('success');

        $enrollment = StudentAcademicEnrollment::sole();
        $this->assertSame($student->id, $enrollment->student_id);
        $this->assertSame($this->session->id, $enrollment->academic_session_id);
        $this->assertSame('Madrassa', $enrollment->academic_track);
        $this->assertSame($this->hifz->id, $enrollment->department_id);
        $this->assertSame($this->nazra->id, $enrollment->academic_class_id);
        $this->assertSame($this->nazraA->id, $enrollment->section_id);
        $this->assertSame('2026-04-01', $enrollment->start_date->format('Y-m-d'));
        $this->assertNull($enrollment->end_date);
        $this->assertSame('Active', $enrollment->status);
        $this->assertSame('Started in Nazra.', $enrollment->notes);
    }

    public function test_the_enrollment_belongs_to_the_correct_student(): void
    {
        $mine = $this->student(['full_name' => 'Mine']);
        $other = $this->student(['full_name' => 'Other']);

        $this->enroll($mine)->assertSessionHasNoErrors();

        $this->assertCount(1, $mine->fresh()->academicEnrollments);
        $this->assertCount(0, $other->fresh()->academicEnrollments);
        $this->assertSame('Mine', StudentAcademicEnrollment::sole()->student->full_name);
    }

    public function test_every_relationship_resolves(): void
    {
        $student = $this->student();
        $this->enroll($student)->assertSessionHasNoErrors();

        $enrollment = StudentAcademicEnrollment::with([
            'student', 'academicSession', 'department', 'academicClass', 'section',
        ])->sole();

        $this->assertSame('Ahmed Ali', $enrollment->student->full_name);
        $this->assertSame('2026-2027', $enrollment->academicSession->name);
        $this->assertSame('Hifz', $enrollment->department->name);
        $this->assertSame('Nazra', $enrollment->academicClass->name);
        $this->assertSame('Nazra-A', $enrollment->section->name);
    }

    public function test_the_profile_displays_the_enrollment_details(): void
    {
        $student = $this->student();
        $this->enroll($student)->assertSessionHasNoErrors();

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('Current Academic Enrollment', false)
            ->assertSee('Academic History', false)
            ->assertSee('2026-2027', false)
            ->assertSee('Hifz', false)
            ->assertSee('Nazra', false)
            ->assertSee('Nazra-A', false)
            ->assertSee('01 Apr, 2026', false)
            ->assertSee('Started in Nazra.', false)
            ->assertDontSee('No active enrollment.', false);
    }

    public function test_the_active_enrollment_relationship_works(): void
    {
        $student = $this->student();
        $this->enroll($student, ['status' => 'Completed', 'end_date' => '2027-03-31'])->assertSessionHasNoErrors();
        $this->enroll($student, [
            'academic_session_id' => $this->nextSession->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => null,
            'start_date' => '2027-04-01',
            'status' => 'Active',
        ])->assertSessionHasNoErrors();

        $active = $student->fresh()->activeAcademicEnrollment;
        $this->assertNotNull($active);
        $this->assertSame($this->nextSession->id, $active->academic_session_id);
        $this->assertSame($this->hifzClass->id, $active->academic_class_id);
        $this->assertCount(1, $student->fresh()->activeAcademicEnrollments);
    }

    /* ---------------------------------------------------------------- */
    /* Validation                                                       */
    /* ---------------------------------------------------------------- */

    public function test_an_unknown_student_is_rejected(): void
    {
        $this->post(route('students.enrollments.store', 999999), $this->payload())
            ->assertNotFound();

        $this->assertSame(0, StudentAcademicEnrollment::count());
    }

    public function test_an_invalid_academic_session_is_rejected(): void
    {
        $student = $this->student();

        $this->enroll($student, ['academic_session_id' => 999999])
            ->assertSessionHasErrors('academic_session_id');

        $this->enroll($student, ['academic_session_id' => null])
            ->assertSessionHasErrors('academic_session_id');

        $this->assertSame(0, StudentAcademicEnrollment::count());
    }

    public function test_a_class_from_another_department_is_rejected(): void
    {
        $student = $this->student();

        // 9th belongs to School, not Hifz.
        $this->enroll($student, [
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->ninth->id,
            'section_id' => null,
        ])->assertSessionHasErrors('academic_class_id');

        $this->assertSame(0, StudentAcademicEnrollment::count());
    }

    public function test_a_section_from_another_class_is_rejected(): void
    {
        $student = $this->student();

        // Nazra-A belongs to Nazra, not to the Hifz class.
        $this->enroll($student, [
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->nazraA->id,
        ])->assertSessionHasErrors('section_id');

        $this->assertSame(0, StudentAcademicEnrollment::count());
    }

    public function test_an_inactive_department_is_rejected(): void
    {
        $student = $this->student();
        $this->hifz->update(['status' => false]);

        $this->enroll($student)->assertSessionHasErrors('department_id');

        $this->assertSame(0, StudentAcademicEnrollment::count());
    }

    public function test_an_inactive_class_is_rejected(): void
    {
        $student = $this->student();
        $this->nazra->update(['status' => false]);

        $this->enroll($student)->assertSessionHasErrors('academic_class_id');

        $this->assertSame(0, StudentAcademicEnrollment::count());
    }

    public function test_an_inactive_section_is_rejected(): void
    {
        $student = $this->student();
        $this->nazraA->update(['status' => false]);

        $this->enroll($student)->assertSessionHasErrors('section_id');

        $this->assertSame(0, StudentAcademicEnrollment::count());
    }

    public function test_the_section_may_be_null(): void
    {
        $student = $this->student();

        $this->enroll($student, ['section_id' => null])->assertSessionHasNoErrors();

        $this->assertNull(StudentAcademicEnrollment::sole()->section_id);
    }

    public function test_a_class_without_sections_can_be_enrolled(): void
    {
        $student = $this->student();

        // The Hifz class has no sections at all.
        $this->assertSame(0, Section::where('academic_class_id', $this->hifzClass->id)->count());

        $this->enroll($student, [
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => null,
        ])->assertSessionHasNoErrors();

        $enrollment = StudentAcademicEnrollment::sole();
        $this->assertSame($this->hifzClass->id, $enrollment->academic_class_id);
        $this->assertNull($enrollment->section_id);

        $this->get(route('students.show', $student->id))->assertOk()->assertSee('No section', false);
    }

    public function test_an_invalid_status_or_track_is_rejected(): void
    {
        $student = $this->student();

        $this->enroll($student, ['status' => 'Graduated'])->assertSessionHasErrors('status');
        $this->enroll($student, ['academic_track' => 'College'])->assertSessionHasErrors('academic_track');
        $this->enroll($student, ['start_date' => null])->assertSessionHasErrors('start_date');
        $this->enroll($student, ['end_date' => '2025-01-01'])->assertSessionHasErrors('end_date');

        $this->assertSame(0, StudentAcademicEnrollment::count());
    }

    /* ---------------------------------------------------------------- */
    /* The active enrollment rule                                       */
    /* ---------------------------------------------------------------- */

    public function test_a_second_active_enrollment_on_the_same_track_is_rejected(): void
    {
        $student = $this->student();
        $this->enroll($student)->assertSessionHasNoErrors();

        $this->enroll($student, [
            'academic_session_id' => $this->nextSession->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => null,
            'start_date' => '2027-04-01',
        ])->assertSessionHasErrors('status');

        $this->assertSame(1, StudentAcademicEnrollment::count());

        // The first one is untouched: nothing was silently deactivated.
        $this->assertSame('Active', StudentAcademicEnrollment::sole()->status);
    }

    public function test_a_completed_enrollment_can_coexist_with_a_new_active_one(): void
    {
        $student = $this->student();

        $this->enroll($student, ['status' => 'Completed', 'end_date' => '2027-03-31'])
            ->assertSessionHasNoErrors();

        $this->enroll($student, [
            'academic_session_id' => $this->nextSession->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => null,
            'start_date' => '2027-04-01',
            'status' => 'Active',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, StudentAcademicEnrollment::count());
        $this->assertCount(1, $student->fresh()->activeAcademicEnrollments);
    }

    public function test_a_withdrawn_enrollment_can_coexist_with_a_new_active_one(): void
    {
        $student = $this->student();

        // "Left" is this project's withdrawn value.
        $this->enroll($student, ['status' => 'Left', 'end_date' => '2026-09-01'])
            ->assertSessionHasNoErrors();

        $this->enroll($student, [
            'academic_session_id' => $this->nextSession->id,
            'start_date' => '2027-04-01',
            'status' => 'Active',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, StudentAcademicEnrollment::count());
        $this->assertCount(1, $student->fresh()->activeAcademicEnrollments);
    }

    public function test_one_active_enrollment_per_track_is_allowed(): void
    {
        $student = $this->student(['student_type' => 'Hifz + School']);

        // Madrassa and School are separate tracks, so both may be active.
        $this->enroll($student, ['academic_track' => 'Madrassa'])->assertSessionHasNoErrors();
        $this->enroll($student, [
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->ninth->id,
            'section_id' => null,
        ])->assertSessionHasNoErrors();

        $this->assertCount(2, $student->fresh()->activeAcademicEnrollments);

        // But a third on a track that is already active is refused.
        $this->enroll($student, [
            'academic_track' => 'School',
            'academic_session_id' => $this->nextSession->id,
            'department_id' => $this->school->id,
            'academic_class_id' => $this->ninth->id,
            'section_id' => null,
        ])->assertSessionHasErrors('status');

        $this->assertSame(2, StudentAcademicEnrollment::count());
    }

    public function test_a_duplicate_session_and_track_is_rejected(): void
    {
        $student = $this->student();
        $this->enroll($student, ['status' => 'Completed', 'end_date' => '2027-01-01'])
            ->assertSessionHasNoErrors();

        // Same student, same session, same track.
        $this->enroll($student, [
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => null,
            'status' => 'Active',
        ])->assertSessionHasErrors('academic_session_id');

        $this->assertSame(1, StudentAcademicEnrollment::count());
    }

    public function test_the_unique_index_is_the_final_guard(): void
    {
        $student = $this->student();
        $this->enroll($student)->assertSessionHasNoErrors();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        StudentAcademicEnrollment::create($this->payload([
            'student_id' => $student->id,
            'status' => 'Completed',
        ]));
    }

    /* ---------------------------------------------------------------- */
    /* History                                                          */
    /* ---------------------------------------------------------------- */

    public function test_enrollments_across_sessions_are_all_preserved(): void
    {
        $student = $this->student();

        $this->enroll($student, ['status' => 'Completed', 'end_date' => '2027-03-31'])
            ->assertSessionHasNoErrors();
        $this->enroll($student, [
            'academic_session_id' => $this->nextSession->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => null,
            'start_date' => '2027-04-01',
            'status' => 'Active',
            'notes' => 'Moved up to Hifz.',
        ])->assertSessionHasNoErrors();

        $enrollments = $student->fresh()->academicEnrollments;
        $this->assertCount(2, $enrollments);

        // The earlier row keeps its own class, dates, status and notes.
        $first = $enrollments->firstWhere('academic_session_id', $this->session->id);
        $this->assertSame($this->nazra->id, $first->academic_class_id);
        $this->assertSame($this->nazraA->id, $first->section_id);
        $this->assertSame('Completed', $first->status);
        $this->assertSame('2027-03-31', $first->end_date->format('Y-m-d'));
        $this->assertSame('Started in Nazra.', $first->notes);
    }

    public function test_a_new_enrollment_does_not_overwrite_the_previous_one(): void
    {
        $student = $this->student();
        $this->enroll($student, ['status' => 'Completed', 'end_date' => '2027-03-31'])->assertSessionHasNoErrors();

        $original = StudentAcademicEnrollment::sole()->toArray();

        $this->enroll($student, [
            'academic_session_id' => $this->nextSession->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => null,
            'start_date' => '2027-04-01',
        ])->assertSessionHasNoErrors();

        $unchanged = StudentAcademicEnrollment::find($original['id'])->toArray();
        $this->assertSame($original, $unchanged);
    }

    public function test_the_history_table_lists_every_enrollment(): void
    {
        $student = $this->student();
        $this->enroll($student, ['status' => 'Completed', 'end_date' => '2027-03-31']);
        $this->enroll($student, [
            'academic_session_id' => $this->nextSession->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => null,
            'start_date' => '2027-04-01',
            'notes' => 'Moved up to Hifz.',
        ]);

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('Academic History', false)
            ->assertSee('2026-2027', false)
            ->assertSee('2027-2028', false)
            ->assertSee('Nazra', false)
            ->assertSee('Completed', false)
            ->assertSee('Started in Nazra.', false)
            ->assertSee('Moved up to Hifz.', false)
            ->assertSee('31 Mar, 2027', false);
    }

    public function test_the_empty_history_state_renders(): void
    {
        $student = $this->student();

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('No active enrollment.', false)
            ->assertSee('No academic enrollment records for this student.', false)
            ->assertSee('Add Enrollment', false);
    }

    /* ---------------------------------------------------------------- */
    /* Editing and deleting                                             */
    /* ---------------------------------------------------------------- */

    public function test_an_enrollment_can_be_edited(): void
    {
        $student = $this->student();
        $this->enroll($student)->assertSessionHasNoErrors();
        $enrollment = StudentAcademicEnrollment::sole();

        $this->get(route('students.enrollments.edit', [$student->id, $enrollment->id]))
            ->assertOk()
            ->assertSee('Edit Academic Enrollment', false)
            ->assertSee('name="academic_session_id"', false);

        // Saving it unchanged must not conflict with itself.
        $this->put(route('students.enrollments.update', [$student->id, $enrollment->id]), $this->payload())
            ->assertRedirect(route('students.show', $student->id))
            ->assertSessionHasNoErrors();

        // A real change is applied.
        $this->put(route('students.enrollments.update', [$student->id, $enrollment->id]), $this->payload([
            'status' => 'Completed',
            'end_date' => '2027-03-31',
            'notes' => 'Finished the year.',
        ]))->assertSessionHasNoErrors();

        $enrollment->refresh();
        $this->assertSame('Completed', $enrollment->status);
        $this->assertSame('2027-03-31', $enrollment->end_date->format('Y-m-d'));
        $this->assertSame('Finished the year.', $enrollment->notes);
    }

    public function test_an_enrollment_can_be_deleted_without_touching_the_student(): void
    {
        $student = $this->student();
        $this->enroll($student)->assertSessionHasNoErrors();
        $enrollment = StudentAcademicEnrollment::sole();

        $this->delete(route('students.enrollments.destroy', [$student->id, $enrollment->id]))
            ->assertRedirect(route('students.show', $student->id))
            ->assertSessionHas('success');

        $this->assertSame(0, StudentAcademicEnrollment::count());
        $this->assertSame(1, Student::count());
        $this->assertNotNull($student->fresh());
    }

    public function test_another_students_enrollment_cannot_be_reached(): void
    {
        $mine = $this->student(['full_name' => 'Mine']);
        $other = $this->student(['full_name' => 'Other']);
        $this->enroll($other)->assertSessionHasNoErrors();
        $enrollment = StudentAcademicEnrollment::sole();

        $this->get(route('students.enrollments.edit', [$mine->id, $enrollment->id]))->assertNotFound();
        $this->put(route('students.enrollments.update', [$mine->id, $enrollment->id]), $this->payload())->assertNotFound();
        $this->delete(route('students.enrollments.destroy', [$mine->id, $enrollment->id]))->assertNotFound();

        $this->assertSame(1, StudentAcademicEnrollment::count());
    }

    /* ---------------------------------------------------------------- */
    /* The current student fields are untouched                         */
    /* ---------------------------------------------------------------- */

    public function test_the_student_placement_fields_are_left_alone(): void
    {
        $student = $this->student();

        $this->enroll($student, [
            'academic_session_id' => $this->nextSession->id,
            'department_id' => $this->school->id,
            'academic_class_id' => $this->ninth->id,
            'section_id' => null,
        ])->assertSessionHasNoErrors();

        // History does not write back to the student's current placement:
        // promotion/transfer will handle that in a later chunk.
        $student->refresh();
        $this->assertSame($this->session->id, $student->academic_session_id);
        $this->assertSame($this->hifz->id, $student->department_id);
        $this->assertSame($this->nazra->id, $student->academic_class_id);
    }

    /* ---------------------------------------------------------------- */
    /* Security                                                         */
    /* ---------------------------------------------------------------- */

    public function test_enrollment_management_requires_authentication(): void
    {
        $student = $this->student();
        $this->enroll($student)->assertSessionHasNoErrors();
        $enrollment = StudentAcademicEnrollment::sole();

        auth()->logout();

        $this->post(route('students.enrollments.store', $student->id), $this->payload())
            ->assertRedirect(route('login'));
        $this->get(route('students.enrollments.edit', [$student->id, $enrollment->id]))
            ->assertRedirect(route('login'));
        $this->put(route('students.enrollments.update', [$student->id, $enrollment->id]), $this->payload())
            ->assertRedirect(route('login'));
        $this->delete(route('students.enrollments.destroy', [$student->id, $enrollment->id]))
            ->assertRedirect(route('login'));
        $this->get(route('students.show', $student->id))
            ->assertRedirect(route('login'));

        $this->assertSame(1, StudentAcademicEnrollment::count());
    }

    /* ---------------------------------------------------------------- */
    /* Admission approval                                               */
    /* ---------------------------------------------------------------- */

    public function test_the_enrollment_carries_the_session_from_approval(): void
    {
        // Guards the column the approval flow now fills in.
        $student = $this->student();
        $this->enroll($student)->assertSessionHasNoErrors();

        $this->assertNotNull(StudentAcademicEnrollment::sole()->academic_session_id);
        $this->assertSame(
            0,
            DB::table('student_academic_enrollments')->whereNull('academic_session_id')->count()
        );
    }
}
