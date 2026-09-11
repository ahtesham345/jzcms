<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentResult;
use App\Models\User;
use App\Support\MadrassaStudentReport;
use App\Support\ResultReportLanguage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers the institution's branding wherever it is visible.
 *
 * The saved settings are the source of truth for the institution's own
 * identity - its name, its logo, its tagline and its address - in the
 * interface and on every generated document. What is being pinned down
 * here is that each of those places reads the same record, that each of
 * them degrades to something sensible when a piece is missing, and that
 * none of it costs a query per view.
 *
 * The report language remains ResultReportLanguage's decision. The Settings
 * default only fills the gap when a document was asked for without naming a
 * language; an explicit choice always wins, which is the case the reports
 * pages depend on.
 */
class InstitutionBrandingTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Builds the academic structure and signs in as an administrator.
        $this->buildMadrassa();
    }

    /**
     * Save the institution's settings straight to the table.
     *
     * Bypasses the form deliberately: this suite is about what the rest of
     * the application does with a saved record, and SettingsTest already
     * covers how one gets saved.
     */
    private function institution(array $overrides = []): Setting
    {
        return Setting::create(array_merge([
            'institution_name' => 'Jamia Zahidia',
            'institution_name_urdu' => 'جامعہ زاہدیہ',
            'address' => 'Street 4, Madina Town, Faisalabad',
            'address_urdu' => 'گلی نمبر چار، مدینہ ٹاؤن، فیصل آباد',
            'phone_number' => '03001234567',
            'principal_name' => 'Qari Abdul Rahman',
            'tagline' => 'Knowledge and character',
            'tagline_urdu' => 'علم و کردار',
            'default_language' => ResultReportLanguage::ENGLISH,
            'timezone' => 'Asia/Karachi',
            'date_format' => 'd M, Y',
        ], $overrides));
    }

    /**
     * Attach a logo to the settings and return its stored path.
     */
    private function withLogo(Setting $settings, string $name = 'logo.png'): string
    {
        $path = UploadedFile::fake()->image($name, 400, 200)->store('settings', 'public');

        $settings->logo = $path;
        $settings->save();

        return $path;
    }

    /**
     * A madrassa student with a result, so the report PDFs have content.
     */
    private function studentWithResult(string $name = 'Fawad Ahmed'): Student
    {
        $student = $this->student($name);
        $enrollment = $this->madrassaEnrollment($student);

        StudentResult::create([
            'student_academic_enrollment_id' => $enrollment->id,
            'term' => StudentResult::TERM_FIRST,
            'test_type' => StudentResult::TEST_GRAND,
            'total_marks' => 100,
            'obtained_marks' => 85,
            'result_date' => '2026-09-30',
        ]);

        return $student;
    }

    /**
     * Work out which engine produced a PDF.
     *
     * The two engines are the two languages: dompdf renders the English
     * documents and mPDF the Urdu ones, because dompdf performs no
     * Arabic-script shaping. Each writes its own name into the document's
     * Producer field, as UTF-16 - hence stripping the null bytes before
     * looking.
     */
    private function pdfEngine(string $pdf): string
    {
        if (! preg_match('#/Producer\s*\((.*?)\)#s', $pdf, $match)) {
            return 'unknown';
        }

        $producer = str_replace("\0", '', $match[1]);

        return match (true) {
            str_contains($producer, 'mPDF') => 'mpdf',
            str_contains($producer, 'dompdf') => 'dompdf',
            default => 'unknown',
        };
    }

    /* ---------------------------------------------------------------- */
    /* 1-5: the interface */
    /* ---------------------------------------------------------------- */

    public function test_the_header_uses_the_saved_institution_name(): void
    {
        $this->institution(['institution_name' => 'Jamia Zahidia Faisalabad']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Jamia Zahidia Faisalabad')
            // The navbar, page title and footer all read the same record,
            // so the software's own configured name is nowhere on the page.
            ->assertDontSee(config('jzcms.short_name'));
    }

    public function test_the_sidebar_uses_the_institution_name_when_there_is_no_logo(): void
    {
        $settings = $this->institution(['institution_name' => 'Jamia Zahidia Faisalabad']);

        $this->assertNull($settings->logo);

        $content = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Jamia Zahidia Faisalabad', $content);
        // No logo means no image in the sidebar's branding area at all,
        // rather than an empty or broken one.
        $this->assertStringNotContainsString('storage/settings/', $content);
    }

    public function test_the_sidebar_shows_the_logo_when_one_has_been_uploaded(): void
    {
        $settings = $this->institution();
        $path = $this->withLogo($settings);

        $content = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString(asset('storage/'.$path), $content);
        // The name goes on as the image's alt text, so the branding is
        // still readable to a screen reader and to a failed image.
        $this->assertStringContainsString('alt="Jamia Zahidia"', $content);
    }

    public function test_the_logo_url_follows_the_request_rather_than_app_url(): void
    {
        // The bug this pins down: the public disk's url is configured as
        // APP_URL.'/storage', fixed at boot. Served anywhere else - every
        // machine running `artisan serve` on port 8000 - Storage::url()
        // hands the browser an address on the wrong origin and the image
        // silently fails to load.
        config()->set('app.url', 'http://localhost');
        config()->set('filesystems.disks.public.url', 'http://localhost/storage');

        $settings = $this->institution();
        $path = $this->withLogo($settings);

        $content = $this->get('http://127.0.0.1:8000'.route('dashboard', absolute: false))
            ->assertOk()
            ->getContent();

        // The page was served from port 8000, so the logo has to be asked
        // for on port 8000.
        $this->assertStringContainsString('http://127.0.0.1:8000/storage/'.$path, $content);
        $this->assertStringNotContainsString('http://localhost/storage/'.$path, $content);
    }

    public function test_the_logo_url_resolves_through_the_public_disk_path(): void
    {
        $settings = $this->institution();
        $path = $this->withLogo($settings);

        // The stored value is a plain disk-relative path, and the URL is
        // that path under the public disk's symlink - nothing invented and
        // nothing duplicated.
        $this->assertStringStartsWith('settings/', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertSame(asset('storage/'.$path), $settings->fresh()->logoUrl());
        $this->assertStringEndsWith('/storage/'.$path, $settings->fresh()->logoUrl());
    }

    public function test_the_logo_url_is_null_when_there_is_no_logo(): void
    {
        $settings = $this->institution();

        $this->assertNull($settings->logoUrl());
        $this->assertFalse($settings->hasLogo());

        // And null again when the row names a file that is not there, so
        // nothing downstream renders an address that would 404.
        $path = $this->withLogo($settings);
        Storage::disk('public')->delete($path);

        $this->assertNull($settings->fresh()->logoUrl());
    }

    public function test_the_sidebar_bounds_the_logo_rather_than_fixing_its_size(): void
    {
        $settings = $this->institution();
        $this->withLogo($settings);

        $content = $this->get(route('dashboard'))->assertOk()->getContent();

        // max-height and max-width with object-contain: a large logo is
        // scaled down into the header and never stretched to fill it.
        $this->assertMatchesRegularExpression(
            '#<img[^>]*storage/settings/[^>]*class="[^"]*max-h-\d+[^"]*max-w-[^"]*object-contain[^"]*"#',
            $content
        );
    }

    public function test_the_sidebar_falls_back_safely_when_the_logo_file_is_missing(): void
    {
        $settings = $this->institution();
        $path = $this->withLogo($settings);

        // The row still names a logo, but the file has gone - a wiped disk,
        // or a file removed by hand.
        Storage::disk('public')->delete($path);
        Setting::forgetCurrent();

        $content = $this->get(route('dashboard'))->assertOk()->getContent();

        // The name, not a broken image.
        $this->assertStringContainsString('Jamia Zahidia', $content);
        $this->assertStringNotContainsString($path, $content);
    }

    public function test_the_configured_fallback_is_used_when_no_settings_exist(): void
    {
        $this->assertSame(0, Setting::count());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(config('jzcms.name'));
    }

    public function test_the_jzcms_fallback_is_used_when_nothing_is_configured_either(): void
    {
        // Neither a saved record nor a configured name. The interface still
        // has to say something, and JZCMS is the last resort.
        config()->set('jzcms.name', '');
        config()->set('jzcms.short_name', '');
        Setting::forgetCurrent();

        $this->assertSame('JZCMS', Setting::current()->brandName());

        $this->get(route('dashboard'))->assertOk()->assertSee('JZCMS');
    }

    public function test_a_page_still_renders_when_the_settings_table_is_unreadable(): void
    {
        // An installation whose migrations have not been run. Every layout
        // now asks for the institution's name, including the login page and
        // the public landing page, so a missing table has to read as
        // "nothing saved yet" rather than take those pages down.
        Schema::drop('settings');
        Setting::forgetCurrent();

        $this->assertSame(config('jzcms.name'), Setting::current()->brandName());

        auth()->logout();
        $this->get('/')->assertOk()->assertSee(config('jzcms.name'));
        $this->get(route('login'))->assertOk();
    }

    public function test_the_header_uses_the_urdu_name_when_urdu_is_the_default(): void
    {
        $this->institution(['default_language' => ResultReportLanguage::URDU]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('جامعہ زاہدیہ');
    }

    public function test_the_header_falls_back_to_english_when_no_urdu_name_exists(): void
    {
        $this->institution([
            'default_language' => ResultReportLanguage::URDU,
            'institution_name_urdu' => null,
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Jamia Zahidia');
    }

    /* ---------------------------------------------------------------- */
    /* 6-8, 11: PDF branding */
    /* ---------------------------------------------------------------- */

    public function test_the_english_pdf_carries_the_english_institution_information(): void
    {
        $this->institution();
        $student = $this->studentWithResult();

        $response = $this->get(route('students.results.short-pdf', [
            'student' => $student,
            'language' => ResultReportLanguage::ENGLISH,
        ]));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        // The letterhead itself, read off the rendered document rather than
        // out of the compressed PDF streams.
        $html = $this->renderShortResultHtml($student, ResultReportLanguage::ENGLISH);

        $this->assertStringContainsString('Jamia Zahidia', $html);
        $this->assertStringContainsString('Knowledge and character', $html);
        $this->assertStringContainsString('Street 4, Madina Town, Faisalabad', $html);
        $this->assertStringNotContainsString('جامعہ زاہدیہ', $html);
    }

    public function test_the_urdu_pdf_carries_the_urdu_institution_information(): void
    {
        $this->institution();
        $student = $this->studentWithResult();

        $response = $this->get(route('students.results.short-pdf', [
            'student' => $student,
            'language' => ResultReportLanguage::URDU,
        ]));

        $response->assertOk();
        $this->assertSame('mpdf', $this->pdfEngine($response->getContent()));

        $html = $this->renderShortResultHtml($student, ResultReportLanguage::URDU);

        $this->assertStringContainsString('جامعہ زاہدیہ', $html);
        $this->assertStringContainsString('علم و کردار', $html);
        $this->assertStringContainsString('گلی نمبر چار، مدینہ ٹاؤن، فیصل آباد', $html);
    }

    public function test_the_urdu_pdf_falls_back_to_english_institution_information(): void
    {
        // An institution that has entered only the English side.
        $this->institution([
            'institution_name_urdu' => null,
            'tagline_urdu' => null,
            'address_urdu' => null,
        ]);

        $student = $this->studentWithResult();

        $html = $this->renderShortResultHtml($student, ResultReportLanguage::URDU);

        // Each of the three falls back on its own, so a half-translated
        // institution prints a complete letterhead rather than a blank one.
        $this->assertStringContainsString('Jamia Zahidia', $html);
        $this->assertStringContainsString('Knowledge and character', $html);
        $this->assertStringContainsString('Street 4, Madina Town, Faisalabad', $html);

        // And the document still renders through the Urdu engine: the
        // fallback is about the institution's words, not the report's.
        $response = $this->get(route('students.results.short-pdf', [
            'student' => $student,
            'language' => ResultReportLanguage::URDU,
        ]));

        $response->assertOk();
        $this->assertSame('mpdf', $this->pdfEngine($response->getContent()));
    }

    public function test_the_logo_is_embedded_in_the_generated_pdf(): void
    {
        $settings = $this->institution();
        $student = $this->studentWithResult();

        $withoutLogo = $this->get(route('students.results.short-pdf', $student))
            ->assertOk()->getContent();

        $this->withLogo($settings);

        $withLogo = $this->get(route('students.results.short-pdf', $student))
            ->assertOk()->getContent();

        // A real image object in a real PDF, and a document that grew by
        // carrying it. Nothing was linked: dompdf runs with remote content
        // disabled, so an image that reached the page was embedded.
        $this->assertStringContainsString('/Subtype /Image', $withLogo);
        $this->assertStringNotContainsString('/Subtype /Image', $withoutLogo);
        $this->assertGreaterThan(strlen($withoutLogo), strlen($withLogo));

        // And it is carried as a data URI rather than as a path.
        $html = $this->renderShortResultHtml($student, ResultReportLanguage::ENGLISH);
        $this->assertStringContainsString('src="data:image/png;base64,', $html);
        $this->assertStringNotContainsString('src="http', $html);
    }

    public function test_a_wide_logo_is_not_distorted_in_the_pdf(): void
    {
        $settings = $this->institution();
        $student = $this->studentWithResult();

        // A banner-shaped mark, 5:1, far larger than the letterhead box.
        $path = UploadedFile::fake()->image('wide.png', 4000, 800)->store('settings', 'public');
        $settings->logo = $path;
        $settings->save();

        $pdf = $this->get(route('students.results.short-pdf', $student))->assertOk()->getContent();

        $drawn = $this->drawnImageSize($pdf);

        $this->assertNotNull($drawn, 'The logo was not drawn into the PDF at all');

        [$width, $height] = $drawn;

        // Its own shape, within a small tolerance. A fixed height plus a
        // max-width - which is what this used to be - squashed this mark
        // to the box and printed a 5:1 logo at roughly 1.3:1.
        $this->assertEqualsWithDelta(5.0, $width / $height, 0.25);

        // And still inside the letterhead box: 60mm x 22mm in points.
        $this->assertLessThanOrEqual(60 / 25.4 * 72 + 1, $width);
        $this->assertLessThanOrEqual(22 / 25.4 * 72 + 1, $height);
    }

    public function test_a_tall_logo_is_not_distorted_in_the_pdf(): void
    {
        $settings = $this->institution();
        $student = $this->studentWithResult();

        $path = UploadedFile::fake()->image('tall.png', 800, 4000)->store('settings', 'public');
        $settings->logo = $path;
        $settings->save();

        $pdf = $this->get(route('students.results.short-pdf', $student))->assertOk()->getContent();

        [$width, $height] = $this->drawnImageSize($pdf);

        $this->assertEqualsWithDelta(0.2, $width / $height, 0.05);
        $this->assertLessThanOrEqual(22 / 25.4 * 72 + 1, $height);
    }

    public function test_the_letterhead_does_not_add_pages_to_a_report(): void
    {
        $student = $this->studentWithResult();

        $bare = $this->get(route('students.results.short-pdf', $student))->assertOk()->getContent();

        $settings = $this->institution();
        $this->withLogo($settings);

        $branded = $this->get(route('students.results.short-pdf', $student))->assertOk()->getContent();

        // The A4 layout survives the header: a letterhead that pushed
        // content onto extra sheets would be worse than no letterhead.
        $this->assertSame($this->pdfPages($bare), $this->pdfPages($branded));
    }

    public function test_the_detailed_report_carries_the_institution_letterhead(): void
    {
        $this->institution();
        $student = $this->studentWithResult();

        $response = $this->get(route('students.results.pdf', $student));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('dompdf', $this->pdfEngine($response->getContent()));

        // English only, and deliberately: this document goes through
        // dompdf, which cannot shape Arabic script.
        $settings = Setting::current();
        $this->assertSame('Jamia Zahidia', $settings->brandName(ResultReportLanguage::ENGLISH));
    }

    /* ---------------------------------------------------------------- */
    /* 9-10: which language a PDF is printed in */
    /* ---------------------------------------------------------------- */

    public function test_the_settings_default_language_is_used_when_none_is_requested(): void
    {
        $this->institution(['default_language' => ResultReportLanguage::URDU]);
        $student = $this->studentWithResult();

        // No language parameter at all: the institution's own default
        // decides, which is the whole point of the preference.
        $response = $this->get(route('students.results.short-pdf', $student));

        $response->assertOk();
        $this->assertSame('mpdf', $this->pdfEngine($response->getContent()));
    }

    public function test_an_explicit_language_overrides_the_settings_default(): void
    {
        $this->institution(['default_language' => ResultReportLanguage::URDU]);
        $student = $this->studentWithResult();

        // The reports pages offer both languages side by side. A link that
        // says English must produce an English report however the
        // institution has configured itself.
        $english = $this->get(route('students.results.short-pdf', [
            'student' => $student,
            'language' => ResultReportLanguage::ENGLISH,
        ]));

        $english->assertOk();
        $this->assertSame('dompdf', $this->pdfEngine($english->getContent()));

        // And the other way round, from an English default.
        Setting::sole()->update(['default_language' => ResultReportLanguage::ENGLISH]);

        $urdu = $this->get(route('students.results.short-pdf', [
            'student' => $student,
            'language' => ResultReportLanguage::URDU,
        ]));

        $urdu->assertOk();
        $this->assertSame('mpdf', $this->pdfEngine($urdu->getContent()));
    }

    public function test_an_unrecognised_language_falls_back_to_the_settings_default(): void
    {
        $this->institution(['default_language' => ResultReportLanguage::URDU]);
        $student = $this->studentWithResult();

        foreach (['zz', 'fr', '', 'URDU'] as $bogus) {
            $response = $this->get(route('students.results.short-pdf', [
                'student' => $student,
                'language' => $bogus,
            ]));

            $response->assertOk();
            $this->assertSame(
                'mpdf',
                $this->pdfEngine($response->getContent()),
                "The language \"{$bogus}\" did not fall back to the configured default."
            );
        }

        // The rule itself, stated once. An explicit choice wins; anything
        // else takes the default; a default this class does not know still
        // ends at English.
        $this->assertSame(ResultReportLanguage::ENGLISH, ResultReportLanguage::resolve('en', 'ur'));
        $this->assertSame(ResultReportLanguage::URDU, ResultReportLanguage::resolve('zz', 'ur'));
        $this->assertSame(ResultReportLanguage::ENGLISH, ResultReportLanguage::resolve(null, 'zz'));
    }

    /* ---------------------------------------------------------------- */
    /* 12-13: changes take effect */
    /* ---------------------------------------------------------------- */

    public function test_renaming_the_institution_shows_on_the_next_request(): void
    {
        $settings = $this->institution(['institution_name' => 'Old Name']);

        $this->get(route('dashboard'))->assertOk()->assertSee('Old Name');

        $settings->update(['institution_name' => 'New Name']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('New Name')
            ->assertDontSee('Old Name');
    }

    public function test_replacing_the_logo_shows_on_the_next_request(): void
    {
        $settings = $this->institution();
        $first = $this->withLogo($settings, 'first.png');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(asset('storage/'.$first), false);

        $second = $this->withLogo($settings->fresh(), 'second.png');
        $this->assertNotSame($first, $second);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(asset('storage/'.$second), false)
            ->assertDontSee(asset('storage/'.$first), false);
    }

    /* ---------------------------------------------------------------- */
    /* 14: performance */
    /* ---------------------------------------------------------------- */

    public function test_a_page_reads_the_settings_once_however_many_places_use_them(): void
    {
        $this->institution();

        // The sidebar, the navbar, the page title and the footer all ask
        // for the institution on this one page.
        $this->get(route('dashboard'))->assertOk();

        Setting::forgetCurrent();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('dashboard'))->assertOk();

        $settingsQueries = collect(DB::getQueryLog())
            ->filter(fn ($entry) => str_contains($entry['query'], '"settings"'))
            ->count();

        DB::disableQueryLog();
        DB::flushQueryLog();

        $this->assertSame(1, $settingsQueries, 'The settings table must be read once per request, not once per view');
    }

    public function test_a_report_reads_the_settings_once_however_many_students_it_covers(): void
    {
        $this->institution();

        foreach (['Fawad Ahmed', 'Bilal Ahmad', 'Usman Tariq'] as $name) {
            $this->studentWithResult($name);
        }

        Setting::forgetCurrent();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('results.reports.pdf'))->assertOk();

        $settingsQueries = collect(DB::getQueryLog())
            ->filter(fn ($entry) => str_contains($entry['query'], '"settings"'))
            ->count();

        DB::disableQueryLog();
        DB::flushQueryLog();

        // The letterhead is drawn once and the footer repeats on every
        // page, but the record behind them is resolved once.
        $this->assertSame(1, $settingsQueries);
    }

    /* ---------------------------------------------------------------- */
    /* 15: the settings routes stay protected */
    /* ---------------------------------------------------------------- */

    public function test_guests_and_unauthorized_users_cannot_update_the_settings(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $settings = $this->institution(['institution_name' => 'Jamia Zahidia']);

        $payload = [
            'institution_name' => 'Renamed By Somebody Else',
            'address' => 'Somewhere',
            'phone_number' => '03001234567',
            'principal_name' => 'Nobody',
            'default_language' => ResultReportLanguage::ENGLISH,
            'timezone' => 'Asia/Karachi',
            'date_format' => 'd M, Y',
        ];

        // A guest.
        auth()->logout();
        $this->get(route('settings.edit'))->assertRedirect(route('login'));
        $this->put(route('settings.update'), $payload)->assertRedirect(route('login'));

        // A signed-in member of staff who is not an administrator.
        $teacher = User::factory()->create();
        $teacher->assignRole('Teacher');
        $this->actingAs($teacher);

        $this->get(route('settings.edit'))->assertForbidden();
        $this->put(route('settings.update'), $payload)->assertForbidden();

        // The branding is untouched by either attempt.
        $this->assertSame('Jamia Zahidia', $settings->fresh()->institution_name);
    }

    /* ---------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------- */

    /**
     * Render the short result report to HTML in one language.
     *
     * The same view the PDF is built from, so an assertion about the
     * letterhead is an assertion about the document - without having to
     * decompress a PDF stream to read a heading. The bytes themselves are
     * checked separately, above.
     */
    private function renderShortResultHtml(Student $student, string $language): string
    {
        $report = new MadrassaStudentReport($student, $this->session);

        return View::make('results.pdf.short-result', [
            'reports' => [$report],
            'terms' => StudentResult::TERMS,
            'termLabel' => StudentResult::TERM_FIRST,
            'testType' => StudentResult::TEST_GRAND,
            'heading' => [
                'session' => $this->session,
                'department' => null,
                'academicClass' => null,
                'section' => null,
                'student' => null,
                'search' => null,
            ],
            'capped' => false,
            'generatedAt' => now(),
            'language' => $language,
            't' => ResultReportLanguage::translator($language),
            'direction' => ResultReportLanguage::direction($language),
            'fontFamily' => ResultReportLanguage::fontFamily($language),
        ])->render();
    }

    /**
     * Count the pages in a PDF.
     */
    private function pdfPages(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    /**
     * Read the size an image was actually drawn at, in points.
     *
     * A PDF places an image by pushing a transformation matrix - "w 0 0 h x
     * y cm" - and painting a unit square through it, so the first two
     * numbers are the width and height on the page. Reading them is the
     * only way to tell a logo that was scaled from one that was squashed:
     * both produce an image object, and only the drawn size says which.
     *
     * @return array{0: float, 1: float}|null
     */
    private function drawnImageSize(string $pdf): ?array
    {
        preg_match_all('#stream\r?\n#', $pdf, $hits, PREG_OFFSET_CAPTURE);

        foreach ($hits[0] as $hit) {
            $start = $hit[1] + strlen($hit[0]);
            $end = strpos($pdf, 'endstream', $start);
            $raw = substr($pdf, $start, $end - $start);

            $inflated = @gzuncompress($raw);
            if ($inflated === false) {
                $inflated = @gzinflate($raw);
            }
            if ($inflated === false) {
                $inflated = $raw;
            }

            if (preg_match('#([\d.]+) 0 0 ([\d.]+) [\d.-]+ [\d.-]+ cm#', $inflated, $match)) {
                return [(float) $match[1], (float) $match[2]];
            }
        }

        return null;
    }
}
