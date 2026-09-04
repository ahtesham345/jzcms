<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ResultReportLanguage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers the institution's global settings.
 *
 * Three things are being pinned down.
 *
 * That there is exactly one record: the first save creates it, every save
 * after it updates the same row, and neither the routes nor the model offer
 * a way to make a second.
 *
 * That the page is reachable only by somebody the project's existing
 * permission system says may reach it - a guest gets the login page, and a
 * signed-in user without the permission gets a 403 rather than a form.
 *
 * That the logo is handled without leaving files behind: a replacement
 * removes the file it replaced, and a save with no upload leaves the
 * existing one exactly where it was.
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // The project's own roles and permissions, seeded rather than
        // invented here: these tests must pass against the permission set
        // the application actually ships.
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
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
     * Sign in as somebody with no settings permission.
     *
     * A Teacher, which is a real role in this project rather than a user
     * with no role at all: the interesting case is a legitimate member of
     * staff who simply is not an administrator.
     */
    private function actingAsTeacher(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Teacher');

        $this->actingAs($user);

        return $user;
    }

    /**
     * A complete settings submission.
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'institution_name' => 'Jamia Zahidia',
            'institution_name_urdu' => 'جامعہ زاہدیہ',
            'address' => 'Street 4, Madina Town, Faisalabad',
            'address_urdu' => 'گلی نمبر ۴، مدینہ ٹاؤن، فیصل آباد',
            'phone_number' => '03001234567',
            'email' => 'info@jzcms.edu.pk',
            'website' => 'https://jzcms.edu.pk',
            'principal_name' => 'Qari Abdul Rahman',
            'principal_name_urdu' => 'قاری عبد الرحمن',
            'tagline' => 'Knowledge and character',
            'tagline_urdu' => 'علم و کردار',
            'default_language' => ResultReportLanguage::ENGLISH,
            'timezone' => 'Asia/Karachi',
            'date_format' => 'd M, Y',
        ], $overrides);
    }

    /* ---------------------------------------------------------------- */
    /* 1-2, 19: who may reach the page */
    /* ---------------------------------------------------------------- */

    public function test_a_guest_cannot_access_settings(): void
    {
        $this->get(route('settings.edit'))->assertRedirect(route('login'));
        $this->put(route('settings.update'), $this->payload())->assertRedirect(route('login'));

        $this->assertSame(0, Setting::count());
    }

    public function test_an_authorized_user_can_access_settings(): void
    {
        $this->actingAsAdmin();

        $this->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Institution &amp; System Settings', false)
            ->assertSee('Institution Information')
            ->assertSee('System Preferences');
    }

    public function test_an_unauthorized_user_cannot_view_or_update_settings(): void
    {
        $this->actingAsTeacher();

        $this->get(route('settings.edit'))->assertForbidden();
        $this->put(route('settings.update'), $this->payload())->assertForbidden();

        $this->assertSame(0, Setting::count());
    }

    public function test_an_unauthorized_user_cannot_change_existing_settings(): void
    {
        $this->actingAsAdmin();
        $this->put(route('settings.update'), $this->payload())->assertSessionHasNoErrors();

        $this->actingAsTeacher();
        $this->put(route('settings.update'), $this->payload([
            'institution_name' => 'Renamed By Somebody Else',
        ]))->assertForbidden();

        $this->assertSame('Jamia Zahidia', Setting::sole()->institution_name);
    }

    public function test_the_sidebar_only_offers_settings_to_an_authorized_user(): void
    {
        // The item is the one that already existed in the sidebar; this is
        // about it pointing at the page and being behind the same
        // permission as the route.
        $this->actingAsAdmin();
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('settings.edit'), false);

        $this->actingAsTeacher();
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('settings.edit'), false);
    }

    /* ---------------------------------------------------------------- */
    /* 3-6, 18: the singleton */
    /* ---------------------------------------------------------------- */

    public function test_the_first_visit_works_with_no_settings_record(): void
    {
        $this->actingAsAdmin();

        $this->assertSame(0, Setting::count());

        $this->get(route('settings.edit'))
            ->assertOk()
            // The defaults are on the form rather than an empty page.
            ->assertSee('No settings have been saved yet')
            ->assertSee(config('jzcms.name'))
            ->assertSee('Asia/Karachi')
            ->assertSee('Save Settings');

        // Rendering the form must not have written anything.
        $this->assertSame(0, Setting::count());
    }

    public function test_saving_creates_the_global_settings_record(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.update'), $this->payload())
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHas('success');

        $this->assertSame(1, Setting::count());
        $this->assertSame('Jamia Zahidia', Setting::sole()->institution_name);
    }

    public function test_a_second_save_updates_the_same_record(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.update'), $this->payload());
        $first = Setting::sole();

        $this->put(route('settings.update'), $this->payload([
            'institution_name' => 'Jamia Zahidia Campus',
            'phone_number' => '0419876543',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Setting::count());

        $second = Setting::sole();
        $this->assertSame($first->id, $second->id, 'The second save must update the first row, not add another');
        $this->assertSame('Jamia Zahidia Campus', $second->institution_name);
        $this->assertSame('0419876543', $second->phone_number);
    }

    public function test_repeated_saves_never_create_a_second_record(): void
    {
        $this->actingAsAdmin();

        // Every route the module has, several times over. There is no
        // create action and no id in either URL, so there is nothing here
        // that could file a second institution.
        for ($i = 0; $i < 4; $i++) {
            $this->get(route('settings.edit'))->assertOk();
            $this->put(route('settings.update'), $this->payload([
                'institution_name' => 'Save number '.$i,
            ]))->assertSessionHasNoErrors();
        }

        $this->assertSame(1, Setting::count());
        $this->assertSame('Save number 3', Setting::sole()->institution_name);
    }

    public function test_the_model_refuses_a_second_settings_row(): void
    {
        $this->actingAsAdmin();
        $this->put(route('settings.update'), $this->payload());

        // The guard behind the routes: even a direct create() from
        // somewhere else in the application is refused rather than
        // quietly making the settings ambiguous.
        $this->expectException(RuntimeException::class);

        Setting::create($this->payload());
    }

    public function test_settings_remain_accessible_after_reloading(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.update'), $this->payload([
            'institution_name' => 'Jamia Zahidia',
            'tagline' => 'Knowledge and character',
        ]));

        // A fresh request, the way a browser reload arrives.
        $this->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Jamia Zahidia')
            ->assertSee('Knowledge and character')
            // No longer the first visit, so the setup notice is gone and
            // the button changes.
            ->assertDontSee('No settings have been saved yet')
            ->assertSee('Save Changes');
    }

    /* ---------------------------------------------------------------- */
    /* 7-8: what gets stored */
    /* ---------------------------------------------------------------- */

    public function test_the_institution_fields_are_stored(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.update'), $this->payload())->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', [
            'institution_name' => 'Jamia Zahidia',
            'institution_name_urdu' => 'جامعہ زاہدیہ',
            'address' => 'Street 4, Madina Town, Faisalabad',
            'phone_number' => '03001234567',
            'email' => 'info@jzcms.edu.pk',
            'website' => 'https://jzcms.edu.pk',
            'principal_name' => 'Qari Abdul Rahman',
            'principal_name_urdu' => 'قاری عبد الرحمن',
            'tagline' => 'Knowledge and character',
            'default_language' => ResultReportLanguage::ENGLISH,
            'timezone' => 'Asia/Karachi',
            'date_format' => 'd M, Y',
        ]);
    }

    public function test_the_urdu_fields_are_optional(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.update'), $this->payload([
            'institution_name_urdu' => '',
            'address_urdu' => '',
            'principal_name_urdu' => '',
            'tagline' => '',
            'tagline_urdu' => '',
            'email' => '',
            'website' => '',
        ]))->assertSessionHasNoErrors();

        $settings = Setting::sole();

        // Stored as nothing rather than as an empty string, so a document
        // set to Urdu falls back to the English name instead of printing a
        // blank heading.
        $this->assertNull($settings->institution_name_urdu);
        $this->assertNull($settings->address_urdu);
        $this->assertNull($settings->principal_name_urdu);
        $this->assertNull($settings->tagline);
        $this->assertNull($settings->tagline_urdu);
        $this->assertNull($settings->email);
        $this->assertNull($settings->website);
    }

    public function test_urdu_values_are_used_when_the_language_is_urdu(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.update'), $this->payload([
            'default_language' => ResultReportLanguage::URDU,
            'principal_name_urdu' => null,
        ]));

        $settings = Setting::current();

        $this->assertSame('جامعہ زاہدیہ', $settings->name());
        // No Urdu principal was entered, so the English one is used rather
        // than an empty string.
        $this->assertSame('Qari Abdul Rahman', $settings->principal());
        // Asked for explicitly, the English side is still reachable.
        $this->assertSame('Jamia Zahidia', $settings->name(ResultReportLanguage::ENGLISH));
    }

    /* ---------------------------------------------------------------- */
    /* 9-12: the logo */
    /* ---------------------------------------------------------------- */

    public function test_a_valid_logo_upload_is_stored(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        ]))->assertSessionHasNoErrors();

        $settings = Setting::sole();

        $this->assertNotNull($settings->logo);
        $this->assertStringStartsWith('settings/', $settings->logo);
        Storage::disk('public')->assertExists($settings->logo);
        $this->assertTrue($settings->hasLogo());
        $this->assertNotNull($settings->logoUrl());
    }

    public function test_an_existing_logo_is_kept_when_none_is_uploaded(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('keep.png'),
        ]));

        $original = Setting::sole()->logo;

        $this->put(route('settings.update'), $this->payload([
            'institution_name' => 'Renamed, same logo',
        ]))->assertSessionHasNoErrors();

        $settings = Setting::sole();

        $this->assertSame($original, $settings->logo, 'The logo must survive a save that does not replace it');
        Storage::disk('public')->assertExists($original);
        $this->assertSame('Renamed, same logo', $settings->institution_name);
    }

    public function test_replacing_a_logo_deletes_the_old_file(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('first.png'),
        ]));

        $original = Setting::sole()->logo;
        Storage::disk('public')->assertExists($original);

        $this->put(route('settings.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('second.png'),
        ]))->assertSessionHasNoErrors();

        $settings = Setting::sole();

        $this->assertNotSame($original, $settings->logo);
        Storage::disk('public')->assertMissing($original);
        Storage::disk('public')->assertExists($settings->logo);

        // Nothing orphaned: one logo on file, not two.
        $this->assertCount(1, Storage::disk('public')->files('settings'));
    }

    public function test_an_invalid_logo_is_rejected(): void
    {
        $this->actingAsAdmin();

        $rejected = [
            UploadedFile::fake()->create('malware.php', 10, 'application/x-php'),
            UploadedFile::fake()->create('prospectus.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->image('too-big.png')->size(3000),
            UploadedFile::fake()->image('wrong.gif'),
        ];

        foreach ($rejected as $file) {
            $this->put(route('settings.update'), $this->payload(['logo' => $file]))
                ->assertSessionHasErrors('logo');
        }

        // A refused upload is a refused save: nothing was written and no
        // file was left on the disk.
        $this->assertSame(0, Setting::count());
        $this->assertCount(0, Storage::disk('public')->files('settings'));
    }

    public function test_a_rejected_logo_does_not_disturb_the_existing_one(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('good.png'),
        ]));

        $original = Setting::sole()->logo;

        $this->put(route('settings.update'), $this->payload([
            'logo' => UploadedFile::fake()->create('bad.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('logo');

        $this->assertSame($original, Setting::sole()->logo);
        Storage::disk('public')->assertExists($original);
    }

    /* ---------------------------------------------------------------- */
    /* 13-16: validation */
    /* ---------------------------------------------------------------- */

    public function test_the_required_fields_are_validated(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.update'), [])
            ->assertSessionHasErrors([
                'institution_name',
                'address',
                'phone_number',
                'principal_name',
                'default_language',
                'timezone',
                'date_format',
            ]);

        $this->assertSame(0, Setting::count());
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.update'), $this->payload(['email' => 'not-an-address']))
            ->assertSessionHasErrors('email');

        $this->assertSame(0, Setting::count());
    }

    public function test_an_invalid_website_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.update'), $this->payload(['website' => 'jzcms']))
            ->assertSessionHasErrors('website');

        $this->assertSame(0, Setting::count());
    }

    public function test_an_invalid_phone_number_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->put(route('settings.update'), $this->payload(['phone_number' => 'call the office']))
            ->assertSessionHasErrors('phone_number');

        // The shapes a Pakistani number is actually written in are all
        // accepted, so the rule keeps free text out without refusing
        // legitimate numbers.
        foreach (['03001234567', '+92 300 1234567', '(041) 111-222-333'] as $valid) {
            $this->put(route('settings.update'), $this->payload(['phone_number' => $valid]))
                ->assertSessionHasNoErrors();
        }
    }

    public function test_an_unsupported_language_is_rejected(): void
    {
        $this->actingAsAdmin();

        // Both a language that does not exist and the English *word*,
        // which is not how the report system names the language.
        foreach (['fr', 'English', 'Urdu', ''] as $language) {
            $this->put(route('settings.update'), $this->payload(['default_language' => $language]))
                ->assertSessionHasErrors('default_language');
        }

        $this->assertSame(0, Setting::count());
    }

    public function test_an_unsupported_date_format_is_rejected(): void
    {
        $this->actingAsAdmin();

        foreach (['Y', 'l jS \o\f F Y', 'nonsense'] as $format) {
            $this->put(route('settings.update'), $this->payload(['date_format' => $format]))
                ->assertSessionHasErrors('date_format');
        }

        $this->assertSame(0, Setting::count());
    }

    public function test_an_unsupported_timezone_is_rejected(): void
    {
        $this->actingAsAdmin();

        foreach (['Mars/Olympus', 'PKT', 'not a timezone'] as $timezone) {
            $this->put(route('settings.update'), $this->payload(['timezone' => $timezone]))
                ->assertSessionHasErrors('timezone');
        }

        $this->assertSame(0, Setting::count());
    }

    public function test_the_logo_column_cannot_be_set_from_the_request(): void
    {
        $this->actingAsAdmin();

        // logo is not fillable, so a request naming a path it did not
        // upload changes nothing rather than pointing the column at an
        // arbitrary file on the disk.
        $this->put(route('settings.update'), $this->payload([
            'logo' => 'students/somebody-elses-photo.jpg',
        ]))->assertSessionHasErrors('logo');

        $this->assertSame(0, Setting::count());
    }

    /* ---------------------------------------------------------------- */
    /* 20: query behaviour */
    /* ---------------------------------------------------------------- */

    public function test_reading_the_settings_repeatedly_costs_one_query(): void
    {
        $this->actingAsAdmin();
        $this->put(route('settings.update'), $this->payload());

        Setting::forgetCurrent();

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Five reads, the way a page and the report layer beneath it would
        // each ask for the institution independently.
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame('Jamia Zahidia', Setting::current()->institution_name);
        }

        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();
        DB::flushQueryLog();

        $this->assertSame(1, $queries, 'current() must be resolved once per request, not once per caller');
    }

    public function test_the_settings_page_does_not_grow_more_expensive_once_saved(): void
    {
        $this->actingAsAdmin();

        // Warm the permission tables, which are loaded once per process
        // and would otherwise be counted against the first measurement.
        $this->get(route('settings.edit'))->assertOk();

        $beforeSaving = $this->queriesForSettingsPage();

        $this->put(route('settings.update'), $this->payload());

        $afterSaving = $this->queriesForSettingsPage();

        // The stored row costs the same single lookup the unsaved default
        // did: nothing on this page iterates or joins.
        $this->assertSame($beforeSaving, $afterSaving);
    }

    /**
     * Count the queries one request to the settings page costs.
     *
     * The memo is dropped first. A real request boots its own container and
     * therefore always starts cold; several requests inside one test share
     * one, so without this the second measurement would be counting a
     * warm cache rather than the page.
     */
    private function queriesForSettingsPage(): int
    {
        Setting::forgetCurrent();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('settings.edit'))->assertOk();

        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();
        DB::flushQueryLog();

        return $queries;
    }
}
