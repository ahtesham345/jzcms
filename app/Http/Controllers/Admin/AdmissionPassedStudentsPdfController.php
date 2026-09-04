<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Department;
use App\Models\Setting;
use App\Support\AdmissionApplicationFilters;
use App\Support\PdfRenderer;
use App\Support\ResultReportLanguage;
use App\Support\UrduPdfRenderer;
use Illuminate\Http\Request;

/**
 * The admission test passed students notice.
 *
 * The sheet that goes on the madrassa notice board after a test: who
 * passed, by application number, so a parent standing in front of it can
 * find their child without being told anything else about anybody. That is
 * the whole document, and it is why it prints an application number rather
 * than a student registration number and no marks at all.
 *
 * It reads the admission applications directly. An applicant who passed has
 * not necessarily been admitted yet - the approval that creates the student
 * is a separate, later decision - so requiring a student record here would
 * leave exactly the people this notice exists for off it.
 *
 * Read only. Nothing here records a result, changes a status or approves an
 * admission; those remain AdmissionApplicationController's business.
 *
 * Who appears is decided by the recorded test result and by the filters the
 * admission listing was showing, through the same scope the listing itself
 * uses. There is no second definition of "passed" and no second definition
 * of a filter, so the notice and the page can never disagree.
 */
class AdmissionPassedStudentsPdfController extends Controller
{
    /**
     * How many applicants one notice may list.
     *
     * A notice board sheet, not an archive: the filters are how a large
     * intake is narrowed into class-by-class notices.
     *
     * The cap limits what is printed, never what is counted. A capped notice
     * still states the true number of matching applicants and says how many
     * of them it is showing, so nobody reads a truncated list as a complete
     * one.
     */
    private const MAX_APPLICANTS = 500;

    /**
     * Stream the passed students notice for the current admission filters.
     */
    public function index(Request $request)
    {
        // The ids are the only values that can name a record that is not
        // there, so they are the only ones worth failing over. Everything
        // else the filters read is reduced to a value that exists, or to no
        // filter at all, in AdmissionApplicationFilters.
        $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'academic_class_id' => ['nullable', 'integer', 'exists:academic_classes,id'],
            'academic_session_id' => ['nullable', 'integer', 'exists:academic_sessions,id'],
        ]);

        $filters = AdmissionApplicationFilters::fromRequest($request);
        $language = $this->language($request);

        // The year this notice is about, decided before the query is built.
        // An explicitly chosen session wins; otherwise it is the institution's
        // current one, so the plain Print Passed Students button prints this
        // year's intake without anybody having to select anything.
        $session = $this->session($filters);

        // The notice is a sheet about one academic year, so it never falls
        // back to "every session". With no session to scope to there is
        // nothing truthful to print, and it says so rather than listing every
        // passed applicant the madrassa has ever had.
        $filters['academic_session_id'] = $session?->id;

        // The admission status is dropped, and this is the one filter the
        // notice refuses to honour.
        //
        // It is the listing's filter, not this document's. The listing offers
        // it over the whole workflow - Pending, Test Scheduled, Approved and
        // the rest - and printing from a page narrowed to Passed would quietly
        // drop every candidate who has since been approved, because approval
        // moves the status on to Approved. The sheet would then be missing
        // exactly the people who got a seat.
        //
        // What this notice is a list of is decided by the test result, which
        // approval never touches. Admission status is a column on it, not a
        // condition of it. Set to null rather than unset so the filter array
        // keeps the complete shape fromRequest() promises its callers.
        $filters['status'] = null;

        // Built once and counted before it is limited, so the total the
        // notice prints is the number of passed applicants these filters
        // actually match - not the number that happened to fit on the paper.
        // A capped notice that says "Total Passed: 500" is worse than no
        // total at all: it reads as a complete figure and is not one.
        $matching = AdmissionApplication::query()
            // The boundary, applied before anything else: only a recorded
            // pass reaches this document, and every recorded pass does. No
            // query string and no filter can widen it or narrow it, because
            // nothing below ever touches test_result again and the status
            // filter has just been dropped.
            ->passedTest()
            // The second boundary. A notice for a year it cannot name would
            // be a notice for every year, so with no session resolved the
            // document lists nobody at all rather than everybody.
            ->when($session === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->filter($filters);

        // The count runs against the same query the rows come from, so the
        // two can never describe different sets of people.
        $total = $matching->clone()->count();

        $applications = $matching
            // Both class sides and their departments, in two queries rather
            // than four per row.
            ->with(['madrassaClass.department', 'schoolClass.department'])
            ->orderBy('application_number')
            ->limit(self::MAX_APPLICANTS)
            ->get();

        $data = [
            'applications' => $applications,
            'total' => $total,
            'heading' => $this->heading($session, $filters),
            'session' => $session,
            'capped' => $total > self::MAX_APPLICANTS,
            'generatedAt' => now(),
            'language' => $language,
            // Handed to the view as a closure, so the template asks for a
            // label by key rather than carrying either language's wording.
            't' => ResultReportLanguage::translator($language),
            'direction' => ResultReportLanguage::direction($language),
            'fontFamily' => ResultReportLanguage::fontFamily($language),
        ];

        // Urdu goes through mPDF and everything else stays on dompdf, the
        // same split every other report in the project makes: dompdf performs
        // no Arabic-script shaping and no bidirectional layout, so an Urdu
        // notice rendered through it would come out as disconnected letters
        // in the wrong order.
        return ResultReportLanguage::isRtl($language)
            ? UrduPdfRenderer::inline('admissions.pdf.passed-students', $data, 'admission-test-passed-students.pdf')
            : PdfRenderer::inline('admissions.pdf.passed-students', $data, 'admission-test-passed-students.pdf');
    }

    /**
     * Read the language off the request.
     *
     * An explicit choice always wins; only when nothing recognisable was
     * asked for does the institution's default language apply. The same rule
     * the result reports follow, and it lives in ResultReportLanguage rather
     * than here so the two cannot come to disagree.
     */
    private function language(Request $request): string
    {
        return ResultReportLanguage::resolve(
            $request->input('language'),
            Setting::current()->default_language
        );
    }

    /**
     * Work out which academic session the notice covers.
     *
     * An explicitly selected session wins, so printing from a listing that
     * has been filtered to a past year produces that year's notice. With
     * nothing selected it is the institution's current session, which is
     * what makes the plain button print this year's intake.
     *
     * The id has already been checked against the sessions table by the
     * validator, and is read back through the filters so the notice and the
     * listing cannot resolve the same query string differently. Null when
     * nothing was chosen and no current session exists.
     *
     * @param  array<string, mixed>  $filters
     */
    private function session(array $filters): ?AcademicSession
    {
        if ($filters['academic_session_id'] !== null) {
            return AcademicSession::find($filters['academic_session_id']);
        }

        return AcademicSession::current();
    }

    /**
     * Resolve the names the notice heading is built from.
     *
     * Looked up once for the heading, never per applicant. The session is
     * the one the query was actually scoped to, so the heading cannot claim
     * a year the rows below it did not come from.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function heading(?AcademicSession $session, array $filters): array
    {
        return [
            'session' => $session,
            'department' => $filters['department_id'] ? Department::find($filters['department_id']) : null,
            'academicClass' => $filters['academic_class_id'] ? AcademicClass::find($filters['academic_class_id']) : null,
            'studentType' => $filters['student_type'],
            'gender' => $filters['gender'],
            'search' => $filters['search'],
        ];
    }
}
