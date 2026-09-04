<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One discipline incident recorded against one student.
 *
 * Unlike the daily record, the attendance mark and the result - all of
 * which belong to a student_academic_enrollments row - a discipline record
 * belongs to the student directly. That is deliberate. A result describes a
 * term in a class and means nothing outside it; an incident describes the
 * person, and it stays theirs after they are promoted, moved to another
 * section or enrolled in a new session. Nothing in this module rewrites an
 * old record when a student's placement changes.
 *
 * The recorder is the authenticated user who wrote the record down, set by
 * the controller from the session. It is not fillable, so no request - form,
 * hand-edited or mass-assigned - can decide who is credited with an entry.
 */
class DisciplineRecord extends Model
{
    use HasFactory;

    public const CATEGORY_BEHAVIOR = 'Behavior';

    public const CATEGORY_ATTENDANCE = 'Attendance';

    public const CATEGORY_FIGHTING = 'Fighting';

    public const CATEGORY_UNIFORM = 'Uniform';

    public const CATEGORY_ACADEMIC = 'Academic';

    public const CATEGORY_OTHER = 'Other';

    /**
     * The categories an incident may be filed under.
     *
     * Fixed. Validation reads this list, the filters read this list and the
     * column's enum repeats it, so there is one place to widen and three
     * things that widen with it.
     *
     * @var array<int, string>
     */
    public const CATEGORIES = [
        self::CATEGORY_BEHAVIOR,
        self::CATEGORY_ATTENDANCE,
        self::CATEGORY_FIGHTING,
        self::CATEGORY_UNIFORM,
        self::CATEGORY_ACADEMIC,
        self::CATEGORY_OTHER,
    ];

    public const SEVERITY_LOW = 'Low';

    public const SEVERITY_MEDIUM = 'Medium';

    public const SEVERITY_HIGH = 'High';

    /**
     * The severities an incident may carry.
     *
     * @var array<int, string>
     */
    public const SEVERITIES = [
        self::SEVERITY_LOW,
        self::SEVERITY_MEDIUM,
        self::SEVERITY_HIGH,
    ];

    /** No discipline records at all. */
    public const STATUS_GOOD = 'Good';

    /** Incidents on file, none of them High. */
    public const STATUS_HAS_WARNINGS = 'Has Warnings';

    /** At least one High severity incident. */
    public const STATUS_SERIOUS_CONCERN = 'Serious Concern';

    /**
     * The attributes that are mass assignable.
     *
     * recorded_by is deliberately absent. It is set by the controller from
     * the authenticated user, and leaving it off this list is what makes a
     * request carrying its own recorded_by change nothing.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_id',
        'date',
        'category',
        'description',
        'action_taken',
        'severity',
        'remarks',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * Keep the stored incident date a plain calendar date.
     *
     * The same guard StudentAttendance uses, for the same reason. Without
     * it the date cast hands the driver a full timestamp, which MySQL
     * truncates to a DATE and SQLite stores verbatim; the two would then
     * disagree about whether a date filter matches.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null ? null : self::normalizeDate($value),
        );
    }

    /**
     * Get the student this incident was recorded against.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the user who recorded this incident.
     *
     * Null when that member of staff has since been removed. The incident
     * stays either way: deleting a user must not delete a student's
     * history.
     */
    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /* ------------------------------------------------------------------ */
    /* Normalising what arrives in a query string */
    /* ------------------------------------------------------------------ */

    /**
     * Reduce a requested category to one this module knows, or null.
     *
     * Used by the filters, so rubbish in a query string narrows nothing
     * rather than narrowing to nothing.
     */
    public static function normalizeCategory(mixed $category): ?string
    {
        return in_array($category, self::CATEGORIES, true) ? $category : null;
    }

    /**
     * Reduce a requested severity to one this module knows, or null.
     */
    public static function normalizeSeverity(mixed $severity): ?string
    {
        return in_array($severity, self::SEVERITIES, true) ? $severity : null;
    }

    /**
     * Reduce a date filter to Y-m-d, or to null when it is not a date.
     *
     * The parsing itself is StudentAttendance's - one date reader for the
     * project rather than five that eventually disagree about what a
     * half-written date means.
     */
    public static function normalizeDate(mixed $date): ?string
    {
        return StudentAttendance::normalizeDate($date);
    }

    /* ------------------------------------------------------------------ */
    /* Display */
    /* ------------------------------------------------------------------ */

    /**
     * Get the Tailwind badge classes for this record's severity.
     *
     * Kept on the model rather than in Blade so the list, the history, the
     * detail page and the profile all colour a High the same way.
     */
    public function severityBadgeClasses(): string
    {
        return self::badgeClassesForSeverity($this->severity);
    }

    /**
     * Get the Tailwind badge classes for a severity.
     */
    public static function badgeClassesForSeverity(?string $severity): string
    {
        return match ($severity) {
            self::SEVERITY_LOW => 'bg-green-100 text-green-800',
            self::SEVERITY_MEDIUM => 'bg-amber-100 text-amber-800',
            self::SEVERITY_HIGH => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get the Tailwind badge classes for a derived discipline status.
     */
    public static function badgeClassesForStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_GOOD => 'bg-green-100 text-green-800',
            self::STATUS_HAS_WARNINGS => 'bg-amber-100 text-amber-800',
            self::STATUS_SERIOUS_CONCERN => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /* ------------------------------------------------------------------ */
    /* Queries */
    /* ------------------------------------------------------------------ */

    /**
     * Order records the way they are read: most recent incident first.
     *
     * The id breaks the tie, so two incidents on the same day come back in
     * a stable order rather than in whatever order the database chose.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInIncidentOrder($query)
    {
        return $query->orderByDesc('date')->orderByDesc('id');
    }

    /**
     * Restrict a query to one student's incidents.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /* ------------------------------------------------------------------ */
    /* Summaries */
    /* ------------------------------------------------------------------ */

    /**
     * Summarise a query's incidents in one round trip.
     *
     * Total, one count per severity and the latest incident date, all as
     * conditional sums inside a single row. The alternative - loading the
     * records and counting them in PHP - gets more expensive with every
     * incident a student accumulates, and the pages that show these numbers
     * are paginated anyway, so the rows in hand are never the whole set.
     *
     * Takes a query rather than a student so the same aggregate serves the
     * profile (all of a student's records), the history page (a filtered
     * subset) and the list.
     *
     * @param  Builder<self>  $query
     * @return array<string, mixed>
     */
    public static function summarise($query): array
    {
        // Bound values rather than interpolation. The severities are
        // constants, but a query built by concatenation is a habit worth
        // not having.
        $totals = $query->clone()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when severity = ? then 1 else 0 end) as low', [self::SEVERITY_LOW])
            ->selectRaw('sum(case when severity = ? then 1 else 0 end) as medium', [self::SEVERITY_MEDIUM])
            ->selectRaw('sum(case when severity = ? then 1 else 0 end) as high', [self::SEVERITY_HIGH])
            ->selectRaw('max(date) as latest_date')
            ->first();

        return [
            'total' => (int) ($totals->total ?? 0),
            'low' => (int) ($totals->low ?? 0),
            'medium' => (int) ($totals->medium ?? 0),
            'high' => (int) ($totals->high ?? 0),
            'latest_date' => $totals?->latest_date === null
                ? null
                : Carbon::parse($totals->latest_date),
        ];
    }

    /**
     * Summarise one student's whole discipline history.
     *
     * Every record the student has, unfiltered, plus the derived status.
     * This is what the student profile shows, and it costs one query
     * however long the history is.
     *
     * @return array<string, mixed>
     */
    public static function summaryForStudent(Student $student): array
    {
        $summary = self::summarise(self::query()->forStudent($student->id));

        $summary['status'] = self::statusFor($summary['total'], $summary['high']);

        return $summary;
    }

    /**
     * Work out a student's discipline status from their counts.
     *
     * Three answers and nothing between them: no incidents is Good, any
     * High incident is a Serious Concern, and anything else on file is Has
     * Warnings. Derived on every read and never stored, so it can never
     * disagree with the records behind it.
     */
    public static function statusFor(int $total, int $high): string
    {
        if ($total === 0) {
            return self::STATUS_GOOD;
        }

        return $high > 0 ? self::STATUS_SERIOUS_CONCERN : self::STATUS_HAS_WARNINGS;
    }
}
