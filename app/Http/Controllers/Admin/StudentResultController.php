<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentResultRequest;
use App\Http\Requests\Admin\UpdateStudentResultRequest;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentResult;
use App\Support\GradeScale;
use App\Support\ResultReportLanguage;
use Illuminate\Http\Request;

/**
 * Madrassa result entry.
 *
 * The madrassa runs two terms, First and Final, and each one has a Grand
 * Test. This module records what a student scored on it.
 *
 * The page is a roster rather than a listing of results: the filters draw
 * the madrassa students who match, and each row shows that term's result or
 * "Not Entered". That way an administrator works down a class the way the
 * paper mark sheet is worked down, and a student who has not been marked is
 * visible rather than absent from the page.
 *
 * Every result hangs off a student_academic_enrollments row. That is the
 * source of truth for where the student was placed: nothing here reads
 * students.academic_class_id, which cannot describe a Hifz + School student
 * holding two placements at once. Reading the placement back through the
 * stored enrollment is also what keeps an old result showing the class it
 * was recorded in after the student has been promoted.
 *
 * No destroy action, following the daily record module: a mistake is
 * corrected on the result it was made on. A student's marks are not
 * something an admin deletes.
 */
class StudentResultController extends Controller
{
    /**
     * How many students to show per page.
     */
    private const PER_PAGE = 20;

    /**
     * Everything the index reads off the query string.
     *
     * Also the whitelist for the filters a result form carries back, so a
     * hand-edited form cannot smuggle anything else into the redirect.
     *
     * @var array<int, string>
     */
    private const INDEX_FILTERS = [
        'search',
        'academic_session_id',
        'department_id',
        'academic_class_id',
        'section_id',
        'term',
        'student_id',
    ];

    /**
     * Display the madrassa students and their result for the chosen term.
     */
    public function index(Request $request)
    {
        // A term is always in effect: the result column has to be about
        // something. An unrecognised one falls back to the First Term
        // rather than reaching a query.
        $term = StudentResult::normalizeTerm($request->input('term'))
            ?? StudentResult::TERM_FIRST;

        $students = $this->rosterQuery($request)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('results.index', [
            // The languages the Result PDF can be printed in, so the
            // page can offer the choice before the report is opened.
            'reportLanguages' => ResultReportLanguage::NAMES,

            'enrollments' => $this->withResults($students, $term),
            'term' => $term,
            'terms' => StudentResult::TERMS,
            'testType' => StudentResult::TEST_GRAND,
            'filters' => $this->indexFilters($request) + ['term' => $term],
            'summary' => $this->summary($term),
            // Only shown when the listing was narrowed to one student, so
            // the page can say whose results it is showing.
            'filteredStudent' => $request->filled('student_id')
                ? Student::find($request->input('student_id'))
                : null,
            ...$this->filterOptions(),
        ]);
    }

    /**
     * Show the form for recording a result.
     *
     * Opened for one enrollment, which the roster supplies. Reached without
     * one there is no student to record anything against, so the request
     * goes back to the roster rather than to an empty form.
     */
    public function create(Request $request)
    {
        $enrollment = $this->openableEnrollment(
            $request->input('student_academic_enrollment_id')
        );

        if (is_string($enrollment)) {
            return $this->backToIndex($request, $enrollment);
        }

        $term = StudentResult::normalizeTerm($request->input('term'))
            ?? StudentResult::TERM_FIRST;

        // A term already marked is corrected, never marked twice. The
        // unique index would refuse the second write anyway; sending the
        // administrator to the existing result is the useful version of
        // that refusal.
        $existing = StudentResult::where('student_academic_enrollment_id', $enrollment->id)
            ->where('term', $term)
            ->where('test_type', StudentResult::TEST_GRAND)
            ->first();

        if ($existing !== null) {
            return redirect()
                ->route('results.edit', $existing)
                ->with('error', "This student already has a {$term} {$existing->test_type} result. Correct the existing result.");
        }

        return view('results.create', [
            'enrollment' => $enrollment,
            'result' => null,
            'term' => $term,
            'terms' => StudentResult::TERMS,
            'testTypes' => StudentResult::TEST_TYPES,
            'gradeLegend' => GradeScale::legend(),
            // Carried through the form so saving returns to the roster the
            // student was picked from rather than to an unfiltered page.
            'returnFilters' => $this->returnFilters($request),
        ]);
    }

    /**
     * Store a newly recorded result.
     *
     * The percentage and the grade are not among the attributes written
     * here. The model derives both from the marks on every save, which is
     * what makes them impossible for a request to set.
     */
    public function store(StoreStudentResultRequest $request)
    {
        $result = StudentResult::create($request->safe()->only([
            'student_academic_enrollment_id',
            'term',
            'test_type',
            'total_marks',
            'obtained_marks',
            'result_date',
            'remarks',
        ]));

        $student = $result->studentAcademicEnrollment->student;

        return $this->backToIndex(
            $request,
            null,
            "{$result->term} {$result->test_type} result saved for {$student->full_name}: "
                .$result->formattedMarks().' ('.$result->formattedPercentage().", grade {$result->grade})."
        );
    }

    /**
     * Display one result in full.
     */
    public function show(StudentResult $result)
    {
        $result->load([
            'studentAcademicEnrollment.student',
            'studentAcademicEnrollment.academicSession',
            'studentAcademicEnrollment.department',
            'studentAcademicEnrollment.academicClass',
            'studentAcademicEnrollment.section',
        ]);

        return view('results.show', [
            'result' => $result,
            'enrollment' => $result->studentAcademicEnrollment,
        ]);
    }

    /**
     * Show the form for correcting a result.
     */
    public function edit(Request $request, StudentResult $result)
    {
        $result->load([
            'studentAcademicEnrollment.student',
            'studentAcademicEnrollment.academicSession',
            'studentAcademicEnrollment.department',
            'studentAcademicEnrollment.academicClass',
            'studentAcademicEnrollment.section',
        ]);

        return view('results.edit', [
            'result' => $result,
            'enrollment' => $result->studentAcademicEnrollment,
            'term' => $result->term,
            'terms' => StudentResult::TERMS,
            'testTypes' => StudentResult::TEST_TYPES,
            'gradeLegend' => GradeScale::legend(),
            'returnFilters' => $this->returnFilters($request),
        ]);
    }

    /**
     * Update a result.
     *
     * The enrollment is never among the changes: the request reads it from
     * the stored row and refuses a request that names a different one, so a
     * correction can only ever change what the marks say, not whose marks
     * they were.
     */
    public function update(UpdateStudentResultRequest $request, StudentResult $result)
    {
        $result->update($request->safe()->only([
            'term',
            'test_type',
            'total_marks',
            'obtained_marks',
            'result_date',
            'remarks',
        ]));

        $student = $result->studentAcademicEnrollment->student;

        return $this->backToIndex(
            $request,
            null,
            "{$result->term} {$result->test_type} result updated for {$student->full_name}: "
                .$result->formattedMarks().' ('.$result->formattedPercentage().", grade {$result->grade})."
        );
    }

    /* ------------------------------------------------------------------ */
    /* The roster */
    /* ------------------------------------------------------------------ */

    /**
     * Build the query behind the roster.
     *
     * Madrassa enrollments only, which is the module's whole boundary. A
     * Hifz + School student appears once, through their madrassa
     * enrollment; a school-only student does not appear at all, and their
     * school enrollment is not a row this page can reach.
     *
     * Active placements only: a result is being entered for the current
     * term, and a completed or left placement is history rather than a row
     * to mark. The existing results of such a placement stay readable
     * through the student profile and through their own pages.
     */
    private function rosterQuery(Request $request)
    {
        return StudentAcademicEnrollment::query()
            ->with(['student', 'academicSession', 'department', 'academicClass', 'section'])
            // Qualified throughout: students is joined below for ordering
            // and carries columns of the same name.
            ->where('student_academic_enrollments.academic_track', StudentResult::ACADEMIC_TRACK)
            ->where('student_academic_enrollments.status', 'Active')
            ->when($request->filled('academic_session_id'), fn ($query) => $query->where('student_academic_enrollments.academic_session_id', $request->input('academic_session_id')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('student_academic_enrollments.department_id', $request->input('department_id')))
            ->when($request->filled('academic_class_id'), fn ($query) => $query->where('student_academic_enrollments.academic_class_id', $request->input('academic_class_id')))
            ->when($request->filled('section_id'), fn ($query) => $query->where('student_academic_enrollments.section_id', $request->input('section_id')))
            ->when($request->filled('student_id'), fn ($query) => $query->where('student_academic_enrollments.student_id', $request->input('student_id')))
            ->join('students', 'students.id', '=', 'student_academic_enrollments.student_id')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($student) use ($search) {
                    $student->where('students.full_name', 'like', "%{$search}%")
                        ->orWhere('students.registration_number', 'like', "%{$search}%")
                        ->orWhere('students.roll_number', 'like', "%{$search}%");
                });
            })
            // Roll number first, as the paper mark sheet is kept, then the
            // two columns that are always present so the order is total.
            ->orderBy('students.roll_number')
            ->orderBy('students.registration_number')
            ->orderBy('students.full_name')
            ->select('student_academic_enrollments.*');
    }

    /**
     * Attach each enrollment's result for the chosen term.
     *
     * One query for the whole page rather than one per student. The result
     * decides which rows offer View and Edit and which offer Add, which is
     * what keeps the roster from offering a second result for a term that
     * already has one.
     *
     * @template T of \Illuminate\Contracts\Pagination\LengthAwarePaginator
     *
     * @param  T  $enrollments
     * @return T
     */
    private function withResults($enrollments, string $term)
    {
        $results = StudentResult::query()
            ->whereIn('student_academic_enrollment_id', $enrollments->pluck('id')->all())
            ->where('term', $term)
            ->where('test_type', StudentResult::TEST_GRAND)
            ->get()
            ->keyBy('student_academic_enrollment_id');

        // Attached rather than returned alongside, so the view reads one
        // list instead of pairing two.
        $enrollments->each(function (StudentAcademicEnrollment $enrollment) use ($results) {
            $enrollment->setRelation('resultForTerm', $results->get($enrollment->id));
        });

        return $enrollments;
    }

    /**
     * Count what the module holds, for the cards at the top of the page.
     *
     * @return array<string, int>
     */
    private function summary(string $term): array
    {
        $madrassaStudents = StudentAcademicEnrollment::where('status', 'Active')
            ->where('academic_track', StudentResult::ACADEMIC_TRACK)
            ->distinct()
            ->count('student_id');

        return [
            'total' => StudentResult::count(),
            'first_term' => StudentResult::where('term', StudentResult::TERM_FIRST)->count(),
            'final_term' => StudentResult::where('term', StudentResult::TERM_FINAL)->count(),
            'term' => $term,
            'madrassa_students' => $madrassaStudents,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Shared helpers */
    /* ------------------------------------------------------------------ */

    /**
     * Load an enrollment a result may be opened for, or say why it may not.
     *
     * Returns the enrollment, or the reason as a string. The same questions
     * the form request asks on save, asked here so the interface never
     * opens a form the save would refuse.
     *
     * @return StudentAcademicEnrollment|string
     */
    private function openableEnrollment(mixed $id)
    {
        if (! is_numeric($id)) {
            return 'Choose a student from the list to record a result.';
        }

        $enrollment = StudentAcademicEnrollment::with([
            'student',
            'academicSession',
            'department',
            'academicClass',
            'section',
        ])->find($id);

        if ($enrollment === null) {
            return 'That academic enrollment no longer exists.';
        }

        if (! StudentResult::isResultableEnrollment($enrollment)) {
            return 'A result can only be recorded against a Madrassa enrollment.';
        }

        if ($enrollment->status !== 'Active') {
            return "A result cannot be created against a {$enrollment->status} enrollment.";
        }

        return $enrollment;
    }

    /**
     * Read the index filters off the request.
     *
     * @return array<string, mixed>
     */
    private function indexFilters(Request $request): array
    {
        $filters = [];

        foreach (self::INDEX_FILTERS as $filter) {
            $filters[$filter] = $request->filled($filter) ? $request->input($filter) : null;
        }

        return $filters;
    }

    /**
     * Read the filters a result form is carrying back to the roster.
     *
     * Whitelisted and reduced to scalars: this ends up in a redirect URL,
     * so nothing arbitrary from the form may reach it.
     *
     * @return array<string, string>
     */
    private function returnFilters(Request $request): array
    {
        $submitted = $request->input('filters', []);

        if (! is_array($submitted)) {
            return [];
        }

        $filters = [];

        foreach (self::INDEX_FILTERS as $filter) {
            $value = $submitted[$filter] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                $filters[$filter] = (string) $value;
            }
        }

        return $filters;
    }

    /**
     * Send the request back to the roster it came from.
     */
    private function backToIndex(Request $request, ?string $error = null, ?string $success = null)
    {
        $redirect = redirect()->route('results.index', $this->returnFilters($request));

        if ($error !== null) {
            return $redirect->with('error', $error);
        }

        return $success === null ? $redirect : $redirect->with('success', $success);
    }

    /**
     * Get the options the filter selects are built from.
     *
     * The same shape the academic, attendance and Hifz pages use, so the
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
