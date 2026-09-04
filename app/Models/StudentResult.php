<?php

namespace App\Models;

use App\Support\GradeScale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One madrassa student's Grand Test result for one term.
 *
 * The madrassa runs two terms - First and Final - and each one has a Grand
 * Test. A result says what the student scored on it.
 *
 * Like the daily record and the attendance mark, a result belongs to a
 * student_academic_enrollments row rather than to a student. That is what
 * keeps it historically correct: the session, department, class and section
 * shown against a result are the ones the student held when it was
 * recorded, not the ones they hold now. It is also what keeps a
 * Hifz + School student honest - they hold a madrassa and a school
 * enrollment at once, and only the madrassa one may carry a result.
 *
 * The percentage and the grade are never accepted from a request. Neither
 * is fillable, and both are recomputed from the marks on every save by the
 * hook below, so there is no path - form, hand-edited request or
 * mass-assignment - by which a browser can decide what a paper scored.
 */
class StudentResult extends Model
{
    use HasFactory;

    public const TERM_FIRST = 'First Term';

    public const TERM_FINAL = 'Final Term';

    /**
     * The terms a result may be recorded for.
     *
     * @var array<int, string>
     */
    public const TERMS = [
        self::TERM_FIRST,
        self::TERM_FINAL,
    ];

    public const TEST_GRAND = 'Grand Test';

    /**
     * The tests this module records.
     *
     * One member for now. Kept as a list rather than as a bare constant
     * because it is the thing a later chunk widens, and every rule that
     * reads it - validation, the filters, the form - then widens with it.
     *
     * @var array<int, string>
     */
    public const TEST_TYPES = [
        self::TEST_GRAND,
    ];

    /**
     * The only track that has results in this module.
     */
    public const ACADEMIC_TRACK = 'Madrassa';

    /**
     * The attributes that are mass assignable.
     *
     * percentage and grade are deliberately absent: they are derived, and
     * leaving them off this list is the first of the two guards that keep a
     * request from setting them. The saving hook below is the second.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_academic_enrollment_id',
        'term',
        'test_type',
        'total_marks',
        'obtained_marks',
        'result_date',
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
            'result_date' => 'date',
            'total_marks' => 'decimal:2',
            'obtained_marks' => 'decimal:2',
            'percentage' => 'decimal:2',
        ];
    }

    /**
     * Recompute the derived columns before every write.
     *
     * Both are computed here rather than in the controller so that every
     * writer - the form, a seeder, a future import - produces the same
     * numbers. Setting percentage or grade directly has no effect: whatever
     * was set is overwritten from the marks a moment later.
     */
    protected static function booted(): void
    {
        static::saving(function (self $result) {
            $percentage = self::calculatePercentage(
                $result->total_marks,
                $result->obtained_marks
            );

            $result->percentage = $percentage;
            $result->grade = GradeScale::forPercentage($percentage);
        });
    }

    /**
     * Get the enrollment this result was recorded against.
     */
    public function studentAcademicEnrollment()
    {
        return $this->belongsTo(StudentAcademicEnrollment::class);
    }

    /**
     * Get the student, through the enrollment.
     *
     * Convenience for a single result. Listings eager load
     * studentAcademicEnrollment.student instead, which reaches the same row
     * without a second query per result.
     */
    public function student()
    {
        return $this->hasOneThrough(
            Student::class,
            StudentAcademicEnrollment::class,
            'id',
            'id',
            'student_academic_enrollment_id',
            'student_id'
        );
    }

    /* ------------------------------------------------------------------ */
    /* The arithmetic */
    /* ------------------------------------------------------------------ */

    /**
     * Work out a percentage from the marks.
     *
     * Null when the total is missing, zero or negative, or when the
     * obtained marks are missing. All three are refused by validation
     * before they reach a save; returning null rather than throwing means a
     * row written straight to the table by a seeder or a test cannot divide
     * by zero either.
     *
     * Rounded to two decimals, which is also the column's precision, so the
     * value that gets graded is the value that gets stored.
     */
    public static function calculatePercentage(mixed $totalMarks, mixed $obtainedMarks): ?float
    {
        if (! is_numeric($totalMarks) || ! is_numeric($obtainedMarks)) {
            return null;
        }

        $total = (float) $totalMarks;

        if ($total <= 0) {
            return null;
        }

        return round(((float) $obtainedMarks / $total) * 100, 2);
    }

    /**
     * Work out the grade a percentage earns.
     *
     * Delegated rather than restated: the boundaries live in one place so
     * the institution can change them without this module knowing.
     */
    public static function gradeForPercentage(?float $percentage): ?string
    {
        return GradeScale::forPercentage($percentage);
    }

    /**
     * Determine whether an enrollment may carry a result at all.
     *
     * The single question every write path asks. A school enrollment is
     * refused whatever its student's programme says, which is what keeps
     * the school side of a Hifz + School student out of this module.
     */
    public static function isResultableEnrollment(?StudentAcademicEnrollment $enrollment): bool
    {
        return $enrollment !== null
            && $enrollment->academic_track === self::ACADEMIC_TRACK;
    }

    /**
     * Reduce a requested term to one this module knows, or null.
     *
     * Used by the filters, so rubbish in a query string narrows to nothing
     * rather than reaching a query.
     */
    public static function normalizeTerm(mixed $term): ?string
    {
        return in_array($term, self::TERMS, true) ? $term : null;
    }

    /**
     * Reduce a date filter to Y-m-d, or to null when it is not a date.
     *
     * Used by the history filters, so rubbish in a query string narrows
     * nothing rather than throwing. The parsing itself is StudentAttendance's
     * - one date reader for the project rather than four that eventually
     * disagree about what "2026-9-3" means.
     */
    public static function normalizeResultDate(mixed $date): ?string
    {
        return StudentAttendance::normalizeDate($date);
    }

    /**
     * Reduce a requested test type to one this module knows, or null.
     */
    public static function normalizeTestType(mixed $testType): ?string
    {
        return in_array($testType, self::TEST_TYPES, true) ? $testType : null;
    }

    /* ------------------------------------------------------------------ */
    /* Display */
    /* ------------------------------------------------------------------ */

    /**
     * Get the percentage as it is shown, or N/A when there is none.
     */
    public function formattedPercentage(): string
    {
        return $this->percentage === null
            ? 'N/A'
            : number_format((float) $this->percentage, 2).'%';
    }

    /**
     * Get the marks the way a mark sheet reads them: obtained over total.
     */
    public function formattedMarks(): string
    {
        return number_format((float) $this->obtained_marks, 2)
            .' / '.number_format((float) $this->total_marks, 2);
    }

    /**
     * Get the Tailwind badge classes for the grade.
     *
     * Keyed by the first letter, so a configured ladder using B+ and C+ is
     * coloured the same as one using B and C, and a grade the match does
     * not recognise falls back to neutral grey rather than to nothing.
     */
    public function gradeBadgeClasses(): string
    {
        return match (mb_substr((string) $this->grade, 0, 1)) {
            'A' => 'bg-green-100 text-green-800',
            'B' => 'bg-emerald-100 text-emerald-800',
            'C' => 'bg-blue-100 text-blue-800',
            'D' => 'bg-amber-100 text-amber-800',
            'F' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /* ------------------------------------------------------------------ */
    /* Queries */
    /* ------------------------------------------------------------------ */

    /**
     * Order results the way they are read: most recent first.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInResultOrder($query)
    {
        return $query->orderByDesc('result_date')->orderByDesc('id');
    }

    /**
     * Restrict a query to one student's madrassa results.
     *
     * The track is part of the condition, not an assumption. Results can
     * only be written against a madrassa enrollment in the first place, but
     * a student's results must be provably free of the school side rather
     * than free of it by luck.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForStudent($query, int $studentId)
    {
        return $query->whereHas(
            'studentAcademicEnrollment',
            fn ($enrollment) => $enrollment
                ->where('student_id', $studentId)
                ->where('academic_track', self::ACADEMIC_TRACK)
        );
    }

    /**
     * Summarise a student's Grand Test results for their profile.
     *
     * Null when the student has never held a madrassa enrollment, which is
     * what decides whether the profile shows the section at all: a
     * school-only student gets nothing rather than an empty panel.
     *
     * The terms are read from the student's current madrassa placement, or
     * from their most recent one when they no longer hold an active
     * enrollment, so the profile shows one session's two terms rather than
     * a mixture of sessions. The full history is a later chunk's job.
     *
     * @return array<string, mixed>|null
     */
    public static function summaryForStudent(Student $student): ?array
    {
        $enrollment = $student->activeEnrollmentForTrack(self::ACADEMIC_TRACK)
            ?? $student->academicEnrollments()
                ->where('academic_track', self::ACADEMIC_TRACK)
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();

        if ($enrollment === null) {
            return null;
        }

        $enrollment->loadMissing(['academicSession', 'department', 'academicClass', 'section']);

        // One query for both terms rather than one each.
        $results = self::where('student_academic_enrollment_id', $enrollment->id)
            ->where('test_type', self::TEST_GRAND)
            ->get()
            ->keyBy('term');

        $terms = [];

        foreach (self::TERMS as $term) {
            // Present as a key even when there is no result, so the view
            // renders "Not Entered" for the missing term rather than
            // leaving the row out and making the reader work out which one
            // is gone.
            $terms[$term] = $results->get($term);
        }

        return [
            'enrollment' => $enrollment,
            'terms' => $terms,
            'test_type' => self::TEST_GRAND,
            'total' => $results->count(),
        ];
    }
}
