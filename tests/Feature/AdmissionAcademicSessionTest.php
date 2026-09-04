<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Department;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AdmissionDepartmentClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the academic session an admission application is filed under.
 *
 * The session is never taken from the browser. It is resolved server side
 * from the institution's current session and stamped on the record, and the
 * point of most of these tests is that no request can change that: the
 * public form does not send a session, the form requests declare no rule for
 * one, and the creation path discards anything passed under that name.
 *
 * The one place a session may be chosen by hand is the admin edit page,
 * where an application filed before the session existed can be corrected -
 * and even there it is frozen once a student has been created.
 */
class AdmissionAcademicSessionTest extends TestCase
{
    use RefreshDatabase;

    private AcademicSession $current;

    private AcademicSession $previous;

    private AcademicClass $nazra;

    private Department $hifz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdmissionDepartmentClassSeeder::class);

        $this->current = AcademicSession::create([
            'name' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'status' => true,
        ]);
        $this->previous = AcademicSession::create([
            'name' => '2025-2026', 'start_date' => '2025-04-01', 'end_date' => '2026-03-31',
            'status' => true,
        ]);

        $this->hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->nazra = AcademicClass::where('department_id', $this->hifz->id)
            ->where('name', 'Nazra')
            ->firstOrFail();

        // The public form is now behind a configured admission window, and
        // is closed until one is open. These tests are about the academic
        // session rather than the schedule, so they open a window that is
        // comfortably around today and leave it alone.
        $this->openAdmissionWindow();
    }

    /**
     * Put the public admission form inside an open admission window.
     */
    private function openAdmissionWindow(): void
    {
        Setting::current()->fill([
            'institution_name' => 'Jamia Zahidia',
            'address' => 'Faisalabad',
            'phone_number' => '03001234567',
            'principal_name' => 'Qari Abdul Rahman',
            'admission_form_enabled' => true,
            'admission_form_opens_at' => now()->subMonth(),
            'admission_form_closes_at' => now()->addMonth(),
        ])->save();

        Setting::forgetCurrent();
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    /**
     * A valid public submission.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function publicPayload(array $overrides = []): array
    {
        return array_merge([
            'student_name' => 'Ahtesham Shakeel',
            'father_name' => 'Shakeel Ahmed',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'student_type' => 'Hifz',
            'madrassa_class_id' => $this->nazra->id,
            'instructions_accepted' => '1',
        ], $overrides);
    }

    /* ---------------------------------------------------------------- */
    /* The current session helper */
    /* ---------------------------------------------------------------- */

    public function test_the_current_session_must_be_both_current_and_active(): void
    {
        $this->assertSame($this->current->id, AcademicSession::current()?->id);

        // Switched off, so it is no longer the session anything is filed
        // into, whatever the is_current flag still says.
        $this->current->update(['status' => false]);
        $this->assertNull(AcademicSession::current());

        $this->current->update(['status' => true, 'is_current' => false]);
        $this->assertNull(AcademicSession::current());
    }

    /* ---------------------------------------------------------------- */
    /* The public form */
    /* ---------------------------------------------------------------- */

    public function test_the_public_form_shows_the_current_academic_session(): void
    {
        $content = $this->get(route('public.admissions.apply'))->assertOk()->getContent();

        $this->assertStringContainsString('Academic Session:', $content);
        $this->assertStringContainsString('2026-2027', $content);

        // Shown, not offered: there is no session input for a browser to
        // change, and no session name posted back.
        $this->assertStringNotContainsString('name="academic_session_id"', $content);
    }

    public function test_a_public_application_is_filed_under_the_current_session(): void
    {
        $this->post(route('public.admissions.store'), $this->publicPayload())
            ->assertRedirect(route('public.admissions.confirmation'));

        $application = AdmissionApplication::firstOrFail();

        $this->assertSame($this->current->id, $application->academic_session_id);
    }

    public function test_a_submitted_session_id_cannot_override_the_current_session(): void
    {
        // The attack: a hand-crafted request naming last year's session.
        $this->post(route('public.admissions.store'), $this->publicPayload([
            'academic_session_id' => $this->previous->id,
        ]))->assertRedirect(route('public.admissions.confirmation'));

        $application = AdmissionApplication::firstOrFail();

        $this->assertSame($this->current->id, $application->academic_session_id);
        $this->assertNotSame($this->previous->id, $application->academic_session_id);
    }

    public function test_a_submitted_session_id_for_a_session_that_does_not_exist_is_ignored(): void
    {
        $this->post(route('public.admissions.store'), $this->publicPayload([
            'academic_session_id' => 9999,
        ]))->assertRedirect(route('public.admissions.confirmation'));

        $this->assertSame(
            $this->current->id,
            AdmissionApplication::firstOrFail()->academic_session_id
        );
    }

    public function test_the_public_form_is_closed_when_no_session_is_open(): void
    {
        AcademicSession::query()->update(['is_current' => false]);

        $content = $this->get(route('public.admissions.apply'))->assertOk()->getContent();

        $this->assertStringContainsString('Admissions are closed at the moment', $content);
        // The form itself is not rendered, so nothing can be filled in.
        $this->assertStringNotContainsString('name="student_name"', $content);
    }

    public function test_a_public_submission_is_refused_when_no_session_is_open(): void
    {
        AcademicSession::query()->update(['is_current' => false]);

        $this->post(route('public.admissions.store'), $this->publicPayload())
            ->assertSessionHasErrors('academic_session');

        // Nothing was filed. An application with no session would sit in the
        // system belonging to no year and appearing on no notice.
        $this->assertSame(0, AdmissionApplication::count());
    }

    public function test_the_existing_public_validation_still_applies(): void
    {
        // The session work must not have loosened anything else.
        $this->post(route('public.admissions.store'), $this->publicPayload([
            'student_name' => '',
            'instructions_accepted' => '',
        ]))->assertSessionHasErrors(['student_name', 'instructions_accepted']);

        $this->assertSame(0, AdmissionApplication::count());
    }

    /* ---------------------------------------------------------------- */
    /* The admin form */
    /* ---------------------------------------------------------------- */

    public function test_an_admin_created_application_is_filed_under_the_current_session(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admissions.store'), [
                'student_name' => 'Bilal Hussain',
                'father_name' => 'Hussain Ahmed',
                'gender' => 'Male',
                'father_mobile' => '03001234567',
                'student_type' => 'Hifz',
                'status' => 'Pending',
            ])->assertRedirect(route('admissions.index'));

        $this->assertSame(
            $this->current->id,
            AdmissionApplication::firstOrFail()->academic_session_id
        );
    }

    public function test_an_admin_cannot_post_a_session_onto_a_new_application(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admissions.store'), [
                'student_name' => 'Bilal Hussain',
                'father_name' => 'Hussain Ahmed',
                'gender' => 'Male',
                'father_mobile' => '03001234567',
                'student_type' => 'Hifz',
                'status' => 'Pending',
                'academic_session_id' => $this->previous->id,
            ])->assertRedirect(route('admissions.index'));

        $this->assertSame(
            $this->current->id,
            AdmissionApplication::firstOrFail()->academic_session_id
        );
    }

    public function test_the_admin_create_page_names_the_session_it_will_file_into(): void
    {
        $content = $this->actingAs($this->admin())
            ->get(route('admissions.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Academic Session:', $content);
        $this->assertStringContainsString('2026-2027', $content);
    }

    public function test_the_admin_create_page_warns_when_there_is_no_current_session(): void
    {
        AcademicSession::query()->update(['is_current' => false]);

        $this->actingAs($this->admin())
            ->get(route('admissions.create'))
            ->assertOk()
            ->assertSee('No current academic session is set.');
    }

    /* ---------------------------------------------------------------- */
    /* Correcting the session */
    /* ---------------------------------------------------------------- */

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function application(array $overrides = []): AdmissionApplication
    {
        return AdmissionApplication::createWithApplicationNumber(array_merge([
            'student_name' => 'Ahtesham Shakeel',
            'father_name' => 'Shakeel Ahmed',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'student_type' => 'Hifz',
            'status' => 'Pending',
        ], $overrides));
    }

    /**
     * The fields the edit form always posts back.
     *
     * @return array<string, mixed>
     */
    private function editPayload(AdmissionApplication $application, array $overrides = []): array
    {
        return array_merge([
            'student_name' => $application->student_name,
            'father_name' => $application->father_name,
            'gender' => $application->gender,
            'father_mobile' => $application->father_mobile,
            'student_type' => $application->student_type,
            'status' => $application->status,
            'academic_session_id' => $application->academic_session_id,
        ], $overrides);
    }

    public function test_an_edit_that_does_not_touch_the_session_leaves_it_alone(): void
    {
        $application = $this->application();

        $this->actingAs($this->admin())
            ->put(route('admissions.update', $application), $this->editPayload($application, [
                'student_name' => 'Corrected Name',
            ]))->assertRedirect(route('admissions.index'));

        $application->refresh();

        $this->assertSame('Corrected Name', $application->student_name);
        $this->assertSame($this->current->id, $application->academic_session_id);
    }

    public function test_an_admin_can_correct_the_session_on_a_legacy_application(): void
    {
        $application = $this->application();

        // A legacy row: filed before the session was recorded.
        $application->academic_session_id = null;
        $application->save();

        $this->actingAs($this->admin())
            ->put(route('admissions.update', $application), $this->editPayload($application, [
                'academic_session_id' => $this->current->id,
            ]))->assertRedirect(route('admissions.index'));

        $this->assertSame($this->current->id, $application->refresh()->academic_session_id);
    }

    public function test_the_session_cannot_be_set_to_one_that_is_not_active(): void
    {
        $application = $this->application();
        $this->previous->update(['status' => false]);

        $this->actingAs($this->admin())
            ->put(route('admissions.update', $application), $this->editPayload($application, [
                'academic_session_id' => $this->previous->id,
            ]))->assertSessionHasErrors('academic_session_id');

        $this->assertSame($this->current->id, $application->refresh()->academic_session_id);
    }

    public function test_the_session_is_frozen_once_a_student_has_been_created(): void
    {
        $application = $this->application();

        // Stands in for an approval: what matters here is that a student id
        // is present, which is what locks the record.
        $application->student_id = $this->studentId();
        $application->save();

        $this->actingAs($this->admin())
            ->put(route('admissions.update', $application), $this->editPayload($application, [
                'academic_session_id' => $this->previous->id,
            ]))->assertSessionHasErrors('academic_session_id');

        $this->assertSame($this->current->id, $application->refresh()->academic_session_id);
    }

    /**
     * Create a bare student, only so an application can be locked against it.
     */
    private function studentId(): int
    {
        return Student::create([
            'registration_number' => 'STD-2026-0001',
            'full_name' => 'Ahtesham Shakeel',
            'father_name' => 'Shakeel Ahmed',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03001234567',
            'admission_date' => '2026-04-01',
            'academic_session_id' => $this->current->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'student_status' => 'Active',
            'student_type' => 'Hifz',
            'resident_type' => 'Local Resident',
        ])->id;
    }

    /* ---------------------------------------------------------------- */
    /* The listing */
    /* ---------------------------------------------------------------- */

    public function test_the_listing_filters_by_academic_session(): void
    {
        $this->application(['student_name' => 'This Year']);

        $lastYear = $this->application(['student_name' => 'Last Year']);
        $lastYear->academic_session_id = $this->previous->id;
        $lastYear->save();

        $content = $this->actingAs($this->admin())
            ->get(route('admissions.index', ['academic_session_id' => $this->previous->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Last Year', $content);
        $this->assertStringNotContainsString('This Year', $content);
    }

    public function test_the_listing_shows_every_session_by_default_including_legacy_rows(): void
    {
        $this->application(['student_name' => 'This Year']);

        $legacy = $this->application(['student_name' => 'Legacy Row']);
        $legacy->academic_session_id = null;
        $legacy->save();

        $content = $this->actingAs($this->admin())
            ->get(route('admissions.index'))
            ->assertOk()
            ->getContent();

        // Defaulting the filter to the current session would have hidden the
        // legacy row from the main admission screen, which is why it does not.
        $this->assertStringContainsString('This Year', $content);
        $this->assertStringContainsString('Legacy Row', $content);
        $this->assertStringContainsString('Not Assigned', $content);
    }

    public function test_the_session_filter_combines_with_the_other_filters(): void
    {
        $passed = $this->application(['student_name' => 'Passed This Year']);
        $passed->forceFill([
            'status' => 'Passed',
            'test_result' => 'Passed',
            'gender' => 'Male',
        ])->save();

        $this->application(['student_name' => 'Pending This Year']);

        $content = $this->actingAs($this->admin())
            ->get(route('admissions.index', [
                'academic_session_id' => $this->current->id,
                'test_result' => 'Passed',
                'gender' => 'Male',
                'student_type' => 'Hifz',
                'search' => 'Passed This',
            ]))->assertOk()->getContent();

        $this->assertStringContainsString('Passed This Year', $content);
        $this->assertStringNotContainsString('Pending This Year', $content);
    }
}
