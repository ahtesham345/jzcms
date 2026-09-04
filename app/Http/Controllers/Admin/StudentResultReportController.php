<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentResult;
use App\Support\GradeScale;
use App\Support\ResultReportLanguage;
use Illuminate\Http\Request;

/**
 * The madrassa result report.
 *
 * Read only. Nothing here writes a result, and nothing here invents one:
 * the page reports what the Results module has recorded, and says plainly
 * where nothing has been recorded yet.
 *
 * The report is built over enrollments rather than over results, which is
 * what lets a student who has not been marked appear at all. A student with
 * no result is the whole reason an administrator opens this page - they are
 * looking for who is still missing - so the query can never be a join that
 * drops them.
 *
 * Which enrollments are in scope is the one design decision worth stating.
 * Two kinds are included:
 *
 *   - every active madrassa enrollment matching the filters, so an unmarked
 *     student is listed as Not Entered rather than being absent; and
 *   - every madrassa enrollment that actually carries a result in the
 *     filtered range, active or not, so a result recorded before a
 *     promotion keeps appearing under the placement it was recorded in.
 *
 * Because student_academic_enrollments is unique on student + session +
 * track, selecting a session - which is the default - gives exactly one
 * enrollment per student, and therefore one row per student per term. A
 * student only occupies two placements at once across sessions, and with
 * All Sessions chosen those genuinely are two different placements and are
 * shown as such.
 *
 * Every number on the page is aggregated in SQL over the whole filtered
 * set, not over the page in hand: a summary that only described 25 students
 * would be worse than no summary.
 */
class StudentResultReportController extends Controller
{
    /**
     * How many students to show per page.
     */
    private const PER_PAGE = 25;

    /**
     * The term filter value that reports both terms.
     */
    public const ALL_TERMS = 'All Terms';

    /**
     * Display the result report.
     */
    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $terms = $this->termsInScope($filters['term']);

        // Built once and reused: the rows are one page of it, every
        // aggregate below runs over all of it.
        $scope = $this->scopeQuery($filters, $terms);

        $enrollments = (clone $scope)
            // Eager loaded: every row renders the student and the placement
            // the result was recorded under. Without this the page would
            // cost five queries per row.
            ->with(['student', 'academicSession', 'department', 'academicClass', 'section'])
            ->join('students', 'students.id', '=', 'student_academic_enrollments.student_id')
            // Roll number first, as the paper mark sheet is kept, then the
            // two columns that are always present so the order is total.
            ->orderBy('students.roll_number')
            ->orderBy('students.registration_number')
            ->orderBy('students.full_name')
            ->select('student_academic_enrollments.*')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('results.reports', [
            // The languages the Result PDF can be printed in, so the
            // page can offer the choice before the report is opened.
            'reportLanguages' => ResultReportLanguage::NAMES,

            'rows' => $this->rows($enrollments, $terms),
            'enrollments' => $enrollments,
            'filters' => $filters,
            'terms' => $terms,
            'termOptions' => array_merge(StudentResult::TERMS, [self::ALL_TERMS]),
            'testType' => StudentResult::TEST_GRAND,
            'summary' => $this->summary($scope, $filters, $terms),
            'gradeBreakdown' => $this->gradeBreakdown($scope, $filters, $terms),
            ...$this->filterOptions(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* What the report covers */
    /* ------------------------------------------------------------------ */

    /**
     * Build the enrollment query the whole report is drawn from.
     *
     * The track condition is the module's boundary and is applied here,
     * once, before anything else. It is not read from the request: there is
     * no track filter on this page, and a query string naming one would be
     * ignored because nothing looks for it.
     *
     * Everything else only narrows, and all of it combines with AND.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $terms
     */
    private function scopeQuery(array $filters, array $terms)
    {
        return StudentAcademicEnrollment::query()
            // Qualified throughout: students is joined for ordering and for
            // the search, and carries columns of the same name.
            ->where('student_academic_enrollments.academic_track', StudentResult::ACADEMIC_TRACK)
            ->where(function ($query) use ($terms) {
                // Active placements, so a student nobody has marked yet is
                // listed as Not Entered rather than vanishing from the
                // page. This is the half an inner join on results would
                // destroy.
                $query->where('student_academic_enrollments.status', 'Active')
                    // Plus any placement that actually carries a result in
                    // range, active or not. This is what keeps a First Term
                    // result visible under Nazra after the student has been
                    // promoted out of it.
                    ->orWhereHas('studentResults', function ($result) use ($terms) {
                        $result->whereIn('term', $terms)
                            ->where('test_type', StudentResult::TEST_GRAND);
                    });
            })
            ->when($filters['academic_session_id'], fn ($query, $value) => $query->where('student_academic_enrollments.academic_session_id', $value))
            ->when($filters['department_id'], fn ($query, $value) => $query->where('student_academic_enrollments.department_id', $value))
            ->when($filters['academic_class_id'], fn ($query, $value) => $query->where('student_academic_enrollments.academic_class_id', $value))
            ->when($filters['section_id'], fn ($query, $value) => $query->where('student_academic_enrollments.section_id', $value))
            ->when($filters['search'], function ($query, $search) {
                // Resolved against the student the enrollment belongs to,
                // as a condition on the enrollment rather than a widening
                // of it: a search can only ever narrow what the track
                // condition above already fixed.
                $query->whereHas('student', function ($student) use ($search) {
                    $student->where('full_name', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%")
                        ->orWhere('roll_number', 'like', "%{$search}%");
                });
            });
    }

    /**
     * Get the terms the report covers.
     *
     * Both when All Terms is chosen, and they stay separate throughout:
     * nothing on this page averages a First Term against a Final Term or
     * adds their marks together.
     *
     * @return array<int, string>
     */
    private function termsInScope(string $term): array
    {
        return $term === self::ALL_TERMS ? StudentResult::TERMS : [$term];
    }

    /* ------------------------------------------------------------------ */
    /* The rows */
    /* ------------------------------------------------------------------ */

    /**
     * Pair each enrollment on this page with its result for each term.
     *
     * One query for the whole page rather than one per row, and one row per
     * enrollment per term in scope. A term with nothing recorded against it
     * is present as null, which the view renders as Not Entered - that
     * absence is the report's most useful output, so it is never left out.
     *
     * @param  array<int, string>  $terms
     * @return array<int, array<string, mixed>>
     */
    private function rows($enrollments, array $terms): array
    {
        $results = StudentResult::query()
            ->whereIn('student_academic_enrollment_id', $enrollments->pluck('id')->all())
            ->whereIn('term', $terms)
            ->where('test_type', StudentResult::TEST_GRAND)
            ->get()
            // Keyed by placement and term together, which is exactly what
            // the unique index guarantees is at most one row.
            ->keyBy(fn (StudentResult $result) => $result->student_academic_enrollment_id.'|'.$result->term);

        $rows = [];

        foreach ($enrollments as $enrollment) {
            foreach ($terms as $term) {
                $result = $results->get($enrollment->id.'|'.$term);

                $rows[] = [
                    'enrollment' => $enrollment,
                    'student' => $enrollment->student,
                    'term' => $term,
                    'result' => $result,
                    // Worked out once here rather than in the view, from
                    // the stored grade and the shared ladder. Three states,
                    // never two: an unmarked paper is not a failed one.
                    'status' => $this->statusFor($result),
                    // True on the first term of each student's block, so
                    // the table can keep the two rows of an All Terms
                    // student visually together.
                    'first_of_group' => $term === $terms[0],
                ];
            }
        }

        return $rows;
    }

    /**
     * Work out a row's status.
     *
     * Passed, Failed or Not Entered. The pass/fail line is GradeScale's,
     * read from the configured ladder, so this module never states a second
     * opinion about which grades pass.
     */
    private function statusFor(?StudentResult $result): string
    {
        if ($result === null) {
            return 'Not Entered';
        }

        return GradeScale::isPassing($result->grade) ? 'Passed' : 'Failed';
    }

    /* ------------------------------------------------------------------ */
    /* The summary */
    /* ------------------------------------------------------------------ */

    /**
     * Count what the filtered report holds.
     *
     * Every figure comes from SQL over the whole filtered set. Nothing here
     * loads results into PHP to add them up, so the cards cost the same
     * whether the report covers twelve students or twelve hundred.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $terms
     * @return array<string, mixed>
     */
    private function summary($scope, array $filters, array $terms): array
    {
        $failing = GradeScale::failingGrade();

        $totals = $this->resultsInScope($scope, $terms)
            ->selectRaw('count(*) as entered')
            ->selectRaw('sum(case when grade = ? then 1 else 0 end) as failed', [$failing])
            ->selectRaw('sum(case when grade <> ? then 1 else 0 end) as passed', [$failing])
            // The two sums the average is built from. Kept as sums rather
            // than as an average of percentages: a paper out of 500 and a
            // paper out of 50 do not carry the same weight, and averaging
            // their percentages would pretend they did.
            ->selectRaw('sum(obtained_marks) as obtained_marks')
            ->selectRaw('sum(total_marks) as total_marks')
            ->first();

        $entered = (int) ($totals->entered ?? 0);
        $placements = (clone $scope)->count();
        $students = (clone $scope)->distinct()->count('student_academic_enrollments.student_id');

        // One cell per placement per term is what a complete report would
        // hold; whatever is missing from that has not been entered.
        $expected = $placements * count($terms);

        $obtained = $totals?->obtained_marks;
        $total = $totals?->total_marks;

        return [
            'students' => $students,
            'entered' => $entered,
            'not_entered' => max(0, $expected - $entered),
            'passed' => (int) ($totals->passed ?? 0),
            'failed' => (int) ($totals->failed ?? 0),
            // Total obtained over total possible, exactly as a mark sheet
            // is totalled. Null when nothing has been marked, which the
            // view shows as N/A rather than as 0%.
            'average_percentage' => StudentResult::calculatePercentage($total, $obtained),
            'terms' => $terms,
        ];
    }

    /**
     * Count the results in the report by grade.
     *
     * One grouped query, and the grades come from the configured ladder so
     * a renamed or re-cut band is reported under its own name rather than
     * under a list written down here. Not Entered is carried alongside them
     * because "nobody has marked this" is as much a fact about a class as
     * any grade is.
     *
     * @param  array<int, string>  $terms
     * @return array<string, int>
     */
    private function gradeBreakdown($scope, array $filters, array $terms): array
    {
        $counts = $this->resultsInScope($scope, $terms)
            ->selectRaw('grade, count(*) as total')
            ->groupBy('grade')
            ->pluck('total', 'grade');

        $breakdown = [];

        foreach (array_keys(GradeScale::ladder()) as $grade) {
            $breakdown[$grade] = (int) ($counts[$grade] ?? 0);
        }

        // A stored grade the current ladder no longer knows about still has
        // to be reported: dropping it would make the breakdown disagree
        // with the Results Entered card.
        foreach ($counts as $grade => $total) {
            if (! array_key_exists($grade, $breakdown)) {
                $breakdown[$grade] = (int) $total;
            }
        }

        $placements = (clone $scope)->count();

        $breakdown['Not Entered'] = max(
            0,
            $placements * count($terms) - (int) $counts->sum()
        );

        return $breakdown;
    }

    /**
     * Build the result query the aggregates run over.
     *
     * Bounded by the report's own enrollment scope, passed as a subquery so
     * the ids never travel through PHP. That bound is what makes every
     * aggregate on the page Madrassa-only and filter-respecting by
     * construction rather than by remembering to restate the conditions.
     *
     * @param  array<int, string>  $terms
     */
    private function resultsInScope($scope, array $terms)
    {
        return StudentResult::query()
            ->whereIn(
                'student_academic_enrollment_id',
                (clone $scope)->select('student_academic_enrollments.id')
            )
            ->whereIn('term', $terms)
            ->where('test_type', StudentResult::TEST_GRAND);
    }

    /* ------------------------------------------------------------------ */
    /* Filters */
    /* ------------------------------------------------------------------ */

    /**
     * Read the report filters off the request.
     *
     * The term always has a value - First Term unless another is chosen -
     * because the report has to be about something. An unrecognised term
     * falls back to that default rather than reaching a query.
     *
     * The session defaults to the institution's current one, so the page
     * opens on the year being taught rather than on every year at once. It
     * is a default, not a floor: All Sessions is still selectable.
     *
     * There is deliberately no track filter. The report is Madrassa-only by
     * construction and a query string cannot say otherwise.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $term = $request->input('term');

        return [
            'term' => $term === self::ALL_TERMS
                ? self::ALL_TERMS
                : (StudentResult::normalizeTerm($term) ?? StudentResult::TERM_FIRST),
            'academic_session_id' => $this->sessionFilter($request),
            'department_id' => $request->filled('department_id') ? $request->input('department_id') : null,
            'academic_class_id' => $request->filled('academic_class_id') ? $request->input('academic_class_id') : null,
            'section_id' => $request->filled('section_id') ? $request->input('section_id') : null,
            'search' => $request->filled('search') ? trim((string) $request->input('search')) : null,
        ];
    }

    /**
     * Work out which session the report is for.
     *
     * The current session on a first visit. "All Sessions" is a deliberate
     * choice rather than an absent parameter, so it arrives as an empty
     * string and is honoured as null - otherwise clearing the session would
     * silently snap back to the current one.
     */
    private function sessionFilter(Request $request): mixed
    {
        if ($request->has('academic_session_id')) {
            return $request->filled('academic_session_id')
                ? $request->input('academic_session_id')
                : null;
        }

        return AcademicSession::where('is_current', true)->value('id');
    }

    /**
     * Get the options the filter selects are built from.
     *
     * The same shape the Results, academic and attendance pages use, so the
     * class and section selects narrow without a round trip.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'academicSessions' => AcademicSession::where('status', true)->orderByDesc('start_date')->get(),
            'departments' => Department::where('status', true)->orderBy('name')->get(),
            'classesByDepartment' => AcademicClass::where('status', true)
                ->orderBy('name')
                ->get(['id', 'name', 'department_id'])
                ->groupBy('department_id')
                ->map(fn ($classes) => $classes->map->only(['id', 'name'])->values()),
            'sectionsByClass' => Section::where('status', true)
                ->orderBy('name')
                ->get(['id', 'name', 'academic_class_id'])
                ->groupBy('academic_class_id')
                ->map(fn ($sections) => $sections->map->only(['id', 'name'])->values()),
        ];
    }
}
