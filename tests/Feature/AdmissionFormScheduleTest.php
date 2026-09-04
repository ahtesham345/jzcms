<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Department;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\AdmissionDepartmentClassSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Covers when the public admission form may be used.
 *
 * The rule has one authority - the stored schedule measured against the
 * server's clock - and two enforcement points, the page and the submission.
 * Most of what is asserted here is that those two never disagree: a form
 * that cannot be opened cannot be posted to either, including by somebody
 * who opened it while admissions were still running and posted it later.
 *
 * Time is frozen with Carbon::setTestNow() rather than waited out, so the
 * boundaries can be tested to the second: exactly at the opening and exactly
 * at the closing the form is open, and one second either side of the window
 * it is not.
 */
class AdmissionFormScheduleTest extends TestCase
{
    use RefreshDatabase;

    /** Inside the window configured by openWindow(). */
    private const DURING = '2026-09-10 12:00:00';

    private const OPENS_AT = '2026-09-05 08:00:00';

    private const CLOSES_AT = '2026-09-15 23:59:00';

    private AcademicClass $nazra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(AdmissionDepartmentClassSeeder::class);

        AcademicSession::create([
            'name' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'status' => true,
        ]);

        $hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->nazra = AcademicClass::where('department_id', $hifz->id)->where('name', 'Nazra')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Sign in as somebody allowed to manage the settings.
     */
    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $this->actingAs($user);

        return $user;
    }

    /**
     * Save an admission window straight to the settings row.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function openWindow(array $overrides = []): Setting
    {
        $settings = Setting::current();

        $settings->fill(array_merge([
            'institution_name' => 'Jamia Zahidia',
            'address' => 'Faisalabad',
            'phone_number' => '03001234567',
            'principal_name' => 'Qari Abdul Rahman',
            'admission_form_enabled' => true,
            'admission_form_opens_at' => self::OPENS_AT,
            'admission_form_closes_at' => self::CLOSES_AT,
        ], $overrides))->save();

        Setting::forgetCurrent();

        return Setting::current();
    }

    /**
     * A settings submission for the admission form page.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function schedulePayload(array $overrides = []): array
    {
        return array_merge([
            'admission_form_enabled' => '1',
            'opens_date' => '2026-09-05',
            'opens_time' => '08:00',
            'closes_date' => '2026-09-15',
            'closes_time' => '23:59',
        ], $overrides);
    }

    /**
     * A valid public admission submission.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function applicationPayload(array $overrides = []): array
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

    private function atTime(string $moment): void
    {
        Carbon::setTestNow(Carbon::parse($moment, config('app.timezone')));
    }

    /* ---------------------------------------------------------------- */
    /* The settings page */
    /* ---------------------------------------------------------------- */

    public function test_an_admin_can_view_the_admission_form_settings(): void
    {
        $this->actingAsAdmin();

        $this->get(route('settings.admission-form.edit'))
            ->assertOk()
            ->assertSee('Admission Form Schedule')
            ->assertSee('Admission Form Enabled');
    }

    public function test_the_settings_module_links_both_of_its_pages(): void
    {
        $this->actingAsAdmin();

        // A section within the existing Settings module, reachable from the
        // general page rather than from a new sidebar entry.
        $this->get(route('settings.edit'))
            ->assertOk()
            ->assertSee(route('settings.admission-form.edit'), false);

        $this->get(route('settings.admission-form.edit'))
            ->assertOk()
            ->assertSee(route('settings.edit'), false);
    }

    public function test_an_admin_can_save_an_admission_schedule(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.admission-form.update'), $this->schedulePayload())
            ->assertRedirect(route('settings.admission-form.edit'))
            ->assertSessionHas('success');

        Setting::forgetCurrent();
        $settings = Setting::current();

        $this->assertTrue($settings->admission_form_enabled);
        $this->assertSame('2026-09-05 08:00:00', $settings->admission_form_opens_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-15 23:59:00', $settings->admission_form_closes_at->format('Y-m-d H:i:s'));
    }

    public function test_a_saved_schedule_is_populated_when_the_page_is_reopened(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.admission-form.update'), $this->schedulePayload());

        $content = $this->get(route('settings.admission-form.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('value="2026-09-05"', $content);
        $this->assertStringContainsString('value="08:00"', $content);
        $this->assertStringContainsString('value="2026-09-15"', $content);
        $this->assertStringContainsString('value="23:59"', $content);
    }

    public function test_a_closing_time_before_the_opening_time_is_rejected(): void
    {
        $this->actingAsAdmin();

        // Same day, closing an hour before it opens.
        $this->put(route('settings.admission-form.update'), $this->schedulePayload([
            'opens_date' => '2026-09-10',
            'opens_time' => '10:00',
            'closes_date' => '2026-09-10',
            'closes_time' => '09:00',
        ]))->assertSessionHasErrors('closes_date');

        Setting::forgetCurrent();
        $this->assertFalse(Setting::current()->admission_form_enabled);
    }

    public function test_a_window_spanning_several_days_is_accepted(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.admission-form.update'), $this->schedulePayload([
            'opens_date' => '2026-09-10',
            'opens_time' => '08:00',
            'closes_date' => '2026-09-20',
            'closes_time' => '23:59',
        ]))->assertSessionHasNoErrors();

        Setting::forgetCurrent();
        $this->assertTrue(Setting::current()->admission_form_enabled);
    }

    public function test_enabling_the_form_requires_a_complete_window(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.admission-form.update'), $this->schedulePayload([
            'opens_time' => '',
            'closes_date' => '',
        ]))->assertSessionHasErrors(['opens_time', 'closes_date']);

        Setting::forgetCurrent();
        $this->assertFalse(Setting::current()->admission_form_enabled);
    }

    public function test_a_disabled_form_can_be_saved_with_no_window_at_all(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.admission-form.update'), [
            'admission_form_enabled' => '0',
            'opens_date' => '',
            'opens_time' => '',
            'closes_date' => '',
            'closes_time' => '',
        ])->assertSessionHasNoErrors()->assertRedirect(route('settings.admission-form.edit'));

        Setting::forgetCurrent();
        $settings = Setting::current();

        $this->assertFalse($settings->admission_form_enabled);
        $this->assertNull($settings->admission_form_opens_at);
    }

    public function test_disabling_the_form_keeps_the_saved_window(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.admission-form.update'), $this->schedulePayload());

        // Switched off, window left exactly as it was, so turning it back on
        // does not mean typing the dates in again.
        $this->put(route('settings.admission-form.update'), $this->schedulePayload([
            'admission_form_enabled' => '0',
        ]))->assertSessionHasNoErrors();

        Setting::forgetCurrent();
        $settings = Setting::current();

        $this->assertFalse($settings->admission_form_enabled);
        $this->assertSame('2026-09-05 08:00:00', $settings->admission_form_opens_at->format('Y-m-d H:i:s'));
    }

    public function test_the_admission_form_settings_are_behind_the_settings_permission(): void
    {
        $this->get(route('settings.admission-form.edit'))->assertRedirect(route('login'));

        $teacher = User::factory()->create();
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)
            ->get(route('settings.admission-form.edit'))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->put(route('settings.admission-form.update'), $this->schedulePayload())
            ->assertForbidden();
    }

    /* ---------------------------------------------------------------- */
    /* The public page */
    /* ---------------------------------------------------------------- */

    public function test_the_form_is_shown_during_the_window(): void
    {
        $this->openWindow();
        $this->atTime(self::DURING);

        $this->get(route('public.admissions.apply'))
            ->assertOk()
            ->assertSee('name="student_name"', false);
    }

    public function test_the_form_is_not_shown_before_the_opening_time(): void
    {
        $this->openWindow();
        $this->atTime('2026-09-01 09:00:00');

        $response = $this->get(route('public.admissions.apply'))->assertForbidden();

        $response->assertSee('Admissions Are Not Open Yet');
        // The configured opening, read back from the settings rather than
        // written into the page.
        $response->assertSee('5 September 2026 at 8:00 AM');
        $response->assertDontSee('name="student_name"', false);
    }

    public function test_the_form_is_not_shown_after_the_closing_time(): void
    {
        $this->openWindow();
        $this->atTime('2026-09-20 09:00:00');

        $response = $this->get(route('public.admissions.apply'))->assertForbidden();

        $response->assertSee('Admissions Are Closed');
        $response->assertSee('15 September 2026 at 11:59 PM');
        $response->assertDontSee('name="student_name"', false);
    }

    public function test_the_form_is_not_shown_when_the_toggle_is_off(): void
    {
        // Inside the window, but switched off: the toggle wins.
        $this->openWindow(['admission_form_enabled' => false]);
        $this->atTime(self::DURING);

        $this->get(route('public.admissions.apply'))
            ->assertForbidden()
            ->assertSee('Admissions Are Currently Closed')
            ->assertDontSee('name="student_name"', false);
    }

    public function test_an_unconfigured_installation_does_not_expose_the_form(): void
    {
        // Nothing saved at all. The safe default is closed: an institution
        // that never configured admissions has not decided to accept any.
        $this->assertFalse(Setting::current()->admission_form_enabled);

        $this->get(route('public.admissions.apply'))
            ->assertForbidden()
            ->assertSee('Admissions Are Currently Closed')
            ->assertDontSee('name="student_name"', false);
    }

    public function test_an_enabled_but_incomplete_window_does_not_expose_the_form(): void
    {
        // Enabled with no closing time. This must not read as "open forever".
        $this->openWindow(['admission_form_closes_at' => null]);
        $this->atTime(self::DURING);

        $this->get(route('public.admissions.apply'))
            ->assertForbidden()
            ->assertSee('Admissions Are Currently Closed');
    }

    /* ---------------------------------------------------------------- */
    /* The boundaries */
    /* ---------------------------------------------------------------- */

    public function test_the_window_is_inclusive_at_both_ends(): void
    {
        $this->openWindow();

        $this->atTime(self::OPENS_AT);
        $this->assertTrue(Setting::current()->admissionFormIsOpen(), 'Exactly at the opening time.');

        $this->atTime(self::CLOSES_AT);
        $this->assertTrue(Setting::current()->admissionFormIsOpen(), 'Exactly at the closing time.');
    }

    public function test_one_second_either_side_of_the_window_is_closed(): void
    {
        $this->openWindow();

        $this->atTime('2026-09-05 07:59:59');
        $this->assertFalse(Setting::current()->admissionFormIsOpen(), 'One second before the opening.');
        $this->assertSame(Setting::ADMISSION_FORM_NOT_YET_OPEN, Setting::current()->admissionFormState());

        $this->atTime('2026-09-15 23:59:01');
        $this->assertFalse(Setting::current()->admissionFormIsOpen(), 'One second after the closing.');
        $this->assertSame(Setting::ADMISSION_FORM_CLOSED, Setting::current()->admissionFormState());
    }

    public function test_the_boundaries_hold_over_the_http_request_too(): void
    {
        $this->openWindow();

        $this->atTime(self::OPENS_AT);
        $this->get(route('public.admissions.apply'))->assertOk();

        $this->atTime('2026-09-05 07:59:59');
        $this->get(route('public.admissions.apply'))->assertForbidden();

        $this->atTime(self::CLOSES_AT);
        $this->get(route('public.admissions.apply'))->assertOk();

        $this->atTime('2026-09-15 23:59:01');
        $this->get(route('public.admissions.apply'))->assertForbidden();
    }

    /* ---------------------------------------------------------------- */
    /* The public submission */
    /* ---------------------------------------------------------------- */

    public function test_a_submission_during_the_window_succeeds(): void
    {
        $this->openWindow();
        $this->atTime(self::DURING);

        $this->post(route('public.admissions.store'), $this->applicationPayload())
            ->assertRedirect(route('public.admissions.confirmation'));

        // The existing workflow is untouched: numbered, pending, and filed
        // under the current academic session.
        $application = AdmissionApplication::firstOrFail();

        $this->assertStringStartsWith('APP-', $application->application_number);
        $this->assertSame('Pending', $application->status);
        $this->assertSame(AcademicSession::currentId(), $application->academic_session_id);
    }

    public function test_a_submission_before_the_opening_time_is_refused(): void
    {
        $this->openWindow();
        $this->atTime('2026-09-01 09:00:00');

        $this->post(route('public.admissions.store'), $this->applicationPayload())
            ->assertForbidden();

        $this->assertSame(0, AdmissionApplication::count());
    }

    public function test_a_submission_after_the_closing_time_is_refused(): void
    {
        $this->openWindow();
        $this->atTime('2026-09-20 09:00:00');

        $this->post(route('public.admissions.store'), $this->applicationPayload())
            ->assertForbidden();

        $this->assertSame(0, AdmissionApplication::count());
    }

    public function test_a_form_opened_before_closing_cannot_be_submitted_afterwards(): void
    {
        $this->openWindow();

        // The form is fetched while admissions are running...
        $this->atTime(self::DURING);
        $this->get(route('public.admissions.apply'))->assertOk();

        // ...the tab is left open, and posted a week after they closed.
        $this->atTime('2026-09-22 10:00:00');

        $this->post(route('public.admissions.store'), $this->applicationPayload())
            ->assertForbidden();

        $this->assertSame(0, AdmissionApplication::count());
    }

    public function test_a_submission_is_refused_when_the_toggle_is_off(): void
    {
        $this->openWindow(['admission_form_enabled' => false]);
        $this->atTime(self::DURING);

        $this->post(route('public.admissions.store'), $this->applicationPayload())
            ->assertForbidden();

        $this->assertSame(0, AdmissionApplication::count());
    }

    /* ---------------------------------------------------------------- */
    /* The restriction cannot be talked around */
    /* ---------------------------------------------------------------- */

    public function test_posted_schedule_fields_cannot_reopen_the_form(): void
    {
        $this->openWindow();
        $this->atTime('2026-09-20 09:00:00');

        // Every shape the restriction might be attacked in: a forged window,
        // a forged toggle, and query parameters saying the same.
        $this->post(route('public.admissions.store').'?admission_form_enabled=1&admission_form_opens_at=2020-01-01', $this->applicationPayload([
            'admission_form_enabled' => '1',
            'admission_form_opens_at' => '2020-01-01 00:00:00',
            'admission_form_closes_at' => '2099-12-31 23:59:00',
            'admission_form_state' => 'open',
        ]))->assertForbidden();

        $this->assertSame(0, AdmissionApplication::count());

        // And the settings themselves were not touched by the attempt.
        Setting::forgetCurrent();
        $this->assertSame(
            '2026-09-15 23:59:00',
            Setting::current()->admission_form_closes_at->format('Y-m-d H:i:s')
        );
    }

    public function test_the_public_page_cannot_be_reopened_by_query_parameters(): void
    {
        $this->openWindow(['admission_form_enabled' => false]);
        $this->atTime(self::DURING);

        $this->get(route('public.admissions.apply', [
            'admission_form_enabled' => 1,
            'preview' => 1,
        ]))->assertForbidden();
    }

    public function test_the_window_is_measured_in_the_application_timezone(): void
    {
        $this->openWindow();

        // The same instant expressed in UTC. The server's clock is what
        // decides, so a window saved as 08:00 local is open at 08:00 local
        // however the moment is written down.
        Carbon::setTestNow(Carbon::parse(self::OPENS_AT, config('app.timezone'))->utc());

        $this->assertTrue(Setting::current()->admissionFormIsOpen());
    }
}
