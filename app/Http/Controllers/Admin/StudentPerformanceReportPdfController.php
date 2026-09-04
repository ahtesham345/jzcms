<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\StudentResult;
use App\Support\MadrassaStudentReport;
use App\Support\PdfRenderer;
use Illuminate\Http\Request;

/**
 * One student's detailed madrassa track record, as a PDF.
 *
 * The long form: profile, every madrassa placement they have held, the
 * session month by month, and then the underlying registers themselves -
 * daily records, prayers, academic attendance and results.
 *
 * The student comes from the route binding and the madrassa placements are
 * derived from that student alone, so nothing in the query string can reach
 * another student's registers or the school side of this one's. A
 * school-only student has no madrassa placement and therefore no report at
 * all: the route 404s for them rather than rendering an empty document that
 * would imply one exists.
 *
 * Every historical figure reads the enrollment its own row was written
 * against. The current placement is used for the profile block and nowhere
 * else.
 */
class StudentPerformanceReportPdfController extends Controller
{
    /**
     * How many rows of each register the report prints.
     *
     * A full academic year of madrassa registers is roughly 250 days, three
     * attendance marks and five prayers each. Printing every row of every
     * register unbounded would be a document nobody can hold, so each
     * section is capped and says so when it has been.
     */
    private const HISTORY_LIMIT = 400;

    /**
     * Stream one student's detailed report.
     */
    public function show(Request $request, Student $student)
    {
        $session = $this->session($request, $student);

        $report = new MadrassaStudentReport($student, $session);

        // The gate. A student with no madrassa placement has no madrassa
        // track record, and this URL must not become a way to read a
        // school-only student's registers.
        if (! $report->hasMadrassaEnrollment()) {
            abort(404);
        }

        $dailyRecords = $report->dailyRecords(self::HISTORY_LIMIT);
        $attendance = $report->attendanceHistory(self::HISTORY_LIMIT);
        $prayers = $report->prayerHistory(self::HISTORY_LIMIT);

        return PdfRenderer::inline('results.pdf.student-performance', [
            'report' => $report,
            'student' => $student,
            'session' => $session,
            'dailyRecords' => $dailyRecords,
            'attendanceHistory' => $attendance,
            'prayerHistory' => $prayers,
            'resultHistory' => $report->resultHistory(),
            'testType' => StudentResult::TEST_GRAND,
            'historyLimit' => self::HISTORY_LIMIT,
            'generatedAt' => now(),
        ], 'madrassa-student-report-'.$student->registration_number.'.pdf');
    }

    /**
     * Work out which session the report covers.
     *
     * A requested session is honoured only when the student actually held a
     * madrassa placement in it - otherwise the report would be a year of
     * empty months for a year they were not here, and a session id would
     * become a way to probe the calendar. Anything else falls back to the
     * session of their current madrassa placement.
     */
    private function session(Request $request, Student $student): ?AcademicSession
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
}
