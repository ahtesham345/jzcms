<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentResult;
use App\Support\MadrassaStudentReport;
use App\Support\PdfRenderer;
use App\Support\ResultReportLanguage;
use App\Support\UrduPdfRenderer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * The short madrassa result report.
 *
 * Result-focused and deliberately brief: who the student is, what they
 * scored in the term, a compact attendance and progress summary, and the
 * session month by month. That is all. It is the document an office hands
 * to a parent with a result, and it is a different thing from the detailed
 * student track record, which keeps its own route, its own controller and
 * its own view and is not touched by anything here.
 *
 * Two entry points, one format:
 *
 *   - one student, opened from the PDF action on a result row; and
 *   - a group, opened from Printable Report, covering exactly the students
 *     the filters on the reports page are showing.
 *
 * Both are read only. Nothing here writes a result, recalculates a
 * percentage or re-grades a paper: the figures come from StudentResult and
 * GradeScale, and the attendance and progress figures come from the
 * registers those modules already keep, through MadrassaStudentReport.
 *
 * Which students may be reported is derived here and never taken on trust.
 * The track condition sits on the enrollment query before any filter runs,
 * so a School enrollment cannot be reported however the query string is
 * edited, and a student with no madrassa placement has no short report at
 * all rather than an empty one.
 */
class ShortResultReportPdfController extends Controller
{
    /**
     * How many students one group report may cover.
     *
     * Each student is a compact block rather than a page of registers, so
     * this is looser than the detailed report's cap. It is still a guard:
     * the filters are how a large cohort is narrowed, and the report says
     * plainly when it has been capped.
     */
    private const MAX_STUDENTS = 200;

    /**
     * Stream the short result report for one student.
     *
     * The student comes from the route binding and their madrassa
     * placements are derived from that student alone, so no id in the
     * query string can reach anybody else.
     */
    public function student(Request $request, Student $student)
    {
        $session = $this->sessionForStudent($request, $student);

        $report = new MadrassaStudentReport($student, $session);

        // The gate. A school-only student has no madrassa result to print,
        // and this URL must not become a way to read their record.
        if (! $report->hasMadrassaEnrollment()) {
            abort(404);
        }

        return $this->render(
            [$report],
            $this->termsInScope($this->term($request)),
            $this->term($request),
            [
                'session' => $session,
                'department' => null,
                'academicClass' => null,
                'section' => null,
                'student' => $student,
                'search' => null,
            ],
            false,
            'madrassa-result-'.$student->registration_number.'.pdf',
            $this->language($request)
        );
    }

    /**
     * Stream the short result report for every filtered student.
     */
    public function group(Request $request)
    {
        $filters = $this->filters($request);
        $session = $filters['academic_session_id'] === null
            ? null
            : AcademicSession::find($filters['academic_session_id']);

        $enrollments = $this->enrollments($filters, $session);

        $reports = [];

        foreach ($enrollments as $enrollment) {
            $report = new MadrassaStudentReport($enrollment->student, $session);

            // Skipped rather than rendered empty: an enrollment whose
            // student has since lost every madrassa placement has nothing
            // to report.
            if (! $report->hasMadrassaEnrollment()) {
                continue;
            }

            $reports[] = $report;
        }

        return $this->render(
            $reports,
            $this->termsInScope($filters['term']),
            $filters['term'],
            $this->heading($filters, $session),
            $enrollments->count() >= self::MAX_STUDENTS,
            'madrassa-result-report.pdf',
            $filters['language']
        );
    }

    /* ------------------------------------------------------------------ */
    /* Rendering */
    /* ------------------------------------------------------------------ */

    /**
     * Render the short report, whoever it covers.
     *
     * One view for both entry points, so a single student's copy and their
     * row in a group report can never disagree about what they scored.
     *
     * @param  array<int, MadrassaStudentReport>  $reports
     * @param  array<int, string>  $terms
     * @param  array<string, mixed>  $heading
     */
    private function render(
        array $reports,
        array $terms,
        string $termLabel,
        array $heading,
        bool $capped,
        string $filename,
        string $language
    ) {
        $data = [
            'reports' => $reports,
            'terms' => $terms,
            'termLabel' => $termLabel,
            'testType' => StudentResult::TEST_GRAND,
            'heading' => $heading,
            'capped' => $capped,
            'generatedAt' => now(),
            'language' => $language,
            // Handed to the view as a closure so a template asks for a
            // label by key rather than carrying either language's wording.
            't' => ResultReportLanguage::translator($language),
            'direction' => ResultReportLanguage::direction($language),
            'fontFamily' => ResultReportLanguage::fontFamily($language),
        ];

        // Urdu goes through mPDF and everything else stays on dompdf.
        // Not a preference: dompdf performs no Arabic-script shaping and no
        // bidirectional layout, so an Urdu report rendered through it comes
        // out as disconnected letters in the wrong order. The English
        // report and the detailed report are untouched by this branch.
        return ResultReportLanguage::isRtl($language)
            ? UrduPdfRenderer::inline('results.pdf.short-result', $data, $filename)
            : PdfRenderer::inline('results.pdf.short-result', $data, $filename);
    }

    /* ------------------------------------------------------------------ */
    /* Who the report covers */
    /* ------------------------------------------------------------------ */

    /**
     * Get the madrassa enrollments the group report covers.
     *
     * The same scope the on-screen report uses, so Printable Report prints
     * exactly the students the page is showing: active placements, plus any
     * that carry a result in the reported terms so a result recorded before
     * a promotion keeps appearing under the placement it was marked in.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, StudentAcademicEnrollment>
     */
    private function enrollments(array $filters, ?AcademicSession $session)
    {
        $terms = $this->termsInScope($filters['term']);

        return StudentAcademicEnrollment::query()
            // The boundary, applied before anything the browser sent. There
            // is no track filter on this report and nothing here reads one.
            ->where('student_academic_enrollments.academic_track', StudentResult::ACADEMIC_TRACK)
            ->where(function ($query) use ($terms) {
                $query->where('student_academic_enrollments.status', 'Active')
                    ->orWhereHas('studentResults', function ($result) use ($terms) {
                        $result->whereIn('term', $terms)
                            ->where('test_type', StudentResult::TEST_GRAND);
                    });
            })
            ->when($session, fn ($query) => $query->where('student_academic_enrollments.academic_session_id', $session->id))
            ->when($filters['department_id'], fn ($query, $value) => $query->where('student_academic_enrollments.department_id', $value))
            ->when($filters['academic_class_id'], fn ($query, $value) => $query->where('student_academic_enrollments.academic_class_id', $value))
            ->when($filters['section_id'], fn ($query, $value) => $query->where('student_academic_enrollments.section_id', $value))
            // Optional, and narrowing only. An id naming somebody with no
            // madrassa placement matches nothing.
            ->when($filters['student_id'], fn ($query, $value) => $query->where('student_academic_enrollments.student_id', $value))
            ->when($filters['search'], function ($query, $search) {
                // Resolved against the student the enrollment belongs to, as
                // a condition on the enrollment rather than a widening of
                // it: a search can only narrow what the track condition
                // above already fixed.
                $query->whereHas('student', function ($student) use ($search) {
                    $student->where('full_name', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%")
                        ->orWhere('roll_number', 'like', "%{$search}%");
                });
            })
            ->with(['student', 'academicSession', 'department', 'academicClass', 'section'])
            ->join('students', 'students.id', '=', 'student_academic_enrollments.student_id')
            ->orderBy('students.roll_number')
            ->orderBy('students.registration_number')
            ->orderBy('students.full_name')
            ->select('student_academic_enrollments.*')
            ->limit(self::MAX_STUDENTS)
            ->get();
    }

    /**
     * Get the terms the report covers.
     *
     * Both when All Terms is chosen, and they stay separate throughout:
     * nothing here averages a First Term against a Final Term or adds their
     * marks together.
     *
     * @return array<int, string>
     */
    private function termsInScope(string $term): array
    {
        return $term === StudentResultReportController::ALL_TERMS
            ? StudentResult::TERMS
            : [$term];
    }

    /* ------------------------------------------------------------------ */
    /* Filters */
    /* ------------------------------------------------------------------ */

    /**
     * Read the term off the request.
     *
     * The report has to be about something, so an absent or unrecognised
     * term falls back to the First Term rather than reaching a query.
     */
    /**
     * Read the language off the request.
     *
     * Validated by being reduced to one of the two the reports exist in.
     * An explicit choice always wins: the reports pages offer English and
     * Urdu side by side, and a link that says English must produce an
     * English report whatever the institution has configured.
     *
     * Only when nothing recognisable was asked for does the institution's
     * default language apply. A report has to be printed in something, so a
     * hand-edited query string gets that default rather than an error page.
     */
    private function language(Request $request): string
    {
        return ResultReportLanguage::resolve(
            $request->input('language'),
            Setting::current()->default_language
        );
    }

    private function term(Request $request): string
    {
        $term = $request->input('term');

        return $term === StudentResultReportController::ALL_TERMS
            ? StudentResultReportController::ALL_TERMS
            : (StudentResult::normalizeTerm($term) ?? StudentResult::TERM_FIRST);
    }

    /**
     * Read the group report filters off the request.
     *
     * The same shape the on-screen report reads, so the printable version
     * of a page covers the same students.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'term' => $this->term($request),
            'language' => $this->language($request),
            // "All Sessions" is a deliberate choice rather than an absent
            // parameter, so it arrives as an empty string and is honoured
            // as null instead of snapping back to the current session.
            'academic_session_id' => $request->has('academic_session_id')
                ? ($request->filled('academic_session_id') ? $request->input('academic_session_id') : null)
                : AcademicSession::where('is_current', true)->value('id'),
            'department_id' => $request->filled('department_id') ? $request->input('department_id') : null,
            'academic_class_id' => $request->filled('academic_class_id') ? $request->input('academic_class_id') : null,
            'section_id' => $request->filled('section_id') ? $request->input('section_id') : null,
            'student_id' => $request->filled('student_id') ? $request->input('student_id') : null,
            'search' => $request->filled('search') ? trim((string) $request->input('search')) : null,
        ];
    }

    /**
     * Work out which session one student's short report covers.
     *
     * A requested session is honoured only when the student actually held a
     * madrassa placement in it, so a session id cannot become a way to
     * print a year they were not here for. Anything else falls back to
     * their current madrassa placement's session.
     */
    private function sessionForStudent(Request $request, Student $student): ?AcademicSession
    {
        $held = $student->academicEnrollments()
            ->where('academic_track', StudentResult::ACADEMIC_TRACK)
            ->pluck('academic_session_id')
            ->filter()
            ->unique();

        $requested = $request->input('academic_session_id');

        if (is_numeric($requested) && $held->contains((int) $requested)) {
            return AcademicSession::find((int) $requested);
        }

        $current = $student->activeEnrollmentForTrack(StudentResult::ACADEMIC_TRACK)
            ?? $student->academicEnrollments()
                ->where('academic_track', StudentResult::ACADEMIC_TRACK)
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();

        return $current?->academicSession()->first();
    }

    /**
     * Resolve the names the report heading is built from.
     *
     * Looked up once for the heading, never per student.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function heading(array $filters, ?AcademicSession $session): array
    {
        return [
            'session' => $session,
            'department' => $filters['department_id'] ? Department::find($filters['department_id']) : null,
            'academicClass' => $filters['academic_class_id'] ? AcademicClass::find($filters['academic_class_id']) : null,
            'section' => $filters['section_id'] ? Section::find($filters['section_id']) : null,
            'student' => $filters['student_id'] ? Student::find($filters['student_id']) : null,
            'search' => $filters['search'],
        ];
    }
}
