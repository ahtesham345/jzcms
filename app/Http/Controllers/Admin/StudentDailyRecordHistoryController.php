<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\MadrassaDailyRecord;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One student's madrassa daily academic records.
 *
 * A viewing page only. Records are created and corrected on the Hifz &
 * Quran roster, which is the module's only writer; nothing here creates,
 * edits or invents one, and loading a date has never written a row.
 *
 * The student comes from the route and every record shown is reached
 * through that student's own madrassa enrollments. A filter can therefore
 * narrow the history but never widen it past the student it belongs to,
 * and never past the madrassa track: a Hifz + School student's school
 * enrollment is not a row this page can reach.
 *
 * There is no search box. The student is already known - that is the whole
 * premise of the page.
 */
class StudentDailyRecordHistoryController extends Controller
{
    /**
     * How many records to show per page.
     */
    private const PER_PAGE = 20;

    /**
     * Display the student's daily record history.
     */
    public function index(Request $request, Student $student)
    {
        $filters = $this->filters($request);

        // The enrollments the history may draw from, narrowed by the
        // session filter here rather than in a subquery on the records.
        // Scoped to this student and to the madrassa track by
        // construction, so nothing below can reach anything else.
        $enrollments = $this->madrassaEnrollments($student, $filters);

        $records = $this->query($enrollments->pluck('id')->all(), $filters)
            // Eager loaded: every row renders the placement it was recorded
            // under and the teacher who took the lesson.
            ->with([
                'studentAcademicEnrollment.academicSession',
                'studentAcademicEnrollment.department',
                'studentAcademicEnrollment.academicClass',
                'studentAcademicEnrollment.section',
                'teacher',
            ])
            ->inDailyOrder()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('students.hifz', [
            'student' => $student,
            'filters' => $filters,
            'records' => $records,
            'recordTypes' => MadrassaDailyRecord::RECORD_TYPES,
            // Only the sessions this student was actually enrolled in on
            // the madrassa side. Offering every session in the institution
            // would list years the student was never here for.
            'academicSessions' => $this->sessionsFor($student),
            'summary' => $this->summary($student, $enrollments, $filters),
            'hasFilters' => $filters['date_from'] !== null
                || $filters['date_to'] !== null
                || $filters['record_type'] !== null
                || $filters['academic_session_id'] !== null,
        ]);
    }

    /**
     * Get the madrassa enrollments the history may draw from.
     *
     * Every one the student has ever held, not just the current placement:
     * being promoted does not start the history over, and the old rows are
     * what keep a record showing the class it was made in.
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Collection<int, StudentAcademicEnrollment>
     */
    private function madrassaEnrollments(Student $student, array $filters)
    {
        return $student->academicEnrollments()
            ->where('academic_track', MadrassaDailyRecord::ACADEMIC_TRACK)
            ->when(
                $filters['academic_session_id'],
                fn ($query, $session) => $query->where('academic_session_id', $session)
            )
            ->get(['id', 'academic_session_id', 'academic_class_id', 'section_id']);
    }

    /**
     * Build the record query from the filters.
     *
     * The enrollment ids come first and are not optional: they are what
     * bounds the page to this student. Everything else only narrows.
     *
     * @param  array<int, int>  $enrollmentIds
     * @param  array<string, mixed>  $filters
     */
    private function query(array $enrollmentIds, array $filters)
    {
        return MadrassaDailyRecord::query()
            ->whereIn('student_academic_enrollment_id', $enrollmentIds)
            ->when($filters['date_from'], fn ($query, $from) => $query->whereDate('record_date', '>=', $from))
            ->when($filters['date_to'], fn ($query, $to) => $query->whereDate('record_date', '<=', $to))
            ->when($filters['record_type'], fn ($query, $type) => $query->where('record_type', $type));
    }

    /**
     * Count what the filtered history holds.
     *
     * The programme is the student's current madrassa one, which is a fact
     * about the student rather than about the range being viewed. The
     * counts are about the range.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, StudentAcademicEnrollment>  $enrollments
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function summary(Student $student, $enrollments, array $filters): array
    {
        $totals = $this->query($enrollments->pluck('id')->all(), $filters)
            ->selectRaw('count(*) as total, min(record_date) as first_date, max(record_date) as last_date')
            ->first();

        return [
            'total' => (int) ($totals->total ?? 0),
            'first_date' => $totals?->first_date === null ? null : Carbon::parse($totals->first_date),
            'last_date' => $totals?->last_date === null ? null : Carbon::parse($totals->last_date),
            'record_type' => MadrassaDailyRecord::recordTypeForStudentType($student->student_type),
            'placements' => $enrollments->count(),
        ];
    }

    /**
     * Get the sessions the student holds a madrassa enrollment in.
     *
     * @return Collection<int, AcademicSession>
     */
    private function sessionsFor(Student $student)
    {
        return $student->academicEnrollments()
            ->where('academic_track', MadrassaDailyRecord::ACADEMIC_TRACK)
            ->with('academicSession')
            ->get()
            ->pluck('academicSession')
            ->filter()
            ->unique('id')
            ->sortByDesc('start_date')
            ->values();
    }

    /**
     * Read the history filters off the request.
     *
     * Anything absent or unrecognised is null rather than an empty string,
     * so a hand-edited record type narrows nothing instead of narrowing to
     * nothing.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $recordType = $request->input('record_type');

        return [
            'date_from' => $this->cleanedDate($request->input('date_from')),
            'date_to' => $this->cleanedDate($request->input('date_to')),
            'record_type' => in_array($recordType, MadrassaDailyRecord::RECORD_TYPES, true) ? $recordType : null,
            'academic_session_id' => $request->filled('academic_session_id')
                ? $request->input('academic_session_id')
                : null,
        ];
    }

    /**
     * Reduce a date filter to Y-m-d, or to null when it is not a date.
     */
    private function cleanedDate(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== ''
            ? MadrassaDailyRecord::normalizeRecordDate($value)
            : null;
    }
}
