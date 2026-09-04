<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Department;
use App\Models\ParentGuardian;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use Database\Seeders\AdmissionDepartmentClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Covers the parent record created and linked when an admission is approved.
 *
 * The project had no admission approval tests before this, so the existing
 * approval behaviour this integration sits inside — registration numbering,
 * the student fields, the enrollments, the application status — is asserted
 * here too, as regression cover.
 */
class AdmissionApprovalParentLinkTest extends TestCase
{
    use RefreshDatabase;

    private AcademicSession $session;

    private AcademicClass $hifzClass;

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

        $hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->hifzClass = AcademicClass::where('department_id', $hifz->id)
            ->where('name', 'Nazra')
            ->firstOrFail();
    }

    /**
     * A passed application, ready to approve.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function application(array $overrides = []): AdmissionApplication
    {
        return AdmissionApplication::createWithApplicationNumber(array_merge([
            'student_name' => 'Ahmed Ali',
            'father_name' => 'Muhammad Ali',
            'date_of_birth' => '2015-06-01',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'mother_mobile' => '03007654321',
            'permanent_address' => 'House 1, Lahore',
            'student_type' => 'Hifz',
            'madrassa_class_id' => $this->hifzClass->id,
            'admission_date' => '2026-04-01',
            'status' => 'Passed',
            'test_result' => 'Passed',
            'test_marks' => 80,
        ], $overrides));
    }

    /**
     * The placement the approval modal posts.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function placement(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'admission_date' => '2026-04-01',
            'emergency_contact' => '03009998887',
            'resident_type' => 'Local Resident',
        ], $overrides);
    }

    private function approve(AdmissionApplication $application, array $overrides = [])
    {
        return $this->post(route('admissions.approve', $application->id), $this->placement($overrides));
    }

    /* ---------------------------------------------------------------- */
    /* A new parent */
    /* ---------------------------------------------------------------- */

    public function test_approving_an_application_creates_and_links_the_father(): void
    {
        $application = $this->application();

        $this->approve($application)
            ->assertRedirect(route('admissions.show', $application->id))
            ->assertSessionHas('success');

        $parent = ParentGuardian::sole();
        $student = Student::sole();

        // The parent record itself.
        $this->assertSame('PAR-'.date('Y').'-0001', $parent->parent_id);
        $this->assertSame('Muhammad Ali', $parent->full_name);
        $this->assertSame('03001234567', $parent->mobile_number);
        $this->assertSame('Male', $parent->gender);
        $this->assertSame('Active', $parent->parent_status);

        // Nothing the application does not carry was invented.
        $this->assertNull($parent->father_name);
        $this->assertNull($parent->cnic_number);
        $this->assertNull($parent->email);
        $this->assertNull($parent->address);
        $this->assertNull($parent->occupation);
        $this->assertNull($parent->photo);
        $this->assertNull($parent->alternate_mobile);
        $this->assertNull($parent->notes);

        // The link.
        $pivot = $student->parents->sole()->pivot;
        $this->assertSame('Father', $pivot->relationship_type);
        $this->assertTrue($pivot->is_primary);
        $this->assertSame($parent->id, $student->parents->sole()->id);
    }

    public function test_the_parent_id_is_generated_by_the_existing_generator(): void
    {
        // An existing parent means the approval must continue the sequence
        // rather than start it.
        ParentGuardian::createWithParentId([
            'full_name' => 'Unrelated Parent',
            'gender' => 'Male',
            'mobile_number' => '03119998887',
            'parent_status' => 'Active',
        ]);

        $this->approve($this->application())->assertSessionHas('success');

        $created = ParentGuardian::where('full_name', 'Muhammad Ali')->sole();
        $this->assertSame('PAR-'.date('Y').'-0002', $created->parent_id);
        $this->assertMatchesRegularExpression('/^PAR-\d{4}-\d{4}$/', $created->parent_id);
    }

    public function test_the_new_parent_is_visible_from_both_profiles(): void
    {
        $this->approve($this->application())->assertSessionHas('success');

        $parent = ParentGuardian::sole();
        $student = Student::sole();

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('Parents / Guardians', false)
            ->assertSee('Muhammad Ali', false)
            ->assertSee($parent->parent_id, false)
            ->assertSee('Father', false)
            ->assertSee('Primary', false)
            ->assertDontSee('No parents or guardians linked.', false);

        $this->get(route('parents.show', $parent->id))
            ->assertOk()
            ->assertSee('Children / Students', false)
            ->assertSee('Ahmed Ali', false)
            ->assertSee($student->registration_number, false)
            ->assertDontSee('No students linked.', false);
    }

    /* ---------------------------------------------------------------- */
    /* No automatic reuse of an existing parent */
    /* ---------------------------------------------------------------- */

    public function test_an_existing_parent_on_the_same_number_is_neither_reused_nor_modified(): void
    {
        $existing = ParentGuardian::createWithParentId([
            'full_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'mobile_number' => '03001234567',
            'cnic_number' => '35201-1234567-1',
            'occupation' => 'Shopkeeper',
            'parent_status' => 'Active',
        ]);

        // Same name, same number: still not assumed to be the same man.
        $this->approve($this->application())->assertSessionHas('success');

        $this->assertSame(2, ParentGuardian::count(), 'Approval must create its own parent record');

        // The pre-existing record is untouched, and unlinked.
        $existing->refresh();
        $this->assertSame('Muhammad Ali', $existing->full_name);
        $this->assertSame('03001234567', $existing->mobile_number);
        $this->assertSame('35201-1234567-1', $existing->cnic_number);
        $this->assertSame('Shopkeeper', $existing->occupation);
        $this->assertCount(0, $existing->students, 'The existing parent must not be linked automatically');

        // The student is linked to the newly created record instead.
        $linked = Student::sole()->parents->sole();
        $this->assertNotSame($existing->id, $linked->id);
        $this->assertNull($linked->cnic_number);
    }

    public function test_no_mobile_matching_is_attempted_in_any_number_format(): void
    {
        // Every shape of the same number: none of them causes a reuse.
        $formats = ['0300-1234567', '+92 300 1234567', '923001234567', '3001234567'];

        foreach ($formats as $index => $stored) {
            ParentGuardian::createWithParentId([
                'full_name' => 'Muhammad Ali',
                'gender' => 'Male',
                'mobile_number' => $stored,
                'parent_status' => 'Active',
            ]);

            $application = $this->application([
                'student_name' => "Child {$index}",
                'father_mobile' => '03001234567',
            ]);

            $this->approve($application)->assertSessionHas('success');

            $this->assertSame(2, ParentGuardian::count(), "{$stored} must not have been matched");

            // Reset for the next format.
            DB::table('parent_student')->delete();
            Student::query()->delete();
            AdmissionApplication::query()->delete();
            ParentGuardian::query()->delete();
        }
    }

    public function test_an_unusual_number_is_stored_exactly_as_entered(): void
    {
        // A landline, or anything else: nothing is normalised or rejected,
        // because nothing is being matched against.
        $this->approve($this->application(['father_mobile' => '042-35678901']))
            ->assertSessionHas('success');

        $created = ParentGuardian::sole();
        $this->assertSame('042-35678901', $created->mobile_number);
        $this->assertSame($created->id, Student::sole()->parents->sole()->id);
    }

    /* ---------------------------------------------------------------- */
    /* Siblings */
    /* ---------------------------------------------------------------- */

    public function test_two_admissions_with_the_same_father_mobile_create_separate_parents(): void
    {
        // Intentional: two approvals are two separate events, and the system
        // has nothing reliable to identify one man across them.
        $first = $this->application(['student_name' => 'Ahmed Ali']);
        $second = $this->application(['student_name' => 'Bilal Ali', 'father_mobile' => '03001234567']);

        $this->approve($first)->assertSessionHas('success');
        $this->approve($second)->assertSessionHas('success');

        $this->assertSame(2, ParentGuardian::count());
        $this->assertSame(2, Student::count());
        $this->assertSame(2, DB::table('parent_student')->count());

        // Each parent holds exactly one child, as Father + Primary.
        foreach (ParentGuardian::with('students')->get() as $parent) {
            $this->assertCount(1, $parent->students);
            $this->assertSame('Father', $parent->students->sole()->pivot->relationship_type);
            $this->assertTrue($parent->students->sole()->pivot->is_primary);
        }

        // Two records, two IDs, both from the existing generator.
        $this->assertEqualsCanonicalizing(
            ['PAR-'.date('Y').'-0001', 'PAR-'.date('Y').'-0002'],
            ParentGuardian::pluck('parent_id')->all()
        );
    }

    public function test_two_admissions_with_different_father_mobiles_create_separate_parents(): void
    {
        // The same father, two numbers — exactly why matching was dropped.
        $first = $this->application(['student_name' => 'Ahmed Ali', 'father_mobile' => '03001234567']);
        $second = $this->application(['student_name' => 'Bilal Ali', 'father_mobile' => '03111112222']);

        $this->approve($first)->assertSessionHas('success');
        $this->approve($second)->assertSessionHas('success');

        $this->assertSame(2, ParentGuardian::count());
        $this->assertEqualsCanonicalizing(
            ['03001234567', '03111112222'],
            ParentGuardian::pluck('mobile_number')->all()
        );
        $this->assertEqualsCanonicalizing(
            ['Muhammad Ali', 'Muhammad Ali'],
            ParentGuardian::pluck('full_name')->all()
        );
    }

    public function test_the_admin_can_merge_the_duplicate_by_hand_afterwards(): void
    {
        // The documented recovery path: relink the sibling to the right
        // parent, then unlink the duplicate. Nothing is deleted for them.
        $first = $this->application(['student_name' => 'Ahmed Ali']);
        $second = $this->application(['student_name' => 'Bilal Ali']);

        $this->approve($first)->assertSessionHas('success');
        $this->approve($second)->assertSessionHas('success');

        $keep = ParentGuardian::orderBy('id')->first();
        $duplicate = ParentGuardian::orderByDesc('id')->first();
        $sibling = Student::where('full_name', 'Bilal Ali')->sole();

        // Unlink the auto-created duplicate, then link to the right parent.
        $this->delete(route('students.parents.destroy', [$sibling->id, $duplicate->id]))
            ->assertSessionHas('success');

        $this->post(route('students.parents.store', $sibling->id), [
            'parent_id' => $keep->id,
            'relationship_type' => 'Father',
            'is_primary' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertCount(2, $keep->fresh()->students);
        $this->assertCount(0, $duplicate->fresh()->students);
        $this->assertSame(2, Student::count(), 'No student may be lost');
        $this->assertSame(2, ParentGuardian::count(), 'The duplicate record itself is the admin to delete');
    }

    public function test_approval_never_creates_a_duplicate_pivot_row(): void
    {
        $application = $this->application();
        $this->approve($application)->assertSessionHas('success');

        // A second approval is refused outright, so no second row appears.
        $this->approve($application->fresh())
            ->assertSessionHas('error');

        $this->assertSame(1, DB::table('parent_student')->count());
        $this->assertSame(1, Student::count());
        $this->assertSame(1, ParentGuardian::count());
    }

    /* ---------------------------------------------------------------- */
    /* Missing or unusable father details */
    /* ---------------------------------------------------------------- */

    public function test_an_application_without_a_father_mobile_is_still_approved(): void
    {
        // father_mobile is NOT NULL on the table and required by both forms,
        // so a blank one can only be reached by writing it directly. The
        // guard still has to hold: approval must not fail over it.
        $application = $this->application();
        DB::table('admission_applications')->where('id', $application->id)->update(['father_mobile' => '']);
        $application->refresh();

        $this->approve($application)->assertSessionHas('success');

        // The approval went through, with no parent guessed at.
        $this->assertSame(1, Student::count());
        $this->assertSame(0, ParentGuardian::count());
        $this->assertSame(0, DB::table('parent_student')->count());
        $this->assertSame('Approved', $application->fresh()->status);
    }

    public function test_an_application_without_a_father_name_is_still_approved(): void
    {
        $application = $this->application();
        // father_name is required on the forms, so it is emptied directly.
        DB::table('admission_applications')->where('id', $application->id)->update(['father_name' => '']);

        $this->approve($application->fresh())->assertSessionHas('success');

        $this->assertSame(1, Student::count());
        $this->assertSame(0, ParentGuardian::count());
    }

    /* ---------------------------------------------------------------- */
    /* The mother is deliberately left alone */
    /* ---------------------------------------------------------------- */

    public function test_no_mother_parent_is_created_and_her_details_stay_on_the_student(): void
    {
        $this->approve($this->application())->assertSessionHas('success');

        // Exactly one parent, and it is the father.
        $this->assertSame(1, ParentGuardian::count());
        $this->assertSame(0, DB::table('parent_student')->where('relationship_type', 'Mother')->count());

        // The existing student fields are populated exactly as before.
        $student = Student::sole();
        $this->assertSame('Muhammad Ali', $student->father_name);
        $this->assertSame('03001234567', $student->father_mobile);
        $this->assertSame('03007654321', $student->mother_mobile);
        $this->assertSame('03009998887', $student->emergency_contact);
    }

    /* ---------------------------------------------------------------- */
    /* Rollback */
    /* ---------------------------------------------------------------- */

    public function test_a_failure_while_creating_the_parent_rolls_the_whole_approval_back(): void
    {
        $application = $this->application();

        ParentGuardian::creating(function () {
            throw new \RuntimeException('Parent creation failed');
        });

        try {
            $this->withoutExceptionHandling()->approve($application);
            $this->fail('The approval should have failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('Parent creation failed', $e->getMessage());
        } finally {
            ParentGuardian::flushEventListeners();
        }

        $this->assertSame(0, Student::count(), 'The student must roll back');
        $this->assertSame(0, ParentGuardian::count(), 'No orphan parent may be left behind');
        $this->assertSame(0, DB::table('parent_student')->count());
        $this->assertSame(0, StudentAcademicEnrollment::count());

        $application->refresh();
        $this->assertSame('Passed', $application->status, 'The application must remain unapproved');
        $this->assertNull($application->student_id);
        $this->assertFalse($application->isApproved());
    }

    public function test_a_failure_after_linking_leaves_an_unrelated_existing_parent_untouched(): void
    {
        $existing = ParentGuardian::createWithParentId([
            'full_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'mobile_number' => '03001234567',
            'occupation' => 'Shopkeeper',
            'parent_status' => 'Active',
        ]);

        $application = $this->application();

        // Fails on the last write of the transaction, after the student,
        // the enrollments and the link have all been created.
        AdmissionApplication::saving(function () {
            throw new \RuntimeException('Approval failed');
        });

        try {
            $this->withoutExceptionHandling()->approve($application);
            $this->fail('The approval should have failed');
        } catch (\RuntimeException $e) {
            $this->assertSame('Approval failed', $e->getMessage());
        } finally {
            AdmissionApplication::flushEventListeners();
        }

        $this->assertSame(0, Student::count());
        $this->assertSame(0, DB::table('parent_student')->count(), 'The link must roll back');

        // The parent existed before the approval, so it survives it.
        $this->assertSame(1, ParentGuardian::count());
        $existing->refresh();
        $this->assertSame('Muhammad Ali', $existing->full_name);
        $this->assertSame('Shopkeeper', $existing->occupation);
        $this->assertCount(0, $existing->students);

        $this->assertSame('Passed', $application->fresh()->status);
    }

    /* ---------------------------------------------------------------- */
    /* Existing approval behaviour */
    /* ---------------------------------------------------------------- */

    public function test_the_existing_approval_behaviour_is_unchanged(): void
    {
        $application = $this->application();

        $this->approve($application)->assertSessionHas('success');

        $student = Student::sole();

        // Registration numbering.
        $this->assertSame('STD-'.date('Y').'-0001', $student->registration_number);

        // The student fields carried across from the application.
        $this->assertSame('Ahmed Ali', $student->full_name);
        $this->assertSame('Muhammad Ali', $student->father_name);
        $this->assertSame('Male', $student->gender);
        $this->assertSame('Hifz', $student->student_type);
        $this->assertSame('Local Resident', $student->resident_type);
        $this->assertSame('Active', $student->student_status);
        $this->assertSame($this->session->id, $student->academic_session_id);

        // The class prefilled from the application, not from the modal.
        $this->assertSame($this->hifzClass->id, $student->academic_class_id);
        $this->assertSame($this->hifzClass->department_id, $student->department_id);

        // The enrollment, including the session the approval was made into.
        $enrollment = StudentAcademicEnrollment::sole();
        $this->assertSame($student->id, $enrollment->student_id);
        $this->assertSame('Madrassa', $enrollment->academic_track);
        $this->assertSame($this->session->id, $enrollment->academic_session_id);
        $this->assertSame($this->hifzClass->department_id, $enrollment->department_id);
        $this->assertSame($this->hifzClass->id, $enrollment->academic_class_id);
        $this->assertNull($enrollment->section_id, 'No section was chosen, so it stays null');
        $this->assertSame('2026-04-01', $enrollment->start_date->format('Y-m-d'));
        $this->assertSame('Active', $enrollment->status);

        // It is the student's current enrollment.
        $this->assertNotNull($student->activeAcademicEnrollment);
        $this->assertSame($enrollment->id, $student->activeAcademicEnrollment->id);

        // The application.
        $application->refresh();
        $this->assertSame('Approved', $application->status);
        $this->assertSame($student->id, $application->student_id);
        $this->assertTrue($application->isApproved());
    }

    public function test_a_dual_track_approval_creates_one_enrollment_per_track(): void
    {
        // The case the per-track unique index exists for: one student, one
        // session, two legitimate active enrollments.
        $school = Department::where('name', 'School')->firstOrFail();
        $ninth = AcademicClass::where('department_id', $school->id)->where('name', '9th')->firstOrFail();

        $application = $this->application([
            'student_type' => 'Hifz + School',
            'madrassa_class_id' => $this->hifzClass->id,
            'school_class_id' => $ninth->id,
        ]);

        $this->approve($application)->assertSessionHasNoErrors();

        $student = Student::sole();
        $enrollments = $student->academicEnrollments;

        $this->assertCount(2, $enrollments);
        $this->assertEqualsCanonicalizing(['Madrassa', 'School'], $enrollments->pluck('academic_track')->all());

        // Both in the same session, both active.
        $this->assertSame([$this->session->id, $this->session->id], $enrollments->pluck('academic_session_id')->all());
        $this->assertCount(2, $student->activeAcademicEnrollments);

        $this->assertSame($this->hifzClass->id, $enrollments->firstWhere('academic_track', 'Madrassa')->academic_class_id);
        $this->assertSame($ninth->id, $enrollments->firstWhere('academic_track', 'School')->academic_class_id);

        // And both show on the profile.
        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('Current Academic Enrollment', false)
            ->assertSee('Academic History', false)
            ->assertSee('9th', false);
    }

    public function test_approval_eligibility_is_unchanged(): void
    {
        // Not passed: refused before anything is created.
        $pending = $this->application(['status' => 'Pending', 'test_result' => null]);

        $this->approve($pending)->assertSessionHas('error');

        $this->assertSame(0, Student::count());
        $this->assertSame(0, ParentGuardian::count());
        $this->assertSame(0, DB::table('parent_student')->count());
    }

    public function test_the_public_admission_form_asks_for_no_father_cnic(): void
    {
        // The public form now sits behind a configured admission window, so
        // one is opened before asking what the form contains. What is being
        // tested here is the form's fields, not the schedule.
        Setting::current()->fill([
            'institution_name' => 'Jamia Zahidia',
            'address' => 'Faisalabad',
            'phone_number' => '03001234567',
            'principal_name' => 'Qari Abdul Rahman',
            'admission_form_enabled' => true,
            'admission_form_opens_at' => now()->subDay(),
            'admission_form_closes_at' => now()->addDay(),
        ])->save();

        Setting::forgetCurrent();

        // The public form stays student/admission focused. CNIC and the rest
        // of the parent detail are completed from Parent Management.
        $response = $this->get(route('public.admissions.apply'))->assertOk();

        $response->assertDontSee('CNIC', false);
        $response->assertDontSee('cnic', false);

        // Still asking for what it always asked for.
        $response->assertSee('name="father_name"', false);
        $response->assertSee('name="father_mobile"', false);

        // And a posted CNIC would be ignored rather than stored: there is no
        // such column on the applications table.
        $this->assertFalse(
            Schema::hasColumn('admission_applications', 'father_cnic')
        );
        $this->assertFalse(
            Schema::hasColumn('admission_applications', 'cnic_number')
        );
    }

    public function test_manual_linking_still_works_after_an_automatic_link(): void
    {
        $this->approve($this->application())->assertSessionHas('success');

        $student = Student::sole();
        $mother = ParentGuardian::createWithParentId([
            'full_name' => 'Fatima Bibi',
            'gender' => 'Female',
            'mobile_number' => '03007654321',
            'parent_status' => 'Active',
        ]);

        // The admin adds the mother by hand.
        $this->post(route('students.parents.store', $student->id), [
            'parent_id' => $mother->id,
            'relationship_type' => 'Mother',
            'is_primary' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertCount(2, $student->fresh()->parents);

        // And can unlink the automatically created father.
        $father = ParentGuardian::where('full_name', 'Muhammad Ali')->sole();
        $this->delete(route('students.parents.destroy', [$student->id, $father->id]))
            ->assertSessionHas('success');

        $this->assertCount(1, $student->fresh()->parents);
        $this->assertSame(2, ParentGuardian::count(), 'Unlinking must not delete the parent');
        $this->assertSame(1, Student::count());
    }
}
