<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAcademicEnrollment extends Model
{
    use HasFactory;

    /**
     * The selectable academic tracks.
     *
     * @var array<int, string>
     */
    public const ACADEMIC_TRACKS = [
        'Madrassa',
        'School',
    ];

    /**
     * The tracks that allow only one enrollment per academic session.
     *
     * The school runs a class for the academic year, so a school student
     * holds exactly one school enrollment per session. The madrassa does
     * not: a stage is finished when the student finishes it, which may be
     * months before the session ends, so a madrassa student may hold
     * several enrollments inside one session - Nazra, then Hifz, then
     * Gardan - each recorded against the session it happened in.
     *
     * The track is what tells the two apart. It is already on every
     * enrollment row and is what the promotion form promotes, so a
     * Hifz + School student keeps the school rule on their school track
     * and is free of it on their madrassa track, with no new flag needed.
     *
     * @var array<int, string>
     */
    public const SESSION_BOUND_TRACKS = [
        'School',
    ];

    /**
     * Determine whether a track allows only one enrollment per session.
     *
     * The single definition of that rule, read by the enrollment form
     * request, the promotion form request and Student::promote(), so the
     * three cannot fall out of step.
     */
    public static function trackIsSessionBound(?string $track): bool
    {
        return in_array($track, self::SESSION_BOUND_TRACKS, true);
    }

    /**
     * The selectable enrollment statuses.
     *
     * @var array<int, string>
     */
    public const STATUSES = [
        'Active',
        'Completed',
        'Left',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_id',
        'academic_session_id',
        'academic_track',
        'department_id',
        'academic_class_id',
        'section_id',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Narrow a query to the placements a result report should list.
     *
     * Two kinds are in scope, and the report depends on both:
     *
     *   - every placement that actually carries a Grand Test result in the
     *     reported terms, active or not, so a result stays visible under the
     *     placement it was marked in after the student has moved on; and
     *   - every active placement, so a student nobody has marked yet is
     *     listed as Not Entered rather than vanishing from the page. That
     *     absence is what an administrator opens the report to find.
     *
     * The second half needs one qualification. A madrassa student may hold
     * several placements inside one session, because a stage finishes when
     * the student finishes it. A student sits one Grand Test per session per
     * term, though - not one per placement - so once a term has been marked
     * against any of that student's placements for the session, their later
     * placement must not be listed as still missing it. Without that, a
     * student promoted from Nazra to Hifz in July would appear twice for
     * First Term: once as Passed under Nazra, once as Not Entered under
     * Hifz, and the report would count a missing result that does not exist.
     *
     * So an active placement is dropped when a sibling placement - same
     * student, same session, same track, different row - already carries a
     * result for one of the reported terms. The term bound is what keeps
     * the rest correct: a student marked for First Term and genuinely
     * missing Final Term is still listed as missing it, because no sibling
     * carries a Final Term result.
     *
     * Nothing is moved or rewritten. The result stays on the enrollment the
     * test was taken under; this only decides which rows the report lists.
     *
     * Shared by the on-screen report and its PDF so the two cannot drift.
     *
     * @param  array<int, string>  $terms
     */
    public function scopeForResultReport(Builder $query, array $terms): Builder
    {
        return $query->where(function (Builder $query) use ($terms) {
            $query
                ->where(function (Builder $active) use ($terms) {
                    $active->where('student_academic_enrollments.status', 'Active')
                        ->whereNotExists(function ($sibling) use ($terms) {
                            $sibling->selectRaw('1')
                                ->from('student_academic_enrollments as sibling')
                                ->join(
                                    'student_results',
                                    'student_results.student_academic_enrollment_id',
                                    '=',
                                    'sibling.id'
                                )
                                ->whereColumn('sibling.student_id', 'student_academic_enrollments.student_id')
                                ->whereColumn('sibling.academic_session_id', 'student_academic_enrollments.academic_session_id')
                                ->whereColumn('sibling.academic_track', 'student_academic_enrollments.academic_track')
                                ->whereColumn('sibling.id', '!=', 'student_academic_enrollments.id')
                                ->whereIn('student_results.term', $terms)
                                ->where('student_results.test_type', StudentResult::TEST_GRAND);
                        });
                })
                ->orWhereHas('studentResults', function ($result) use ($terms) {
                    $result->whereIn('term', $terms)
                        ->where('test_type', StudentResult::TEST_GRAND);
                });
        });
    }

    /**
     * Get the student this enrollment belongs to.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the academic session this enrollment belongs to.
     */
    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * Get the department this enrollment belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the class this enrollment belongs to.
     */
    public function academicClass()
    {
        return $this->belongsTo(AcademicClass::class);
    }

    /**
     * Get the section this enrollment belongs to, if any.
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get the results recorded against this enrollment.
     *
     * The inverse of the belongsTo on StudentResult. Only madrassa
     * enrollments ever carry one - the result module refuses to write
     * against a school enrollment - so on a school row this is simply
     * empty rather than meaningful.
     */
    public function studentResults()
    {
        return $this->hasMany(StudentResult::class);
    }

    /**
     * Determine whether this enrollment is the student's current one.
     */
    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    /**
     * Get the Tailwind badge classes for the enrollment's status.
     */
    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'Active' => 'bg-green-100 text-green-800',
            'Completed' => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
