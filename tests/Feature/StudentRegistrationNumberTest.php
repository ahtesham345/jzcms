<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Department;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AdmissionDepartmentClassSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registration number generation for students.
 *
 * Both admission approval and manual student creation allocate from a single
 * sequence, so STD-2026-0001 may be an admitted applicant and STD-2026-0002 a
 * manually entered student, or the reverse. The counter is global to the
 * students table and never split by creation source.
 *
 * This is the behaviour that already existed for admissions - these tests
 * extend it to manual creation and confirm the two paths share one generator.
 */
class StudentRegistrationNumberTest extends TestCase
{
    use RefreshDatabase;

    private AcademicSession $session;

    private Department $hifz;

    private AcademicClass $nazra;

    private Section $nazraA;

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

        $this->hifz = Department::where('name', 'Hifz')->firstOrFail();

        $this->nazra = AcademicClass::where('department_id', $this->hifz->id)
            ->where('name', 'Nazra')->firstOrFail();

        $this->nazraA = Section::create([
            'name' => 'Nazra-A',
            'code' => 'NAZRAA',
            'academic_class_id' => $this->nazra->id,
            'status' => true,
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* Manual creation */
    /* ---------------------------------------------------------------- */

    public function test_manual_creation_generates_registration_number_automatically(): void
    {
        $this->post(route('students.store'), $this->payload())
            ->assertRedirect(route('students.index'))
            ->assertSessionHasNoErrors();

        $student = Student::sole();

        $this->assertMatchesRegularExpression('/^STD-\d{4}-\d{4}$/', $student->registration_number);
        $this->assertStringStartsWith('STD-'.date('Y').'-', $student->registration_number);
    }

    public function test_manual_creation_generates_the_next_sequential_number(): void
    {
        // Seed one student with an existing registration number
        $existing = Student::create(array_merge($this->studentAttributes(), [
            'registration_number' => 'STD-'.date('Y').'-0005',
        ]));

        $this->post(route('students.store'), $this->payload(['full_name' => 'New Student']))
            ->assertSessionHasNoErrors();

        $newStudent = Student::where('id', '!=', $existing->id)->sole();

        $this->assertSame('STD-'.date('Y').'-0006', $newStudent->registration_number);
    }

    public function test_manual_creation_starts_at_0001_when_no_students_exist(): void
    {
        $this->assertSame(0, Student::count());

        $this->post(route('students.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame('STD-'.date('Y').'-0001', Student::sole()->registration_number);
    }

    public function test_manual_creation_ignores_registration_numbers_from_other_years(): void
    {
        // Old student from 2025
        Student::create(array_merge($this->studentAttributes(), [
            'registration_number' => 'STD-2025-9999',
        ]));

        $this->post(route('students.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $newStudent = Student::where('registration_number', 'like', 'STD-'.date('Y').'-%')->sole();

        $this->assertSame('STD-'.date('Y').'-0001', $newStudent->registration_number);
    }

    public function test_manual_creation_ignores_legacy_registration_numbers(): void
    {
        // Legacy student with a non-standard format
        Student::create(array_merge($this->studentAttributes(), [
            'registration_number' => 'LEGACY-001',
        ]));

        $this->post(route('students.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $newStudent = Student::where('registration_number', 'like', 'STD-%')->sole();

        $this->assertSame('STD-'.date('Y').'-0001', $newStudent->registration_number);
    }

    public function test_manual_creation_does_not_accept_admin_supplied_registration_number(): void
    {
        // Even if an admin somehow posts a registration_number, it must be ignored
        $this->post(route('students.store'), $this->payload([
            'registration_number' => 'STD-'.date('Y').'-9999',
        ]))->assertSessionHasNoErrors();

        $student = Student::sole();

        // Should be auto-generated, not the posted value
        $this->assertSame('STD-'.date('Y').'-0001', $student->registration_number);
    }

    /* ---------------------------------------------------------------- */
    /* Admission approval */
    /* ---------------------------------------------------------------- */

    public function test_admission_approval_still_generates_registration_number_automatically(): void
    {
        $application = $this->application();

        $this->post(route('admissions.approve', $application->id), $this->approvalPayload())
            ->assertSessionHas('success');

        $student = Student::sole();

        $this->assertMatchesRegularExpression('/^STD-\d{4}-\d{4}$/', $student->registration_number);
        $this->assertStringStartsWith('STD-'.date('Y').'-', $student->registration_number);
    }

    public function test_admission_approval_generates_the_next_sequential_number(): void
    {
        // Existing student
        Student::create(array_merge($this->studentAttributes(), [
            'registration_number' => 'STD-'.date('Y').'-0003',
        ]));

        $application = $this->application();

        $this->post(route('admissions.approve', $application->id), $this->approvalPayload())
            ->assertSessionHasNoErrors();

        $newStudent = Student::where('full_name', 'Ahmed Ali')->sole();

        $this->assertSame('STD-'.date('Y').'-0004', $newStudent->registration_number);
    }

    /* ---------------------------------------------------------------- */
    /* Mixed creation sequence */
    /* ---------------------------------------------------------------- */

    public function test_manual_and_approval_share_one_continuous_sequence(): void
    {
        // Manual creation -> 0001
        $this->post(route('students.store'), $this->payload(['full_name' => 'Manual First']))
            ->assertSessionHasNoErrors();

        $manual1 = Student::where('full_name', 'Manual First')->sole();
        $this->assertSame('STD-'.date('Y').'-0001', $manual1->registration_number);

        // Admission approval -> 0002
        $app1 = $this->application(['student_name' => 'Approved Second']);
        $this->post(route('admissions.approve', $app1->id), $this->approvalPayload())
            ->assertSessionHasNoErrors();

        $approved1 = Student::where('full_name', 'Approved Second')->sole();
        $this->assertSame('STD-'.date('Y').'-0002', $approved1->registration_number);

        // Admission approval -> 0003
        $app2 = $this->application(['student_name' => 'Approved Third']);
        $this->post(route('admissions.approve', $app2->id), $this->approvalPayload())
            ->assertSessionHasNoErrors();

        $approved2 = Student::where('full_name', 'Approved Third')->sole();
        $this->assertSame('STD-'.date('Y').'-0003', $approved2->registration_number);

        // Manual creation -> 0004
        $this->post(route('students.store'), $this->payload(['full_name' => 'Manual Fourth']))
            ->assertSessionHasNoErrors();

        $manual2 = Student::where('full_name', 'Manual Fourth')->sole();
        $this->assertSame('STD-'.date('Y').'-0004', $manual2->registration_number);
    }

    /* ---------------------------------------------------------------- */
    /* Preservation */
    /* ---------------------------------------------------------------- */

    public function test_existing_registration_numbers_are_never_modified(): void
    {
        $existing1 = Student::create(array_merge($this->studentAttributes(), [
            'full_name' => 'Existing One',
            'registration_number' => 'STD-2025-0010',
        ]));

        $existing2 = Student::create(array_merge($this->studentAttributes(), [
            'full_name' => 'Existing Two',
            'registration_number' => 'STD-'.date('Y').'-0005',
        ]));

        // Create a new student
        $this->post(route('students.store'), $this->payload(['full_name' => 'New Student']))
            ->assertSessionHasNoErrors();

        // Existing students unchanged
        $this->assertSame('STD-2025-0010', $existing1->fresh()->registration_number);
        $this->assertSame('STD-'.date('Y').'-0005', $existing2->fresh()->registration_number);

        // New student gets next number after existing2
        $newStudent = Student::where('full_name', 'New Student')->sole();
        $this->assertSame('STD-'.date('Y').'-0006', $newStudent->registration_number);
    }

    /* ---------------------------------------------------------------- */
    /* Format */
    /* ---------------------------------------------------------------- */

    public function test_registration_number_format_is_std_year_four_digits(): void
    {
        $this->post(route('students.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $student = Student::sole();

        $this->assertMatchesRegularExpression('/^STD-\d{4}-\d{4}$/', $student->registration_number);

        // Format: STD-YYYY-#### (STD + hyphen + 4-digit year + hyphen + 4-digit sequence)
        $expectedLength = strlen('STD-'.date('Y').'-0001');
        $this->assertSame($expectedLength, strlen($student->registration_number));
    }

    public function test_registration_number_sequence_is_zero_padded_to_four_digits(): void
    {
        $this->post(route('students.store'), $this->payload(['full_name' => 'Student One']))
            ->assertSessionHasNoErrors();

        $this->post(route('students.store'), $this->payload(['full_name' => 'Student Two']))
            ->assertSessionHasNoErrors();

        $students = Student::orderBy('id')->get();

        $this->assertSame('STD-'.date('Y').'-0001', $students[0]->registration_number);
        $this->assertSame('STD-'.date('Y').'-0002', $students[1]->registration_number);
    }

    /* ---------------------------------------------------------------- */
    /* Database constraint */
    /* ---------------------------------------------------------------- */

    public function test_database_unique_constraint_remains_intact(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        Student::create(array_merge($this->studentAttributes(), [
            'full_name' => 'Student One',
            'registration_number' => 'STD-'.date('Y').'-0001',
        ]));

        // Attempt to create another with the same registration number
        Student::create(array_merge($this->studentAttributes(), [
            'full_name' => 'Student Two',
            'registration_number' => 'STD-'.date('Y').'-0001',
        ]));
    }

    /* ---------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------- */

    /**
     * Payload for manual student creation.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
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
     * Attributes for creating a student directly via the model.
     *
     * @return array<string, mixed>
     */
    private function studentAttributes(): array
    {
        return [
            'full_name' => 'Test Student',
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
     * Create an admission application ready for approval.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function application(array $overrides = []): AdmissionApplication
    {
        return AdmissionApplication::createWithApplicationNumber(array_merge([
            'student_name' => 'Ahmed Ali',
            'father_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'student_type' => 'Hifz',
            'madrassa_class_id' => $this->nazra->id,
            'status' => 'Passed',
            'test_result' => 'Passed',
        ], $overrides));
    }

    /**
     * Payload for approving an admission application.
     *
     * @return array<string, mixed>
     */
    private function approvalPayload(): array
    {
        return [
            'academic_session_id' => $this->session->id,
            'admission_date' => '2026-04-01',
            'emergency_contact' => '03009998887',
            'resident_type' => 'Local Resident',
            'section_id' => $this->nazraA->id,
        ];
    }
}
