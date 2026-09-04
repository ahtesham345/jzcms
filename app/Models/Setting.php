<?php

namespace App\Models;

use App\Support\PdfRenderer;
use App\Support\ResultReportLanguage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The institution's global settings.
 *
 * One row for the whole application - who the institution is, and the three
 * system preferences every future report will want a default for. It is a
 * singleton by construction rather than by convention: the Settings page has
 * no id in its URL, there is no create action and no destroy action, and the
 * hook below refuses a second row however it is reached.
 *
 * Read it through current(). That returns the stored row, or an unsaved
 * instance carrying the defaults when nothing has been saved yet, so a
 * caller never has to decide what to do about a missing record. The result
 * is memoised on the container for the life of the request, which means a
 * page that asks five times pays for one query.
 *
 * The language is stored as ResultReportLanguage's own code - 'en' or 'ur'.
 * That system already exists, already decides which PDF engine and font a
 * document gets, and already names the two languages. Storing the word
 * "English" here instead would create a second vocabulary for the same two
 * languages that the report layer could not read.
 *
 * Nothing here reads or writes config('jzcms.*'). This chunk stores the
 * preferences; wiring them into the existing views, PDFs and date output is
 * deliberately not part of it, and doing it halfway would leave two sources
 * of truth disagreeing about the institution's own name.
 */
class Setting extends Model
{
    use HasFactory;

    /**
     * Where current() memoises the resolved row.
     *
     * On the container rather than in a static property, so it lives
     * exactly as long as the request does. A static would survive into the
     * next test in the same process and hand it the previous one's row.
     */
    private const CACHE_KEY = 'jzcms.settings.current';

    /**
     * The date formats the Settings page offers.
     *
     * Keyed by the PHP format string, valued by nothing - the label is
     * rendered from the format itself against a sample date, so a format
     * can never be described as something it does not produce.
     *
     * Deliberately short. These are the shapes this project's own views
     * already print dates in, plus the two unambiguous numeric ones; a
     * longer list would be offering the institution choices no report is
     * ever going to honour differently.
     *
     * @var array<int, string>
     */
    public const DATE_FORMATS = [
        'd M, Y',
        'd-m-Y',
        'd/m/Y',
        'Y-m-d',
        'M d, Y',
        'd F Y',
    ];

    /**
     * The timezone a Pakistani institution runs on.
     */
    public const DEFAULT_TIMEZONE = 'Asia/Karachi';

    /**
     * The date format the rest of this project already prints in.
     */
    public const DEFAULT_DATE_FORMAT = 'd M, Y';

    /**
     * The URI prefix the public disk is exposed under.
     *
     * config/filesystems.php links public_path('storage') to the public
     * disk's root, so a file stored as settings/logo.png is served at
     * /storage/settings/logo.png. This names that one segment rather than
     * spelling it inline, and it is the same prefix every other stored
     * image in the project is rendered through.
     */
    private const PUBLIC_DISK_URI_PREFIX = 'storage';

    /**
     * The attributes that are mass assignable.
     *
     * logo is deliberately absent. It is a path on the public disk, written
     * by the controller after it has stored the uploaded file, and a request
     * must never be able to point the column at a file it did not upload.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'institution_name',
        'institution_name_urdu',
        'address',
        'address_urdu',
        'phone_number',
        'email',
        'website',
        'principal_name',
        'principal_name_urdu',
        'tagline',
        'tagline_urdu',
        'default_language',
        'timezone',
        'date_format',
        'admission_form_enabled',
        'admission_form_opens_at',
        'admission_form_closes_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * The two admission datetimes come back as Carbon instances in the
     * application's own timezone, which is what lets the window check below
     * compare them against now() without converting anything by hand.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'admission_form_enabled' => 'boolean',
            'admission_form_opens_at' => 'datetime',
            'admission_form_closes_at' => 'datetime',
        ];
    }

    /**
     * Refuse a second settings row, and drop the memo after every write.
     *
     * The guard is the singleton rule stated where it cannot be routed
     * around: not in the controller, which is only one of the ways a row
     * could be written, but on the model every writer has to go through.
     */
    protected static function booted(): void
    {
        static::creating(function () {
            if (static::query()->exists()) {
                throw new RuntimeException(
                    'The institution settings are a single global record. Update the existing one instead of creating another.'
                );
            }
        });

        // Both hooks, not just saved(): a row deleted by a future
        // maintenance task must not leave current() handing back a model
        // that is no longer there.
        static::saved(fn () => static::forgetCurrent());
        static::deleted(fn () => static::forgetCurrent());
    }

    /* ------------------------------------------------------------------ */
    /* Reading the singleton */
    /* ------------------------------------------------------------------ */

    /**
     * Get the institution's settings.
     *
     * Always a Setting, never null. Before anything has been saved that is
     * an unsaved instance carrying the defaults, which is what lets the
     * Settings page render the same form for the first visit and every
     * visit after it, and what lets a future report ask for the institution
     * name without first asking whether one has been entered.
     *
     * Callers that need to know the difference ask exists() on the result.
     *
     * A table that cannot be read is treated the same as one with nothing
     * in it. Every layout in the application now asks for the institution's
     * name, so this call sits underneath the login page and the public
     * landing page - and an installation whose migrations have not been run
     * yet must show those pages with the configured fallback rather than a
     * 500. Branding is not a reason for a page to fail.
     */
    public static function current(): self
    {
        if (! app()->bound(self::CACHE_KEY)) {
            app()->instance(self::CACHE_KEY, static::resolveCurrent());
        }

        return app(self::CACHE_KEY);
    }

    /**
     * Read the settings row, or fall back to the defaults.
     */
    private static function resolveCurrent(): self
    {
        try {
            return static::query()->orderBy('id')->first() ?? static::defaults();
        } catch (QueryException) {
            // No settings table, or no database at all. Narrowed to the
            // query exception on purpose: a broken model or a bad cast
            // should still surface rather than be quietly branded over.
            return static::defaults();
        }
    }

    /**
     * Drop the memoised settings.
     *
     * Called after every write. Public because a test that writes a row
     * behind the model's back has to be able to say so.
     */
    public static function forgetCurrent(): void
    {
        app()->forgetInstance(self::CACHE_KEY);
    }

    /**
     * Build the unsaved instance the first visit is offered.
     *
     * The institution's own name comes from config so a fresh install shows
     * something recognisable rather than an empty box. It is a starting
     * value for a form, not a fallback the application keeps consulting:
     * once the form is saved, the row is the only thing current() returns.
     */
    public static function defaults(): self
    {
        return new self([
            'institution_name' => config('jzcms.name'),
            'address' => '',
            'phone_number' => '',
            'principal_name' => '',
            'default_language' => ResultReportLanguage::ENGLISH,
            'timezone' => self::DEFAULT_TIMEZONE,
            'date_format' => self::DEFAULT_DATE_FORMAT,
            // Off, and with no window. An institution that has never
            // configured admissions has not decided to accept applications,
            // so the unconfigured state is closed rather than open.
            'admission_form_enabled' => false,
            'admission_form_opens_at' => null,
            'admission_form_closes_at' => null,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* The public admission window */
    /* ------------------------------------------------------------------ */

    /**
     * The public admission form is accepting applications.
     */
    public const ADMISSION_FORM_OPEN = 'open';

    /**
     * A window is configured and enabled, but has not started yet.
     */
    public const ADMISSION_FORM_NOT_YET_OPEN = 'not_yet_open';

    /**
     * A window is configured and enabled, and has ended.
     */
    public const ADMISSION_FORM_CLOSED = 'closed';

    /**
     * Switched off, or never configured.
     *
     * One state for both on purpose. From outside they are the same thing -
     * the institution is not taking online applications - and the public
     * page has no business telling a visitor whether that is because a
     * setting is off or because nobody ever filled the page in.
     */
    public const ADMISSION_FORM_UNAVAILABLE = 'unavailable';

    /**
     * Work out whether the public admission form may be used right now.
     *
     * The single authority for that question. The public page and the public
     * submission both come through here, so the two cannot come to disagree
     * and a window cannot be enforced on the form while being forgotten on
     * the POST behind it.
     *
     * Nothing about the browser is consulted: not a hidden field, not a
     * query parameter, not the visitor's clock. The answer is the stored
     * settings measured against the server's own now(), which runs in the
     * application timezone from config('app.timezone').
     *
     * The order matters. The toggle is checked first, so switching the form
     * off closes it immediately whatever window is saved - which is the
     * point of having a toggle as well as a schedule. An incomplete window
     * is then unavailable rather than unbounded: a missing opening date must
     * not read as "open since the beginning of time".
     */
    public function admissionFormState(): string
    {
        if (! $this->admission_form_enabled) {
            return self::ADMISSION_FORM_UNAVAILABLE;
        }

        if ($this->admission_form_opens_at === null || $this->admission_form_closes_at === null) {
            return self::ADMISSION_FORM_UNAVAILABLE;
        }

        $now = now();

        // Both boundaries are inclusive: a form that opens at 08:00 is open
        // at exactly 08:00, and one that closes at 23:59 is still open at
        // exactly 23:59.
        if ($now->lessThan($this->admission_form_opens_at)) {
            return self::ADMISSION_FORM_NOT_YET_OPEN;
        }

        if ($now->greaterThan($this->admission_form_closes_at)) {
            return self::ADMISSION_FORM_CLOSED;
        }

        return self::ADMISSION_FORM_OPEN;
    }

    /**
     * Determine whether the public admission form may be used right now.
     */
    public function admissionFormIsOpen(): bool
    {
        return $this->admissionFormState() === self::ADMISSION_FORM_OPEN;
    }

    /**
     * Get the configured opening moment, formatted for a public page.
     *
     * Null when no opening has been configured, so the page can say nothing
     * rather than print a date that was never set.
     */
    public function admissionFormOpensAtLabel(): ?string
    {
        return $this->formatAdmissionMoment($this->admission_form_opens_at);
    }

    /**
     * Get the configured closing moment, formatted for a public page.
     */
    public function admissionFormClosesAtLabel(): ?string
    {
        return $this->formatAdmissionMoment($this->admission_form_closes_at);
    }

    /**
     * Format one end of the window the way the public page reads it out.
     */
    private function formatAdmissionMoment(?Carbon $moment): ?string
    {
        if ($moment === null) {
            return null;
        }

        // "5 September 2026 at 8:00 AM" - written out in full, because this
        // is read by a parent on a public page rather than scanned in a
        // table, and an ambiguous 05/09 helps nobody.
        return $moment->format('j F Y').' at '.$moment->format('g:i A');
    }

    /* ------------------------------------------------------------------ */
    /* The allowed options */
    /* ------------------------------------------------------------------ */

    /**
     * Get the languages a default may be set to.
     *
     * Read from the report language system rather than restated, so the two
     * cannot come to disagree about which languages exist.
     *
     * @return array<string, string>
     */
    public static function languageOptions(): array
    {
        return ResultReportLanguage::NAMES;
    }

    /**
     * Get the timezones a setting may be set to.
     *
     * Every IANA identifier PHP knows. Not a curated list: an invented one
     * would eventually refuse a legitimate timezone, and this is the set the
     * date functions themselves accept.
     *
     * @return array<int, string>
     */
    public static function timezoneOptions(): array
    {
        return timezone_identifiers_list();
    }

    /**
     * Get the date formats a setting may be set to, with a worked example.
     *
     * The label is produced by applying the format to a sample date, so the
     * option always describes what it actually does.
     *
     * @return array<string, string>
     */
    public static function dateFormatOptions(): array
    {
        $sample = now();

        $options = [];

        foreach (self::DATE_FORMATS as $format) {
            $options[$format] = $sample->format($format);
        }

        return $options;
    }

    /* ------------------------------------------------------------------ */
    /* The logo */
    /* ------------------------------------------------------------------ */

    /**
     * Determine whether a logo is on file and the file is still there.
     *
     * Both halves matter. A path with no file behind it - a disk wiped, a
     * file removed by hand - must read as "no logo" rather than render a
     * broken image. Same check ParentGuardian makes for its photo.
     */
    public function hasLogo(): bool
    {
        return $this->logo !== null
            && Storage::disk('public')->exists($this->logo);
    }

    /**
     * Get the browser URL of the logo, or null when there is none.
     *
     * asset(), not Storage::disk('public')->url(). The two are not
     * interchangeable here and the difference is why an uploaded logo could
     * be stored correctly and still never appear.
     *
     * The public disk's url is configured in config/filesystems.php as
     * APP_URL.'/storage' - an absolute address fixed at boot. Served
     * anywhere other than APP_URL, which is every dev machine running
     * `artisan serve` on port 8000, Storage::url() hands the browser
     * http://localhost/storage/... while the page it is sitting on is
     * http://localhost:8000. The request goes to the wrong origin and the
     * image silently fails to load; the institution's name, being text,
     * was unaffected, which is exactly the shape of the symptom.
     *
     * asset() builds from the current request's own root instead, so the
     * URL follows the application to whatever host, port or subdirectory it
     * is actually being served from. It is also what every other stored
     * image in this project already uses - the student photo, the parent
     * photo, the admission photo - so the logo is now resolved the same way
     * as the images that were always working.
     *
     * The path itself is untouched: this reads the same settings.logo
     * column the upload wrote, through the same public disk.
     */
    public function logoUrl(): ?string
    {
        return $this->hasLogo()
            ? asset(self::PUBLIC_DISK_URI_PREFIX.'/'.ltrim($this->logo, '/'))
            : null;
    }

    /**
     * Get the logo as a data URI, or null when there is none.
     *
     * What the PDFs use. dompdf runs with remote content disabled and
     * chrooted, so a report cannot link to an image - it has to carry it.
     * The reading is PdfRenderer's, which is already how student photos
     * reach a report, so there is one answer in this project to "put a
     * stored image on a page" rather than two that handle a missing file
     * differently.
     *
     * mPDF accepts the same data URI, so the Urdu report needs nothing of
     * its own.
     */
    public function logoDataUri(): ?string
    {
        return PdfRenderer::photoDataUri($this->logo);
    }

    /* ------------------------------------------------------------------ */
    /* Display */
    /* ------------------------------------------------------------------ */

    /**
     * Get the institution's name in the configured default language.
     *
     * Falls back to the English name whenever the Urdu one has not been
     * entered, so a document set to Urdu prints a name rather than a blank.
     * The same fallback ResultReportLanguage applies to its own labels.
     */
    public function name(?string $language = null): string
    {
        return $this->inLanguage('institution_name', $language);
    }

    /**
     * Get the name to show as institution branding, whatever the state.
     *
     * The one thing every visible heading in the application calls, because
     * every one of them has the same three questions to answer and must not
     * answer them differently: the saved name, or - on an install where
     * nothing has been set up, or a row somehow holding a blank - the
     * project's own configured name, and JZCMS behind that.
     *
     * name() alone is very nearly enough, because defaults() seeds the
     * unsaved instance from config. This exists for the cases defaults()
     * cannot cover: a configured name that is itself empty, and an Urdu
     * heading whose English twin is blank too.
     */
    public function brandName(?string $language = null): string
    {
        foreach ([$this->name($language), config('jzcms.name'), config('jzcms.short_name')] as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return 'JZCMS';
    }

    /**
     * Get the institution's address in the configured default language.
     */
    public function addressLine(?string $language = null): string
    {
        return $this->inLanguage('address', $language);
    }

    /**
     * Get the principal's name in the configured default language.
     */
    public function principal(?string $language = null): string
    {
        return $this->inLanguage('principal_name', $language);
    }

    /**
     * Get the tagline in the configured default language.
     */
    public function taglineLine(?string $language = null): string
    {
        return $this->inLanguage('tagline', $language);
    }

    /**
     * Read one of the paired columns in a language.
     *
     * The Urdu column when Urdu is asked for and something is stored in it,
     * the English one otherwise. Kept in one place so all four pairs fall
     * back the same way.
     */
    private function inLanguage(string $column, ?string $language): string
    {
        $language = ResultReportLanguage::normalize($language ?? $this->default_language);

        if (ResultReportLanguage::isRtl($language)) {
            $urdu = trim((string) $this->{$column.'_urdu'});

            if ($urdu !== '') {
                return $urdu;
            }
        }

        return (string) $this->{$column};
    }
}
