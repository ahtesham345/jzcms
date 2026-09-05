<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Department;
use App\Models\ParentGuardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use Database\Seeders\AdmissionDepartmentClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers manual student creation and editing from Student Management.
 *
 * The point of the module is that a student entered by hand comes out the
 * same as a student admitted through Admission Management: placed on the
 * right track, with the enrollment rows that record it, and with a parent on
 * file. These tests describe that from the outside - what the forms accept,
 * what they refuse, and what ends up in the database.
 */
class StudentManagementPlacementTest extends TestCase
{
    use RefreshDatabase;

    private AcademicSession $session;

    private AcademicSession $otherSession;

    private Department $hifz;

    private Department $school;

    private Department $darsENizami;

    private AcademicClass $nazra;

    private AcademicClass $primary;

    private AcademicClass $salEAwwal;

    private Section $nazraA;

    private Section $primaryA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->seed(AdmissionDepartmentClassSeeder::class);

        $this->session = AcademicSession::create([
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
            'status' => true,
        ]);

        $this->otherSession = AcademicSession::create([
            'name' => '2027-2028',
            'start_date' => '2027-04-01',
            'end_date' => '2028-03-31',
            'status' => true,
        ]);

        $this->hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->school = Department::where('name', 'School')->firstOrFail();
        $this->darsENizami = Department::where('name', 'Dars-e-Nizami')->firstOrFail();

        $this->nazra = AcademicClass::where('department_id', $this->hifz->id)
            ->where('name', 'Nazra')->firstOrFail();
        $this->primary = AcademicClass::where('department_id', $this->school->id)
            ->where('name', 'Primary Section')->firstOrFail();
        $this->salEAwwal = AcademicClass::where('department_id', $this->darsENizami->id)
            ->orderBy('id')->firstOrFail();

        $this->nazraA = $this->section('Nazra-A', $this->nazra);
        $this->primaryA = $this->section('Primary-A', $this->primary);
    }

    private function section(string $name, AcademicClass $class): Section
    {
        return Section::create([
            'name' => $name,
            'code' => strtoupper(str_replace('-', '', $name)),
            'academic_class_id' => $class->id,
            'status' => true,
        ]);
    }

    /**
     * The payload the Add Student form posts, placed in Hifz by default.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'registration_number' => 'STD-2026-0001',
            'full_name' => 'Ahmed Ali',
            'father_name' => 'Muhammad Ali',
            'date_of_birth' => '2015-06-01',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03009998887',
            'admission_date' => '2026-04-01',
            'academic_session_id' => $this->session->id,
            'student_status' => 'Active',
            'student_type' => 'Hifz',
            'resident_type' => 'Local Resident',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->nazraA->id,
        ], $overrides);
    }

    /**
     * The payload a Hifz + School student is created with.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function dualPayload(array $overrides = []): array
    {
        $payload = $this->payload(array_merge([
            'student_type' => 'Hifz + School',
            'madrassa_department_id' => $this->hifz->id,
            'madrassa_class_id' => $this->nazra->id,
            'madrassa_section_id' => $this->nazraA->id,
            'school_department_id' => $this->school->id,
            'school_class_id' => $this->primary->id,
            'school_section_id' => $this->primaryA->id,
        ], $overrides));

        // A dual-track student posts one placement per track; the plain
        // fields belong to the single-track form and are prohibited.
        unset($payload['department_id'], $payload['academic_class_id'], $payload['section_id']);

        return array_merge($payload, $overrides);
    }

    /* ---------------------------------------------------------------- */
    /* The forms themselves */
    /* ---------------------------------------------------------------- */

    public function test_the_add_student_form_renders(): void
    {
        $this->get(route('students.create'))
            ->assertOk()
            ->assertSee('Student Type')
            ->assertSee('Link an Existing Parent');
    }

    public function test_the_edit_student_form_renders(): void
    {
        $student = $this->createStudent();

        $this->get(route('students.edit', $student->id))->assertOk();
    }

    /* ---------------------------------------------------------------- */
    /* Manual creation */
    /* ---------------------------------------------------------------- */

    public function test_a_student_can_be_created_manually(): void
    {
        $this->post(route('students.store'), $this->payload())
            ->assertRedirect(route('students.index'))
            ->assertSessionHas('success');

        $student = Student::sole();

        $this->assertSame('Ahmed Ali', $student->full_name);
        $this->assertSame('Hifz', $student->student_type);
        $this->assertSame($this->hifz->id, $student->department_id);
        $this->assertSame($this->nazra->id, $student->academic_class_id);
        $this->assertSame($this->nazraA->id, $student->section_id);
    }

    public function test_manual_creation_records_the_academic_enrollment(): void
    {
        $this->post(route('students.store'), $this->payload())->assertSessionHasNoErrors();

        $student = Student::sole();
        $enrollment = StudentAcademicEnrollment::sole();

        $this->assertSame($student->id, $enrollment->student_id);
        $this->assertSame('Madrassa', $enrollment->academic_track);
        $this->assertSame($this->hifz->id, $enrollment->department_id);
        $this->assertSame($this->nazra->id, $enrollment->academic_class_id);
        $this->assertSame($this->nazraA->id, $enrollment->section_id);
        $this->assertSame('Active', $enrollment->status);
        $this->assertSame('2026-04-01', $enrollment->start_date->format('Y-m-d'));
        $this->assertNull($enrollment->end_date);
    }

    public function test_the_enrollment_is_attached_to_the_chosen_academic_session(): void
    {
        $this->post(route('students.store'), $this->payload([
            'academic_session_id' => $this->otherSession->id,
        ]))->assertSessionHasNoErrors();

        $student = Student::sole();

        $this->assertSame($this->otherSession->id, $student->academic_session_id);
        $this->assertSame($this->otherSession->id, StudentAcademicEnrollment::sole()->academic_session_id);
    }

    public function test_the_add_student_form_offers_the_current_session_first(): void
    {
        $this->get(route('students.create'))
            ->assertOk()
            ->assertSee('value="'.$this->session->id.'" selected', false);
    }

    public function test_a_section_is_optional(): void
    {
        $this->post(route('students.store'), $this->payload(['section_id' => null]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Student::sole()->section_id);
        $this->assertNull(StudentAcademicEnrollment::sole()->section_id);
    }

    /* ---------------------------------------------------------------- */
    /* One placement per department */
    /* ---------------------------------------------------------------- */

    public function test_a_school_student_is_placed_in_the_school(): void
    {
        $this->post(route('students.store'), $this->payload([
            'student_type' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->primaryA->id,
        ]))->assertSessionHasNoErrors();

        $enrollment = StudentAcademicEnrollment::sole();

        $this->assertSame('School', $enrollment->academic_track);
        $this->assertSame($this->school->id, $enrollment->department_id);
        $this->assertSame($this->primary->id, $enrollment->academic_class_id);
    }

    public function test_a_dars_e_nizami_student_is_placed_in_that_department(): void
    {
        $this->post(route('students.store'), $this->payload([
            'student_type' => 'Dars-e-Nizami',
            'department_id' => $this->darsENizami->id,
            'academic_class_id' => $this->salEAwwal->id,
            'section_id' => null,
        ]))->assertSessionHasNoErrors();

        $enrollment = StudentAcademicEnrollment::sole();

        $this->assertSame('Madrassa', $enrollment->academic_track);
        $this->assertSame($this->darsENizami->id, $enrollment->department_id);
        $this->assertSame($this->salEAwwal->id, $enrollment->academic_class_id);
    }

    public function test_the_department_must_match_the_student_type(): void
    {
        $this->post(route('students.store'), $this->payload([
            // A Hifz student cannot be placed in the School department.
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->primaryA->id,
        ]))->assertSessionHasErrors('department_id');

        $this->assertSame(0, Student::count());
    }

    public function test_a_class_from_another_department_is_rejected(): void
    {
        $this->post(route('students.store'), $this->payload([
            // The Hifz department with a School class under it.
            'academic_class_id' => $this->primary->id,
            'section_id' => null,
        ]))->assertSessionHasErrors('academic_class_id');

        $this->assertSame(0, Student::count());
    }

    public function test_a_section_from_another_class_is_rejected(): void
    {
        $this->post(route('students.store'), $this->payload([
            // Nazra with a section belonging to the school's Primary class.
            'section_id' => $this->primaryA->id,
        ]))->assertSessionHasErrors('section_id');

        $this->assertSame(0, Student::count());
    }

    public function test_an_inactive_class_is_rejected(): void
    {
        $this->nazra->update(['status' => false]);

        $this->post(route('students.store'), $this->payload())
            ->assertSessionHasErrors('academic_class_id');

        $this->assertSame(0, Student::count());
    }

    /* ---------------------------------------------------------------- */
    /* Hifz + School */
    /* ---------------------------------------------------------------- */

    public function test_a_hifz_and_school_student_gets_a_placement_on_both_tracks(): void
    {
        $this->post(route('students.store'), $this->dualPayload())
            ->assertSessionHasNoErrors();

        $student = Student::sole();
        $enrollments = $student->academicEnrollments()->orderBy('academic_track')->get();

        $this->assertCount(2, $enrollments);

        $madrassa = $enrollments->firstWhere('academic_track', 'Madrassa');
        $this->assertSame($this->hifz->id, $madrassa->department_id);
        $this->assertSame($this->nazra->id, $madrassa->academic_class_id);
        $this->assertSame($this->nazraA->id, $madrassa->section_id);
        $this->assertSame('Active', $madrassa->status);

        $school = $enrollments->firstWhere('academic_track', 'School');
        $this->assertSame($this->school->id, $school->department_id);
        $this->assertSame($this->primary->id, $school->academic_class_id);
        $this->assertSame($this->primaryA->id, $school->section_id);
        $this->assertSame('Active', $school->status);

        // The student row itself holds the madrassa placement, the same rule
        // the admission approval follows.
        $this->assertSame($this->hifz->id, $student->department_id);
        $this->assertSame($this->nazra->id, $student->academic_class_id);
        $this->assertSame($this->nazraA->id, $student->section_id);
    }

    public function test_a_hifz_and_school_student_cannot_be_placed_with_the_single_track_fields(): void
    {
        $this->post(route('students.store'), $this->payload([
            'student_type' => 'Hifz + School',
        ]))->assertSessionHasErrors(['department_id', 'madrassa_department_id', 'school_department_id']);

        $this->assertSame(0, Student::count());
    }

    public function test_the_madrassa_side_of_a_dual_student_cannot_use_a_school_department(): void
    {
        $this->post(route('students.store'), $this->dualPayload([
            'madrassa_department_id' => $this->school->id,
            'madrassa_class_id' => $this->primary->id,
            'madrassa_section_id' => $this->primaryA->id,
        ]))->assertSessionHasErrors('madrassa_department_id');

        $this->assertSame(0, Student::count());
    }

    public function test_the_school_side_of_a_dual_student_cannot_use_a_madrassa_class(): void
    {
        $this->post(route('students.store'), $this->dualPayload([
            'school_class_id' => $this->nazra->id,
            'school_section_id' => null,
        ]))->assertSessionHasErrors('school_class_id');

        $this->assertSame(0, Student::count());
    }

    public function test_a_single_track_student_cannot_post_the_dual_track_fields(): void
    {
        $this->post(route('students.store'), $this->payload([
            'madrassa_department_id' => $this->hifz->id,
            'madrassa_class_id' => $this->nazra->id,
        ]))->assertSessionHasErrors(['madrassa_department_id', 'madrassa_class_id']);

        $this->assertSame(0, Student::count());
    }

    /* ---------------------------------------------------------------- */
    /* Parents */
    /* ---------------------------------------------------------------- */

    public function test_an_existing_parent_can_be_linked_during_creation(): void
    {
        $parent = ParentGuardian::createWithParentId([
            'full_name' => 'Muhammad Ali',
            'mobile_number' => '03001234567',
            'gender' => 'Male',
            'parent_status' => 'Active',
        ]);

        $this->post(route('students.store'), $this->payload([
            'parent_id' => $parent->id,
            'parent_relationship_type' => 'Father',
            'parent_is_primary' => '1',
        ]))->assertSessionHasNoErrors();

        $student = Student::sole();
        $linked = $student->parents()->sole();

        $this->assertSame($parent->id, $linked->id);
        $this->assertSame('Father', $linked->pivot->relationship_type);
        $this->assertTrue((bool) $linked->pivot->is_primary);

        // The link is readable from the parent's side too, and no second
        // parent record was invented.
        $this->assertSame(1, ParentGuardian::count());
        $this->assertTrue($parent->fresh()->students->contains($student->id));
    }

    public function test_the_student_profile_shows_the_linked_parent(): void
    {
        $parent = ParentGuardian::createWithParentId([
            'full_name' => 'Zubair Khan',
            'mobile_number' => '03001112222',
            'gender' => 'Male',
            'parent_status' => 'Active',
        ]);

        $this->post(route('students.store'), $this->payload([
            'parent_id' => $parent->id,
            'parent_relationship_type' => 'Guardian',
        ]))->assertSessionHasNoErrors();

        $this->get(route('students.show', Student::sole()->id))
            ->assertOk()
            ->assertSee('Zubair Khan');
    }

    public function test_the_father_is_recorded_when_no_parent_is_chosen(): void
    {
        $this->post(route('students.store'), $this->payload())->assertSessionHasNoErrors();

        $student = Student::sole();
        $parent = ParentGuardian::sole();

        // The same record the admission approval creates.
        $this->assertSame('Muhammad Ali', $parent->full_name);
        $this->assertSame('03001234567', $parent->mobile_number);
        $this->assertSame('Male', $parent->gender);
        $this->assertSame('Active', $parent->parent_status);

        $pivot = $student->parents()->sole()->pivot;
        $this->assertSame('Father', $pivot->relationship_type);
        $this->assertTrue((bool) $pivot->is_primary);
    }

    public function test_a_nonexistent_parent_cannot_be_attached(): void
    {
        $this->post(route('students.store'), $this->payload([
            'parent_id' => 9999,
            'parent_relationship_type' => 'Father',
        ]))->assertSessionHasErrors('parent_id');

        $this->assertSame(0, Student::count());
        $this->assertSame(0, ParentGuardian::count());
    }

    public function test_a_linked_parent_needs_a_relationship(): void
    {
        $parent = ParentGuardian::createWithParentId([
            'full_name' => 'Muhammad Ali',
            'mobile_number' => '03001234567',
            'gender' => 'Male',
            'parent_status' => 'Active',
        ]);

        $this->post(route('students.store'), $this->payload([
            'parent_id' => $parent->id,
        ]))->assertSessionHasErrors('parent_relationship_type');

        $this->assertSame(0, Student::count());
    }

    public function test_nothing_is_written_when_the_placement_is_rejected(): void
    {
        $this->post(route('students.store'), $this->payload([
            'academic_class_id' => $this->primary->id,
        ]))->assertSessionHasErrors();

        $this->assertSame(0, Student::count());
        $this->assertSame(0, StudentAcademicEnrollment::count());
        $this->assertSame(0, ParentGuardian::count());
    }

    /* ---------------------------------------------------------------- */
    /* Editing */
    /* ---------------------------------------------------------------- */

    public function test_editing_moves_the_student_and_their_enrollment_together(): void
    {
        $student = $this->createStudent();
        $hifzClass = AcademicClass::where('department_id', $this->hifz->id)
            ->where('name', 'Hifz')->firstOrFail();

        $this->put(route('students.update', $student->id), $this->payload([
            'academic_class_id' => $hifzClass->id,
            'section_id' => null,
        ]))->assertRedirect(route('students.index'));

        $student->refresh();
        $this->assertSame($hifzClass->id, $student->academic_class_id);
        $this->assertNull($student->section_id);

        $enrollment = $student->academicEnrollments()->sole();
        $this->assertSame($hifzClass->id, $enrollment->academic_class_id);
        $this->assertNull($enrollment->section_id);
        $this->assertSame('Active', $enrollment->status);
    }

    public function test_editing_rejects_a_class_from_another_department(): void
    {
        $student = $this->createStudent();

        $this->put(route('students.update', $student->id), $this->payload([
            'academic_class_id' => $this->primary->id,
            'section_id' => null,
        ]))->assertSessionHasErrors('academic_class_id');

        $this->assertSame($this->nazra->id, $student->fresh()->academic_class_id);
    }

    public function test_editing_rejects_a_section_from_another_class(): void
    {
        $student = $this->createStudent();

        $this->put(route('students.update', $student->id), $this->payload([
            'section_id' => $this->primaryA->id,
        ]))->assertSessionHasErrors('section_id');

        $this->assertSame($this->nazraA->id, $student->fresh()->section_id);
    }

    public function test_editing_rejects_a_department_that_does_not_match_the_student_type(): void
    {
        $student = $this->createStudent();

        $this->put(route('students.update', $student->id), $this->payload([
            'student_type' => 'School',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->nazraA->id,
        ]))->assertSessionHasErrors('department_id');
    }

    public function test_editing_gives_a_student_with_no_enrollment_the_one_they_were_missing(): void
    {
        // A student recorded before manual creation wrote enrollments.
        $student = Student::create($this->studentAttributes());

        $this->assertSame(0, $student->academicEnrollments()->count());

        $this->put(route('students.update', $student->id), $this->payload())
            ->assertSessionHasNoErrors();

        $enrollment = $student->academicEnrollments()->sole();

        $this->assertSame('Madrassa', $enrollment->academic_track);
        $this->assertSame($this->session->id, $enrollment->academic_session_id);
        $this->assertSame($this->nazra->id, $enrollment->academic_class_id);
        $this->assertSame($this->nazraA->id, $enrollment->section_id);
        $this->assertSame('Active', $enrollment->status);
    }

    public function test_editing_a_dual_student_moves_both_placements(): void
    {
        $this->post(route('students.store'), $this->dualPayload())->assertSessionHasNoErrors();

        $student = Student::sole();
        $hifzClass = AcademicClass::where('department_id', $this->hifz->id)
            ->where('name', 'Hifz')->firstOrFail();
        $middle = AcademicClass::where('department_id', $this->school->id)
            ->where('name', 'Middle Section')->firstOrFail();

        $this->put(route('students.update', $student->id), $this->dualPayload([
            'madrassa_class_id' => $hifzClass->id,
            'madrassa_section_id' => null,
            'school_class_id' => $middle->id,
            'school_section_id' => null,
        ]))->assertSessionHasNoErrors();

        $student->refresh();

        $this->assertSame($hifzClass->id, $student->activeEnrollmentForTrack('Madrassa')->academic_class_id);
        $this->assertSame($middle->id, $student->activeEnrollmentForTrack('School')->academic_class_id);
        $this->assertSame(2, $student->academicEnrollments()->count());
    }

    public function test_editing_does_not_rewrite_completed_enrollment_history(): void
    {
        $student = $this->createStudent();

        $completed = $student->academicEnrollments()->create([
            'academic_session_id' => $this->otherSession->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'status' => 'Completed',
        ]);

        $hifzClass = AcademicClass::where('department_id', $this->hifz->id)
            ->where('name', 'Hifz')->firstOrFail();

        $this->put(route('students.update', $student->id), $this->payload([
            'academic_class_id' => $hifzClass->id,
            'section_id' => null,
        ]))->assertSessionHasNoErrors();

        $completed->refresh();

        $this->assertSame('Completed', $completed->status);
        $this->assertSame($this->nazra->id, $completed->academic_class_id);
        $this->assertSame($this->otherSession->id, $completed->academic_session_id);
    }

    /* ---------------------------------------------------------------- */
    /* Access */
    /* ---------------------------------------------------------------- */

    public function test_a_guest_cannot_create_or_edit_a_student(): void
    {
        $student = $this->createStudent();

        auth()->logout();

        $this->post(route('students.store'), $this->payload(['registration_number' => 'STD-2026-0999']))
            ->assertRedirect(route('login'));

        $this->put(route('students.update', $student->id), $this->payload([
            'academic_class_id' => $this->primary->id,
        ]))->assertRedirect(route('login'));

        $this->get(route('students.create'))->assertRedirect(route('login'));

        $this->assertSame(1, Student::count());
        $this->assertSame($this->nazra->id, $student->fresh()->academic_class_id);
    }

    /* ---------------------------------------------------------------- */
    /* The admission flow is untouched */
    /* ---------------------------------------------------------------- */

    public function test_approving_an_admission_still_creates_the_student_enrollment_and_father(): void
    {
        $application = AdmissionApplication::createWithApplicationNumber([
            'student_name' => 'Bilal Ahmed',
            'father_name' => 'Ahmed Raza',
            'gender' => 'Male',
            'father_mobile' => '03004445555',
            'student_type' => 'Hifz',
            'madrassa_class_id' => $this->nazra->id,
            'status' => 'Passed',
            'test_result' => 'Passed',
        ]);

        $this->post(route('admissions.approve', $application->id), [
            'academic_session_id' => $this->session->id,
            'admission_date' => '2026-04-01',
            'emergency_contact' => '03009998887',
            'resident_type' => 'Local Resident',
            'section_id' => $this->nazraA->id,
        ])->assertSessionHas('success');

        $student = Student::sole();
        $enrollment = StudentAcademicEnrollment::sole();
        $parent = ParentGuardian::sole();

        $this->assertSame('Bilal Ahmed', $student->full_name);
        $this->assertSame($this->nazra->id, $student->academic_class_id);
        $this->assertSame('Madrassa', $enrollment->academic_track);
        $this->assertSame($this->nazraA->id, $enrollment->section_id);
        $this->assertSame($this->session->id, $enrollment->academic_session_id);
        $this->assertSame('Ahmed Raza', $parent->full_name);
        $this->assertSame('Father', $student->parents()->sole()->pivot->relationship_type);
    }

    /* ---------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------- */

    /**
     * The student columns, without the placement or parent form fields.
     *
     * @return array<string, mixed>
     */
    private function studentAttributes(): array
    {
        return [
            'registration_number' => 'STD-2026-0001',
            'full_name' => 'Ahmed Ali',
            'father_name' => 'Muhammad Ali',
            'date_of_birth' => '2015-06-01',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03009998887',
            'admission_date' => '2026-04-01',
            'academic_session_id' => $this->session->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->nazraA->id,
            'student_status' => 'Active',
            'student_type' => 'Hifz',
            'resident_type' => 'Local Resident',
        ];
    }

    /**
     * A student created through the manual form, enrollment and all.
     */
    private function createStudent(): Student
    {
        $this->post(route('students.store'), $this->payload())->assertSessionHasNoErrors();

        return Student::sole();
    }
}
