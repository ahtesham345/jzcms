<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentResult;
use App\Support\ResultReportLanguage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * One student's madrassa result history.
 *
 * A viewing page only. Results are recorded and corrected on the Results
 * page, which is the module's only writer; nothing here creates, edits or
 * invents one.
 *
 * The student comes from the route and every result shown is reached
 * through that student's own madrassa enrollments, gathered once at the
 * top. A filter can therefore narrow the history but never widen it past
 * the student it belongs to, and never past the madrassa track: a
 * Hifz + School student's school enrollment is not a row this page can
 * reach, and no query parameter names an enrollment for it to reach one.
 *
 * Everything a row says about where a result was recorded - session,
 * department, class, section - is read back through that result's own
 * enrollment. The student's current placement is used for the header and
 * for nothing else, which is what keeps a First Term result showing Nazra /
 * Section A after the student has been promoted into Hifz / Section B.
 */
class StudentResultHistoryController extends Controller
{
    /**
     * How many results to show per page.
     */
    private const PER_PAGE = 20;

    /**
     * Display the student's result history.
     */
    public function index(Request $request, Student $student)
    {
        $filters = $this->filters($request);

        // The enrollments the history may draw from, narrowed by the
        // session filter here rather than in a subquery on the results.
        // Scoped to this student and to the madrassa track by
        // construction, so nothing below can reach anything else.
        $enrollments = $this->madrassaEnrollments($student, $filters);
        $enrollmentIds = $enrollments->pluck('id')->all();

        $results = $this->query($enrollmentIds, $filters)
            // Eager loaded: every row renders the placement the result was
            // recorded under. Without this the page would cost four
            // queries per result, and the count would grow with the number
            // of results rather than staying flat.
            ->with([
                'studentAcademicEnrollment.academicSession',
                'studentAcademicEnrollment.department',
                'studentAcademicEnrollment.academicClass',
                'studentAcademicEnrollment.section',
            ])
            ->inResultOrder()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('students.results', [
            // The languages the Result PDF can be printed in, so the
            // page can offer the choice before the report is opened.
            'reportLanguages' => ResultReportLanguage::NAMES,

            'student' => $student,
            'filters' => $filters,
            'results' => $results,
            'terms' => StudentResult::TERMS,
            'testType' => StudentResult::TEST_GRAND,
            // Only the sessions this student was actually enrolled in on
            // the madrassa side. Offering every session in the institution
            // would list years the student was never here for.
            'academicSessions' => $this->sessionsFor($student),
            'summary' => $this->summary($enrollmentIds, $filters, $enrollments),
            'termSummary' => $this->termSummary($enrollmentIds, $filters),
            // The student's current madrassa placement, for the header
            // only. Never used to describe a result: that is what the
            // result's own enrollment is for.
            'currentEnrollment' => $this->currentMadrassaEnrollment($student),
            'hasFilters' => $filters['term'] !== null
                || $filters['academic_session_id'] !== null
                || $filters['date_from'] !== null
                || $filters['date_to'] !== null,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* What the history may draw from */
    /* ------------------------------------------------------------------ */

    /**
     * Get the madrassa enrollments the history may draw from.
     *
     * Every one the student has ever held, not just the current placement:
     * being promoted does not start the history over, and the old rows are
     * what keep a result showing the class it was recorded in.
     *
     * The track condition is the module's boundary and is not optional. It
     * is applied here, once, so every query below inherits it rather than
     * restating it and eventually forgetting to.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, StudentAcademicEnrollment>
     */
    private function madrassaEnrollments(Student $student, array $filters)
    {
        return $student->academicEnrollments()
            ->where('academic_track', StudentResult::ACADEMIC_TRACK)
            ->when(
                $filters['academic_session_id'],
                fn ($query, $session) => $query->where('academic_session_id', $session)
            )
            ->get(['id', 'academic_session_id', 'academic_class_id', 'section_id']);
    }

    /**
     * Get the student's current madrassa placement, for the page header.
     *
     * The active one, or the most recent when the student no longer holds
     * an active madrassa enrollment. Null for a school-only student, which
     * is what the empty state reads.
     */
    private function currentMadrassaEnrollment(Student $student): ?StudentAcademicEnrollment
    {
        $enrollment = $student->activeEnrollmentForTrack(StudentResult::ACADEMIC_TRACK)
            ?? $student->academicEnrollments()
                ->where('academic_track', StudentResult::ACADEMIC_TRACK)
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();

        return $enrollment?->loadMissing([
            'academicSession',
            'department',
            'academicClass',
            'section',
        ]);
    }

    /**
     * Build the result query from the filters.
     *
     * The enrollment ids come first and are not optional: they are what
     * bounds the page to this student and to the madrassa track.
     * Everything else only narrows, and all of it combines with AND.
     *
     * @param  array<int, int>  $enrollmentIds
     * @param  array<string, mixed>  $filters
     */
    private function query(array $enrollmentIds, array $filters)
    {
        return StudentResult::query()
            ->whereIn('student_academic_enrollment_id', $enrollmentIds)
            ->when($filters['term'], fn ($query, $term) => $query->where('term', $term))
            ->when($filters['date_from'], fn ($query, $from) => $query->whereDate('result_date', '>=', $from))
            ->when($filters['date_to'], fn ($query, $to) => $query->whereDate('result_date', '<=', $to));
    }

    /* ------------------------------------------------------------------ */
    /* Summaries */
    /* ------------------------------------------------------------------ */

    /**
     * Count what the filtered history holds.
     *
     * One aggregate query rather than loading the results and counting them
     * in PHP: the cards must not get more expensive as a student
     * accumulates results, and the page is already paginated so the rows in
     * hand are not the whole set anyway.
     *
     * @param  array<int, int>  $enrollmentIds
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, StudentAcademicEnrollment>  $enrollments
     * @return array<string, mixed>
     */
    private function summary(array $enrollmentIds, array $filters, $enrollments): array
    {
        $first = StudentResult::TERM_FIRST;
        $final = StudentResult::TERM_FINAL;

        // The two term counts are conditional sums inside the same row, so
        // the whole card strip costs one query however many terms it grows
        // to hold. Bound values rather than interpolation: the terms are
        // constants, but a query built by concatenation is a habit worth
        // not having.
        $totals = $this->query($enrollmentIds, $filters)
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when term = ? then 1 else 0 end) as first_term', [$first])
            ->selectRaw('sum(case when term = ? then 1 else 0 end) as final_term', [$final])
            ->selectRaw('max(result_date) as latest_date')
            ->first();

        return [
            'total' => (int) ($totals->total ?? 0),
            'first_term' => (int) ($totals->first_term ?? 0),
            'final_term' => (int) ($totals->final_term ?? 0),
            'latest_date' => $totals?->latest_date === null
                ? null
                : Carbon::parse($totals->latest_date),
            'placements' => $enrollments->count(),
        ];
    }

    /**
     * Get the result standing behind each term's summary line.
     *
     * One row per term at most, and each term is read on its own: nothing
     * here averages the two or adds their marks together. A First Term and
     * a Final Term are separate papers and the summary says so.
     *
     * When a student has sat the same term in more than one session - which
     * is normal after a promotion - the most recent result is the one
     * shown, and narrowing by session picks out the older one. A term with
     * nothing behind it is present as null, which the view renders as
     * "Not Entered".
     *
     * @param  array<int, int>  $enrollmentIds
     * @param  array<string, mixed>  $filters
     * @return array<string, StudentResult|null>
     */
    private function termSummary(array $enrollmentIds, array $filters): array
    {
        // At most one row per term, so this is bounded however long the
        // student's history gets. The eager load is on that handful of
        // rows, not on the history.
        $latest = $this->query($enrollmentIds, $filters)
            ->with([
                'studentAcademicEnrollment.academicSession',
                'studentAcademicEnrollment.academicClass',
                'studentAcademicEnrollment.section',
            ])
            ->inResultOrder()
            ->get()
            ->unique('term')
            ->keyBy('term');

        $summary = [];

        foreach (StudentResult::TERMS as $term) {
            $summary[$term] = $latest->get($term);
        }

        return $summary;
    }

    /**
     * Get the sessions the student holds a madrassa enrollment in.
     *
     * @return \Illuminate\Support\Collection<int, AcademicSession>
     */
    private function sessionsFor(Student $student)
    {
        return $student->academicEnrollments()
            ->where('academic_track', StudentResult::ACADEMIC_TRACK)
            ->with('academicSession')
            ->get()
            ->pluck('academicSession')
            ->filter()
            ->unique('id')
            ->sortByDesc('start_date')
            ->values();
    }

    /* ------------------------------------------------------------------ */
    /* Filters */
    /* ------------------------------------------------------------------ */

    /**
     * Read the history filters off the request.
     *
     * Anything absent or unrecognised is null rather than an empty string,
     * so a hand-edited term narrows nothing instead of narrowing to
     * nothing. There is deliberately no filter naming a student or an
     * enrollment: the student is the route, and the enrollments are
     * gathered from that student alone.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'term' => StudentResult::normalizeTerm($request->input('term')),
            'academic_session_id' => $request->filled('academic_session_id')
                ? $request->input('academic_session_id')
                : null,
            'date_from' => StudentResult::normalizeResultDate($request->input('date_from')),
            'date_to' => StudentResult::normalizeResultDate($request->input('date_to')),
        ];
    }
}
