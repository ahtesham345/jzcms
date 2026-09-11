<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Department;
use App\Models\DepartmentTerm;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Support\StudentTerms;
use Database\Seeders\AdmissionDepartmentClassSeeder;
use Database\Seeders\ComputerCourseSeeder;
use Database\Seeders\DepartmentTermsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the instructions a guardian reads and agrees to.
 *
 * They used to be one block in config shown to everybody. They are now the
 * department's, so the madrassa and the school can ask for different things,
 * and a student studying in two departments is shown both sets - madrassa
 * first, with a line that appears in both shown once.
 *
 * What is not department-specific: the heading and the agreement sentence.
 * One names the section and the other states consent, and neither depends on
 * what is being studied.
 *
 * The wording itself is nobody's business but the Imam's. Nothing here
 * invents an instruction; the tests that need two departments to differ add
 * a marker line and look for it.
 */
class StudentTermsTest extends TestCase
{
    use RefreshDatabase;

    private Department $hifz;

    private Department $school;

    private Department $darsENizami;

    private Department $computer;

    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        // The project's own roles and permissions, seeded rather than
        // invented here: the settings pages are permission-gated and
        // these tests must pass against the permission set the
        // application actually ships.
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);
        $this->seed(AdmissionDepartmentClassSeeder::class);
        $this->seed(ComputerCourseSeeder::class);
        $this->seed(DepartmentTermsSeeder::class);

        $this->session = AcademicSession::create([
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
            'status' => true,
        ]);

        $this->hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->school = Department::where('name', 'School')->firstOrFail();
        $this->darsENizami = Department::where('name', 'Dars-e-Nizami')->firstOrFail();
        $this->computer = Department::where('name', 'Computer')->firstOrFail();
    }

    /**
     * Replace one department's instructions.
     *
     * @param  array<int, string>  $lines
     */
    private function setTerms(Department $department, array $lines): void
    {
        DepartmentTerm::updateOrCreate(
            ['department_id' => $department->id],
            ['items' => DepartmentTerm::joinLines($lines), 'status' => true]
        );
    }

    /**
     * A student with the placements a student type calls for.
     *
     * @param  array<int, array{track: string, department: Department, class: AcademicClass}>  $placements
     */
    private function student(string $studentType, array $placements): Student
    {
        static $sequence = 0;
        $sequence++;

        $primary = $placements[0];

        $student = Student::create([
            'registration_number' => 'STD-2026-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'full_name' => 'Test Student',
            'father_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03009998887',
            'admission_date' => '2026-04-01',
            'academic_session_id' => $this->session->id,
            'student_status' => 'Active',
            'student_type' => $studentType,
            'resident_type' => 'Local Resident',
            'department_id' => $primary['department']->id,
            'academic_class_id' => $primary['class']->id,
        ]);

        foreach ($placements as $placement) {
            $student->academicEnrollments()->create([
                'academic_session_id' => $this->session->id,
                'academic_track' => $placement['track'],
                'department_id' => $placement['department']->id,
                'academic_class_id' => $placement['class']->id,
                'start_date' => '2026-04-01',
                'status' => 'Active',
            ]);
        }

        return $student;
    }

    private function classIn(Department $department): AcademicClass
    {
        return AcademicClass::where('department_id', $department->id)->orderBy('id')->firstOrFail();
    }

    /* ---------------------------------------------------------------- */
    /* Each department holds its own */
    /* ---------------------------------------------------------------- */

    public function test_every_real_department_is_seeded_with_the_existing_instructions(): void
    {
        $defaults = StudentTerms::defaults();

        $this->assertCount(17, $defaults);

        foreach ([$this->hifz, $this->school, $this->darsENizami, $this->computer] as $department) {
            $this->assertSame(
                $defaults,
                StudentTerms::forDepartment($department->id),
                "{$department->name} should start with the instructions the system already showed."
            );
        }
    }

    public function test_each_department_can_be_given_its_own_instructions(): void
    {
        $this->setTerms($this->hifz, ['HIFZ-ONE', 'HIFZ-TWO']);
        $this->setTerms($this->school, ['SCHOOL-ONE']);
        $this->setTerms($this->darsENizami, ['DARS-ONE']);
        $this->setTerms($this->computer, ['COMPUTER-ONE']);

        $this->assertSame(['HIFZ-ONE', 'HIFZ-TWO'], StudentTerms::forDepartment($this->hifz->id));
        $this->assertSame(['SCHOOL-ONE'], StudentTerms::forDepartment($this->school->id));
        $this->assertSame(['DARS-ONE'], StudentTerms::forDepartment($this->darsENizami->id));
        $this->assertSame(['COMPUTER-ONE'], StudentTerms::forDepartment($this->computer->id));
    }

    public function test_a_single_placement_student_type_resolves_its_own_department(): void
    {
        $this->setTerms($this->hifz, ['HIFZ-ONE']);
        $this->setTerms($this->school, ['SCHOOL-ONE']);
        $this->setTerms($this->darsENizami, ['DARS-ONE']);

        $this->assertSame(['HIFZ-ONE'], StudentTerms::forStudentType('Hifz'));
        $this->assertSame(['SCHOOL-ONE'], StudentTerms::forStudentType('School'));
        $this->assertSame(['DARS-ONE'], StudentTerms::forStudentType('Dars-e-Nizami'));
    }

    /* ---------------------------------------------------------------- */
    /* Two placements */
    /* ---------------------------------------------------------------- */

    public function test_hifz_and_school_shows_both_departments_madrassa_first(): void
    {
        $this->setTerms($this->hifz, ['HIFZ-ONE', 'HIFZ-TWO']);
        $this->setTerms($this->school, ['SCHOOL-ONE', 'SCHOOL-TWO']);

        $this->assertSame(
            ['HIFZ-ONE', 'HIFZ-TWO', 'SCHOOL-ONE', 'SCHOOL-TWO'],
            StudentTerms::forStudentType('Hifz + School')
        );
    }

    public function test_dars_e_nizami_and_computer_shows_both_departments_madrassa_first(): void
    {
        $this->setTerms($this->darsENizami, ['DARS-ONE', 'DARS-TWO']);
        $this->setTerms($this->computer, ['COMPUTER-ONE']);

        $this->assertSame(
            ['DARS-ONE', 'DARS-TWO', 'COMPUTER-ONE'],
            StudentTerms::forStudentType('Dars-e-Nizami + Computer')
        );
    }

    public function test_a_line_in_both_departments_is_shown_once(): void
    {
        $this->setTerms($this->hifz, ['SHARED', 'HIFZ-ONLY']);
        $this->setTerms($this->school, ['SHARED', 'SCHOOL-ONLY']);

        // The shared line keeps the position it first appeared in, which is
        // the madrassa's, so the order is not disturbed by removing it from
        // the school's copy.
        $this->assertSame(
            ['SHARED', 'HIFZ-ONLY', 'SCHOOL-ONLY'],
            StudentTerms::forStudentType('Hifz + School')
        );
    }

    public function test_identical_departments_produce_one_list_not_two(): void
    {
        // Which is the state after seeding: all four hold the same wording,
        // so a combined student reads it once, exactly as before this change.
        $this->assertSame(
            StudentTerms::defaults(),
            StudentTerms::forStudentType('Hifz + School')
        );

        $this->assertCount(17, StudentTerms::forStudentType('Dars-e-Nizami + Computer'));
    }

    public function test_no_combined_department_holds_instructions(): void
    {
        $combined = Department::create([
            'name' => 'Hifz + School',
            'code' => 'HFZSCH',
            'status' => true,
        ]);

        $this->seed(DepartmentTermsSeeder::class);

        // The seeder writes for the departments the mapping places students
        // in. A combination is a student type, so it is not one of them.
        $this->assertNull(DepartmentTerm::where('department_id', $combined->id)->first());
        $this->assertArrayNotHasKey($combined->id, StudentTerms::editableByDepartment());
    }

    /* ---------------------------------------------------------------- */
    /* Fallback */
    /* ---------------------------------------------------------------- */

    public function test_a_department_with_no_row_falls_back_to_the_defaults(): void
    {
        DepartmentTerm::where('department_id', $this->hifz->id)->delete();

        $this->assertSame(StudentTerms::defaults(), StudentTerms::forDepartment($this->hifz->id));
        $this->assertNotEmpty(StudentTerms::forStudentType('Hifz'));
    }

    public function test_a_department_with_empty_items_falls_back_to_the_defaults(): void
    {
        $this->setTerms($this->hifz, []);

        $this->assertSame(StudentTerms::defaults(), StudentTerms::forDepartment($this->hifz->id));
    }

    public function test_an_unknown_student_type_still_shows_instructions(): void
    {
        $this->assertSame(StudentTerms::defaults(), StudentTerms::forStudentType('Nonsense'));
        $this->assertSame(StudentTerms::defaults(), StudentTerms::forStudentType(null));
    }

    /* ---------------------------------------------------------------- */
    /* Heading and agreement stay global */
    /* ---------------------------------------------------------------- */

    public function test_the_heading_and_agreement_are_not_department_specific(): void
    {
        $this->setTerms($this->hifz, ['HIFZ-ONE']);
        $this->setTerms($this->school, ['SCHOOL-ONE']);

        $this->assertSame(config('admission_instructions.heading'), StudentTerms::heading());
        $this->assertSame(config('admission_instructions.agreement'), StudentTerms::agreement());

        // And nothing about them is stored per department.
        $this->assertNotContains('heading', array_keys(DepartmentTerm::first()->getAttributes()));
    }

    /* ---------------------------------------------------------------- */
    /* Where they are displayed */
    /* ---------------------------------------------------------------- */

    public function test_the_public_admission_form_carries_a_list_for_every_student_type(): void
    {
        $this->setTerms($this->hifz, ['HIFZ-ONE']);
        $this->setTerms($this->school, ['SCHOOL-ONE']);
        $this->setTerms($this->darsENizami, ['DARS-ONE']);
        $this->setTerms($this->computer, ['COMPUTER-ONE']);

        Setting::create([
            'institution_name' => 'Jamia Zahidia',
            'address' => 'Faisalabad',
            'phone_number' => '03001234567',
            'principal_name' => 'Qari Abdul Rahman',
            'timezone' => 'Asia/Karachi',
            'date_format' => 'd M, Y',
            'admission_form_enabled' => true,
            'admission_form_opens_at' => now()->subDay(),
            'admission_form_closes_at' => now()->addMonth(),
        ]);

        auth()->logout();

        $terms = $this->get(route('public.admissions.apply'))
            ->assertOk()
            ->viewData('termsByStudentType');

        $this->assertSame(['HIFZ-ONE'], $terms['Hifz']);
        $this->assertSame(['SCHOOL-ONE'], $terms['School']);
        $this->assertSame(['DARS-ONE'], $terms['Dars-e-Nizami']);
        $this->assertSame(['HIFZ-ONE', 'SCHOOL-ONE'], $terms['Hifz + School']);
        $this->assertSame(['DARS-ONE', 'COMPUTER-ONE'], $terms['Dars-e-Nizami + Computer']);
    }

    public function test_the_student_profile_resolves_from_the_students_real_placements(): void
    {
        $this->setTerms($this->hifz, ['HIFZ-ONE']);
        $this->setTerms($this->school, ['SCHOOL-ONE']);

        $student = $this->student('Hifz + School', [
            ['track' => 'Madrassa', 'department' => $this->hifz, 'class' => $this->classIn($this->hifz)],
            ['track' => 'School', 'department' => $this->school, 'class' => $this->classIn($this->school)],
        ]);

        $this->assertSame(['HIFZ-ONE', 'SCHOOL-ONE'], StudentTerms::forStudent($student));

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('HIFZ-ONE')
            ->assertSee('SCHOOL-ONE');
    }

    public function test_a_student_with_no_active_placement_falls_back_to_their_student_type(): void
    {
        $this->setTerms($this->hifz, ['HIFZ-ONE']);

        $student = $this->student('Hifz', [
            ['track' => 'Madrassa', 'department' => $this->hifz, 'class' => $this->classIn($this->hifz)],
        ]);

        $student->academicEnrollments()->update(['status' => 'Completed']);

        $this->assertSame(['HIFZ-ONE'], StudentTerms::forStudent($student->fresh()));
    }

    public function test_the_admin_admission_detail_resolves_from_the_student_type(): void
    {
        $this->setTerms($this->darsENizami, ['DARS-ONE']);
        $this->setTerms($this->computer, ['COMPUTER-ONE']);

        $application = AdmissionApplication::create([
            'application_number' => 'ADM-2026-0001',
            'student_name' => 'Zaid Ahmed',
            'father_name' => 'Ali',
            'date_of_birth' => '2010-01-01',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'permanent_address' => 'Faisalabad',
            'student_type' => 'Dars-e-Nizami + Computer',
            'status' => 'Pending',
            'academic_session_id' => $this->session->id,
            'instructions_accepted' => true,
            'instructions_accepted_at' => now(),
        ]);

        $this->assertSame(['DARS-ONE', 'COMPUTER-ONE'], StudentTerms::forApplication($application));

        $this->get(route('admissions.show', $application->id))
            ->assertOk()
            ->assertSee('DARS-ONE')
            ->assertSee('COMPUTER-ONE');
    }

    /* ---------------------------------------------------------------- */
    /* The settings page */
    /* ---------------------------------------------------------------- */

    public function test_the_settings_page_shows_a_block_for_each_real_department(): void
    {
        $this->get(route('settings.student-terms.edit'))
            ->assertOk()
            ->assertSee('Hifz')
            ->assertSee('School')
            ->assertSee('Dars-e-Nizami')
            ->assertSee('Computer')
            ->assertSee('terms['.$this->hifz->id.']', false);
    }

    public function test_saving_the_settings_page_changes_what_is_displayed(): void
    {
        $student = $this->student('Hifz', [
            ['track' => 'Madrassa', 'department' => $this->hifz, 'class' => $this->classIn($this->hifz)],
        ]);

        $this->put(route('settings.student-terms.update'), [
            'terms' => [
                $this->hifz->id => "NEW-HIFZ-ONE\nNEW-HIFZ-TWO",
                $this->school->id => 'SCHOOL-ONE',
                $this->darsENizami->id => 'DARS-ONE',
                $this->computer->id => 'COMPUTER-ONE',
            ],
        ])->assertRedirect(route('settings.student-terms.edit'))->assertSessionHas('success');

        $this->assertSame(['NEW-HIFZ-ONE', 'NEW-HIFZ-TWO'], StudentTerms::forStudentType('Hifz'));

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('NEW-HIFZ-ONE')
            ->assertSee('NEW-HIFZ-TWO');
    }

    public function test_blank_lines_do_not_become_empty_instructions(): void
    {
        $this->put(route('settings.student-terms.update'), [
            'terms' => [
                $this->hifz->id => "ONE\n\n\r\n   \nTWO\n",
                $this->school->id => 'SCHOOL-ONE',
                $this->darsENizami->id => 'DARS-ONE',
                $this->computer->id => 'COMPUTER-ONE',
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['ONE', 'TWO'], StudentTerms::forDepartment($this->hifz->id));
    }

    public function test_urdu_and_line_structure_survive_a_save(): void
    {
        $urdu = [
            'ہر وقت باوضو رہنے کی عادت ڈالیں۔',
            'ہر ماہ کی 10 تاریخ سے پہلے فیس جمع کرانا لازمی ہے۔',
        ];

        $this->put(route('settings.student-terms.update'), [
            'terms' => [
                $this->hifz->id => implode("\n", $urdu),
                $this->school->id => 'SCHOOL-ONE',
                $this->darsENizami->id => 'DARS-ONE',
                $this->computer->id => 'COMPUTER-ONE',
            ],
        ])->assertSessionHasNoErrors();

        // Byte for byte, lines and all.
        $this->assertSame($urdu, StudentTerms::forDepartment($this->hifz->id));

        $this->get(route('settings.student-terms.edit'))
            ->assertOk()
            ->assertSee($urdu[0])
            ->assertSee($urdu[1]);
    }

    public function test_instructions_cannot_be_saved_for_a_department_the_page_did_not_offer(): void
    {
        $combined = Department::create([
            'name' => 'Hifz + School',
            'code' => 'HFZSCH',
            'status' => true,
        ]);

        $this->put(route('settings.student-terms.update'), [
            'terms' => [$combined->id => 'SHOULD-NOT-SAVE'],
        ])->assertSessionHasErrors('terms');

        $this->assertNull(DepartmentTerm::where('department_id', $combined->id)->first());
    }

    /* ---------------------------------------------------------------- */
    /* Nothing is hardcoded any more */
    /* ---------------------------------------------------------------- */

    public function test_no_display_location_reads_the_config_items_directly(): void
    {
        // The config keeps the wording as the fallback, but no page may read
        // the item list from it: a department's own instructions would then
        // be ignored wherever that happened.
        $views = [
            resource_path('views/components/admission-instructions.blade.php'),
            resource_path('views/public/admissions/apply.blade.php'),
            resource_path('views/admissions/show.blade.php'),
            resource_path('views/students/show.blade.php'),
        ];

        foreach ($views as $view) {
            $this->assertStringNotContainsString(
                'admission_instructions.items',
                file_get_contents($view),
                basename($view).' still reads the instruction list straight from config.'
            );
        }
    }
}
