<?php

namespace Tests\Feature;

use App\Models\MadrassaDailyRecord;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentAttendance;
use App\Models\StudentPrayerAttendance;
use App\Models\StudentResult;
use App\Support\MadrassaStudentReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers the printable madrassa reports.
 *
 * Two documents: the result report over a filtered group, and one student's
 * detailed track record. Both are PDFs, both are read only, and both draw
 * from registers that belong to other modules - academic attendance, prayer
 * attendance and the Hifz daily record - so most of what matters here is
 * that they read those registers the way those modules already read them,
 * and that neither can reach the school side of anything.
 *
 * The PDF bytes themselves are not parsed. What is asserted is that a real
 * PDF comes back, inline rather than as a download, and - by rendering the
 * same data through the report builder and the Blade views - that the
 * figures behind it are right. Asserting on decompressed PDF content
 * streams would test dompdf, not this module.
 */
class MadrassaResultPdfReportTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMadrassa();

        // The fixtures' session runs 2026-04-01 to 2027-03-31. Frozen
        // inside it so "a session still running" is a stable fact rather
        // than something that changes with the calendar.
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* ---------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------- */

    private function storedResult(StudentAcademicEnrollment $enrollment, array $overrides = []): StudentResult
    {
        return StudentResult::create(array_merge([
            'student_academic_enrollment_id' => $enrollment->id,
            'term' => StudentResult::TERM_FIRST,
            'test_type' => StudentResult::TEST_GRAND,
            'total_marks' => 100,
            'obtained_marks' => 85,
            'result_date' => '2026-09-10',
        ], $overrides));
    }

    private function mark(StudentAcademicEnrollment $enrollment, string $date, string $status, string $period = 'Morning'): StudentAttendance
    {
        return StudentAttendance::create([
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => $date,
            'attendance_period' => $period,
            'status' => $status,
        ]);
    }

    private function prayer(StudentAcademicEnrollment $enrollment, string $date, string $prayer, string $status): StudentPrayerAttendance
    {
        return StudentPrayerAttendance::create([
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => $date,
            'prayer' => $prayer,
            'status' => $status,
        ]);
    }

    private function daily(StudentAcademicEnrollment $enrollment, string $date, array $work = []): MadrassaDailyRecord
    {
        return MadrassaDailyRecord::create(array_merge([
            'student_academic_enrollment_id' => $enrollment->id,
            'record_date' => $date,
            'record_type' => MadrassaDailyRecord::TYPE_HIFZ,
            'sabaq' => 'Surah Al-Baqarah',
            'sabaq_quantity' => '1 page',
        ], $work));
    }

    /**
     * Assert a response really is an inline PDF.
     */
    private function assertInlinePdf($response): void
    {
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $disposition = $response->headers->get('Content-Disposition');

        $this->assertStringStartsWith('inline;', $disposition);
        // The whole browser requirement: a report opens in a tab, it is
        // never pushed at the user as a download.
        $this->assertStringNotContainsString('attachment', $disposition);

        // A real document, not an error page with a PDF header on it.
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    /* ---------------------------------------------------------------- */
    /* Access */
    /* ---------------------------------------------------------------- */

    public function test_guests_cannot_reach_either_pdf_route(): void
    {
        $student = $this->student('Hamza Iqbal');
        $this->madrassaEnrollment($student);

        auth()->logout();

        $this->get(route('results.reports.pdf'))->assertRedirect(route('login'));
        $this->get(route('students.results.pdf', $student))->assertRedirect(route('login'));
    }

    public function test_the_result_report_pdf_is_generated(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Hamza Iqbal'));
        $this->storedResult($enrollment);

        $this->assertInlinePdf($this->get(route('results.reports.pdf')));
    }

    public function test_the_student_detailed_report_pdf_is_generated(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);
        $this->storedResult($enrollment);
        $this->daily($enrollment, '2026-09-07');
        $this->mark($enrollment, '2026-09-07', StudentAttendance::STATUS_PRESENT);
        $this->prayer($enrollment, '2026-09-07', 'Fajr', StudentPrayerAttendance::STATUS_PRESENT);

        $this->assertInlinePdf($this->get(route('students.results.pdf', $student)));
    }

    /* ---------------------------------------------------------------- */
    /* Madrassa-only enforcement */
    /* ---------------------------------------------------------------- */

    public function test_a_school_only_student_has_no_detailed_report(): void
    {
        $student = $this->student('Usman Tariq', 'School');
        $this->schoolEnrollment($student);

        // Not an empty document: no report at all. A school-only student
        // has no madrassa track record for this URL to render.
        $this->get(route('students.results.pdf', $student))->assertNotFound();
    }

    public function test_a_school_only_student_never_appears_in_the_result_report(): void
    {
        $this->madrassaEnrollment($this->student('Hamza Iqbal'));

        $school = $this->student('Usman Tariq', 'School');
        $schoolEnrollment = $this->schoolEnrollment($school);

        // Registers written straight onto the school enrollment, bypassing
        // every rule the write paths enforce.
        $this->storedResult($schoolEnrollment, ['obtained_marks' => 72]);
        $this->mark($schoolEnrollment, '2026-09-07', StudentAttendance::STATUS_PRESENT);

        $this->assertInlinePdf($this->get(route('results.reports.pdf')));

        // The report builder is what the document is rendered from, so the
        // assertion is made where the data is decided.
        $report = new MadrassaStudentReport($school, $this->session);

        $this->assertFalse($report->hasMadrassaEnrollment());
        $this->assertSame(0, $report->attendanceSummary()['present']);
        $this->assertNull($report->resultsByTerm()[StudentResult::TERM_FIRST]);
    }

    public function test_a_hifz_plus_school_student_is_reported_through_the_madrassa_side_only(): void
    {
        $student = $this->student('Ali Raza', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->storedResult($madrassa, ['obtained_marks' => 61]);
        $this->mark($madrassa, '2026-09-07', StudentAttendance::STATUS_PRESENT);

        // The school side of the same student, which must be invisible.
        $this->storedResult($school, ['obtained_marks' => 99]);
        $this->mark($school, '2026-09-07', StudentAttendance::STATUS_PRESENT);
        $this->mark($school, '2026-09-08', StudentAttendance::STATUS_ABSENT);

        $report = new MadrassaStudentReport($student, $this->session);

        // One placement, one result, one mark. The school rows are not
        // reachable from here.
        $this->assertSame(1, $report->enrollments()->count());
        $this->assertSame($madrassa->id, $report->enrollments()->first()->id);

        $attendance = $report->attendanceSummary();
        $this->assertSame(1, $attendance['present']);
        $this->assertSame(0, $attendance['absent']);

        $this->assertSame(
            '61.00',
            (string) $report->resultsByTerm()[StudentResult::TERM_FIRST]->obtained_marks
        );

        $this->assertInlinePdf($this->get(route('students.results.pdf', $student)));
    }

    public function test_a_manipulated_query_string_cannot_widen_the_result_report(): void
    {
        $this->madrassaEnrollment($this->student('Hamza Iqbal'));

        $school = $this->student('Usman Tariq', 'School');
        $schoolEnrollment = $this->schoolEnrollment($school);
        $this->storedResult($schoolEnrollment, ['obtained_marks' => 72]);

        // A track, a school student and a school placement, all named at
        // once. Nothing reads a track, and the student filter can only
        // narrow, so the report comes back with no students rather than
        // with the school one.
        $response = $this->get(route('results.reports.pdf', [
            'academic_track' => 'School',
            'student_id' => $school->id,
            'student_academic_enrollment_id' => $schoolEnrollment->id,
            'department_id' => $this->school->id,
        ]));

        $this->assertInlinePdf($response);
    }

    /* ---------------------------------------------------------------- */
    /* Result content */
    /* ---------------------------------------------------------------- */

    public function test_the_first_term_result_is_reported_with_its_marks_and_grade(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);
        $this->storedResult($enrollment, ['obtained_marks' => 85]);

        $report = new MadrassaStudentReport($student, $this->session);
        $result = $report->resultsByTerm()[StudentResult::TERM_FIRST];

        $this->assertSame('85.00', (string) $result->obtained_marks);
        $this->assertSame('85.00', (string) $result->percentage);
        // The approved ladder, untouched: 85% is an A.
        $this->assertSame('A', $result->grade);
        $this->assertSame('Passed', MadrassaStudentReport::statusFor($result));
    }

    public function test_the_final_term_result_is_reported_separately_from_the_first(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        $this->storedResult($enrollment, ['obtained_marks' => 82]);
        $this->storedResult($enrollment, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 91,
            'result_date' => '2027-03-15',
        ]);

        $byTerm = (new MadrassaStudentReport($student, $this->session))->resultsByTerm();

        $this->assertSame('82.00', (string) $byTerm[StudentResult::TERM_FIRST]->percentage);
        $this->assertSame('91.00', (string) $byTerm[StudentResult::TERM_FINAL]->percentage);

        // Never combined: 86.5 would be their mean and 173 the sum of the
        // marks. Neither is a figure this module produces.
        $this->assertNotSame('86.50', (string) $byTerm[StudentResult::TERM_FIRST]->percentage);
        $this->assertNotSame('86.50', (string) $byTerm[StudentResult::TERM_FINAL]->percentage);
    }

    public function test_an_unmarked_term_is_reported_as_not_entered(): void
    {
        $student = $this->student('Hamza Iqbal');
        $this->madrassaEnrollment($student);

        $byTerm = (new MadrassaStudentReport($student, $this->session))->resultsByTerm();

        $this->assertNull($byTerm[StudentResult::TERM_FIRST]);
        $this->assertSame('Not Entered', MadrassaStudentReport::statusFor(null));
    }

    public function test_a_failing_grade_is_reported_as_failed(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);
        $this->storedResult($enrollment, ['obtained_marks' => 30]);

        $result = (new MadrassaStudentReport($student, $this->session))
            ->resultsByTerm()[StudentResult::TERM_FIRST];

        $this->assertSame('F', $result->grade);
        $this->assertSame('Failed', MadrassaStudentReport::statusFor($result));
    }

    /* ---------------------------------------------------------------- */
    /* Attendance */
    /* ---------------------------------------------------------------- */

    public function test_the_attendance_summary_counts_madrassa_marks_only(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        // Three registers on one day, one of them missed.
        $this->mark($enrollment, '2026-09-07', StudentAttendance::STATUS_PRESENT, 'Morning');
        $this->mark($enrollment, '2026-09-07', StudentAttendance::STATUS_PRESENT, 'Afternoon');
        $this->mark($enrollment, '2026-09-07', StudentAttendance::STATUS_ABSENT, 'Evening');

        $attendance = (new MadrassaStudentReport($student, $this->session))->attendanceSummary();

        $this->assertSame(2, $attendance['present']);
        $this->assertSame(1, $attendance['absent']);
        $this->assertSame(3, $attendance['recorded']);
        // The existing rule: Present over what was recorded, never over the
        // calendar.
        $this->assertSame(66.67, $attendance['percentage']);
        // Three registers a day on this track.
        $this->assertSame($attendance['working_days'] * 3, $attendance['opportunities']);
    }

    public function test_attendance_is_not_counted_beyond_today_in_a_running_session(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        // Today is 15 Sep 2026 and the session runs to 31 Mar 2027.
        $this->mark($enrollment, '2026-09-07', StudentAttendance::STATUS_PRESENT);
        // Dated ahead of today: a day nobody has reached.
        $this->mark($enrollment, '2026-11-02', StudentAttendance::STATUS_PRESENT);

        $report = new MadrassaStudentReport($student, $this->session);

        $this->assertSame('2026-09-15', $report->countedEnd()->format('Y-m-d'));
        $this->assertSame(1, $report->attendanceSummary()['present']);

        // And the working days stop at today rather than running to the end
        // of the session.
        $this->assertSame(
            StudentAttendance::teachingDaysBetween('2026-04-01', '2026-09-15'),
            $report->workingDays()
        );
    }

    public function test_a_completed_session_is_counted_to_its_own_end_date(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        // Move today past the end of the session.
        Carbon::setTestNow(Carbon::parse('2027-06-01 10:00:00'));

        $this->mark($enrollment, '2027-03-30', StudentAttendance::STATUS_PRESENT);

        $report = new MadrassaStudentReport($student, $this->session);

        $this->assertSame('2027-03-31', $report->countedEnd()->format('Y-m-d'));
        $this->assertSame(1, $report->attendanceSummary()['present']);
        $this->assertSame(
            StudentAttendance::teachingDaysBetween('2026-04-01', '2027-03-31'),
            $report->workingDays()
        );
    }

    public function test_enrollment_start_and_end_bound_the_reported_days(): void
    {
        $student = $this->student('Hamza Iqbal');

        // Joined mid-session and left again, so the report must not credit
        // them with the months either side.
        $this->madrassaEnrollment($student, [
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-31',
            'status' => 'Completed',
        ]);

        $report = new MadrassaStudentReport($student, $this->session);

        $this->assertSame(
            StudentAttendance::teachingDaysBetween('2026-06-01', '2026-07-31'),
            $report->workingDays()
        );

        $months = collect($report->months())->keyBy('key');

        $this->assertSame(0, $months['2026-04']['working_days']);
        $this->assertSame(0, $months['2026-05']['working_days']);
        $this->assertGreaterThan(0, $months['2026-06']['working_days']);
        $this->assertSame(0, $months['2026-08']['working_days']);
    }

    /* ---------------------------------------------------------------- */
    /* Prayer */
    /* ---------------------------------------------------------------- */

    public function test_the_prayer_summary_follows_the_existing_rules(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        $this->prayer($enrollment, '2026-09-07', 'Fajr', StudentPrayerAttendance::STATUS_PRESENT);
        $this->prayer($enrollment, '2026-09-07', 'Zuhr', StudentPrayerAttendance::STATUS_ABSENT);
        $this->prayer($enrollment, '2026-09-07', 'Asr', StudentPrayerAttendance::STATUS_PRESENT);
        // Maghrib and Isha are not transcribed: unrecorded, not absent.

        $prayer = (new MadrassaStudentReport($student, $this->session))->prayerSummary();

        $this->assertSame(2, $prayer['present']);
        $this->assertSame(1, $prayer['absent']);
        $this->assertSame(3, $prayer['recorded']);
        $this->assertSame(66.67, $prayer['percentage']);

        $this->assertSame(100.0, $prayer['prayers']['Fajr']['percentage']);
        $this->assertSame(0.0, $prayer['prayers']['Zuhr']['percentage']);
        // Nothing recorded at all is N/A rather than zero.
        $this->assertNull($prayer['prayers']['Maghrib']['percentage']);

        // Five prayers per working day.
        $this->assertSame($prayer['expected'] % 5, 0);
    }

    public function test_the_prayer_history_marks_weekends_off_rather_than_absent(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        // A Monday, recorded.
        $this->prayer($enrollment, '2026-09-07', 'Fajr', StudentPrayerAttendance::STATUS_PRESENT);

        $history = collect((new MadrassaStudentReport($student, $this->session))->prayerHistory())
            ->keyBy(fn ($day) => $day['date']->format('Y-m-d'));

        $monday = $history['2026-09-07'];

        $this->assertNull($monday['off_day']);
        $this->assertSame(StudentPrayerAttendance::STATUS_PRESENT, $monday['prayers']['Fajr']);
        // A prayer with no row is unmarked, never an absence.
        $this->assertSame(StudentPrayerAttendance::STATUS_UNMARKED, $monday['prayers']['Isha']);
    }

    public function test_an_off_day_prayer_row_is_reported_as_off(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        // 2026-09-06 is a Sunday, the weekly off day. The entry sheet
        // refuses one, but a row reaching the table by any other route must
        // still not read as an absence on a report.
        $this->prayer($enrollment, '2026-09-06', 'Fajr', StudentPrayerAttendance::STATUS_ABSENT);

        $day = collect((new MadrassaStudentReport($student, $this->session))->prayerHistory())
            ->firstWhere(fn ($row) => $row['date']->format('Y-m-d') === '2026-09-06');

        $this->assertSame('Sunday', $day['off_day']);

        foreach ($day['prayers'] as $status) {
            $this->assertSame('OFF', $status);
        }
    }

    public function test_a_saturday_prayer_row_is_reported_normally(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        // 2026-09-05 is a Saturday, and Saturday is now a working day: the
        // mark stands as recorded rather than being blanked out as OFF.
        $this->prayer($enrollment, '2026-09-05', 'Fajr', StudentPrayerAttendance::STATUS_ABSENT);

        $day = collect((new MadrassaStudentReport($student, $this->session))->prayerHistory())
            ->firstWhere(fn ($row) => $row['date']->format('Y-m-d') === '2026-09-05');

        $this->assertNull($day['off_day']);
        $this->assertSame(StudentPrayerAttendance::STATUS_ABSENT, $day['prayers']['Fajr']);
    }

    /* ---------------------------------------------------------------- */
    /* Daily records and progress */
    /* ---------------------------------------------------------------- */

    public function test_the_progress_summary_counts_prepared_and_unprepared_days(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        // Two days with a Sabaq, one recorded day without one.
        $this->daily($enrollment, '2026-09-07');
        $this->daily($enrollment, '2026-09-08', ['manzil' => 'Para 1 to 5', 'manzil_quantity' => '1 para']);
        $this->daily($enrollment, '2026-09-09', [
            'sabaq' => null,
            'sabaq_quantity' => null,
            'sabqi' => 'Para 3',
        ]);

        $progress = (new MadrassaStudentReport($student, $this->session))->progressSummary();

        $this->assertSame(MadrassaDailyRecord::TYPE_HIFZ, $progress['record_type']);
        $this->assertSame(3, $progress['recorded_days']);
        $this->assertSame('Sabaq', $progress['lesson_label']);
        $this->assertSame(2, $progress['prepared_lessons']);
        $this->assertSame(1, $progress['unprepared_lessons']);
        // One day carried a Sabqi, so two recorded days did not.
        $this->assertSame(2, $progress['unprepared_revision']);
        $this->assertSame(1, $progress['manzil']);
    }

    public function test_the_latest_record_reports_its_own_placement_teacher_and_lesson(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $teacher = $this->teacher();

        $this->daily($enrollment, '2026-09-07');
        $this->daily($enrollment, '2026-09-08', [
            'sabaq' => 'Surah Aal-e-Imran',
            'sabaq_quantity' => 'half page',
            'teacher_id' => $teacher->id,
        ]);

        $latest = (new MadrassaStudentReport($student, $this->session))->latestRecord();

        $this->assertSame('2026-09-08', $latest->record_date->format('Y-m-d'));
        $this->assertSame($teacher->id, $latest->teacher_id);
        $this->assertStringContainsString('half page', $latest->workSummary());
        // The placement the record was written against.
        $this->assertSame($this->nazra->id, $latest->studentAcademicEnrollment->academic_class_id);
    }

    public function test_the_daily_records_carry_their_own_historical_placement(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $this->daily($enrollment, '2026-09-07');

        // Promoted afterwards.
        $enrollment->update(['status' => 'Completed', 'end_date' => '2027-03-31']);
        $student->academicEnrollments()->create([
            'academic_session_id' => $this->nextSession->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'start_date' => '2027-04-01',
            'status' => 'Active',
        ]);

        $record = (new MadrassaStudentReport($student, $this->session))->dailyRecords()->first();

        $this->assertSame($this->nazra->id, $record->studentAcademicEnrollment->academic_class_id);
        $this->assertNull($record->studentAcademicEnrollment->section_id);
        $this->assertSame($this->session->id, $record->studentAcademicEnrollment->academic_session_id);
    }

    /* ---------------------------------------------------------------- */
    /* Historical placement */
    /* ---------------------------------------------------------------- */

    public function test_a_result_keeps_its_historical_placement_in_the_detailed_report(): void
    {
        $student = $this->student('Hamza Iqbal');

        $first = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);
        $this->storedResult($first, ['obtained_marks' => 61]);

        $first->update(['status' => 'Completed', 'end_date' => '2027-03-31']);
        $second = $student->academicEnrollments()->create([
            'academic_session_id' => $this->nextSession->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'start_date' => '2027-04-01',
            'status' => 'Active',
        ]);
        $this->storedResult($second, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 72,
            'result_date' => '2027-09-30',
        ]);

        $history = (new MadrassaStudentReport($student, $this->session))->resultHistory();

        $this->assertSame(2, $history->count());

        $old = $history->firstWhere('term', StudentResult::TERM_FIRST);
        $new = $history->firstWhere('term', StudentResult::TERM_FINAL);

        // Each result names the placement it was recorded under, not the
        // one the student holds now.
        $this->assertSame($this->nazra->id, $old->studentAcademicEnrollment->academic_class_id);
        $this->assertSame($this->session->id, $old->studentAcademicEnrollment->academic_session_id);

        $this->assertSame($this->hifzClass->id, $new->studentAcademicEnrollment->academic_class_id);
        $this->assertSame($this->hifzB->id, $new->studentAcademicEnrollment->section_id);

        $this->assertInlinePdf($this->get(route('students.results.pdf', $student)));
    }

    public function test_the_enrollment_history_lists_every_madrassa_placement(): void
    {
        $student = $this->student('Hamza Iqbal');

        $first = $this->madrassaEnrollment($student, ['academic_class_id' => $this->nazra->id, 'section_id' => null]);
        $first->update(['status' => 'Completed', 'end_date' => '2027-03-31']);

        $student->academicEnrollments()->create([
            'academic_session_id' => $this->nextSession->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'start_date' => '2027-04-01',
            'status' => 'Active',
        ]);

        // And a school placement, which must not be listed.
        $this->schoolEnrollment($student);

        $enrollments = (new MadrassaStudentReport($student, $this->session))->enrollments();

        $this->assertSame(2, $enrollments->count());
        $this->assertTrue($enrollments->every(fn ($e) => $e->academic_track === 'Madrassa'));
    }

    /* ---------------------------------------------------------------- */
    /* Month-wise */
    /* ---------------------------------------------------------------- */

    public function test_the_month_table_covers_every_month_of_the_session(): void
    {
        $student = $this->student('Hamza Iqbal');
        $this->madrassaEnrollment($student);

        $months = (new MadrassaStudentReport($student, $this->session))->months();

        // April 2026 to March 2027 inclusive.
        $this->assertCount(12, $months);
        $this->assertSame('2026-04', $months[0]['key']);
        $this->assertSame('2027-03', $months[11]['key']);
    }

    public function test_months_after_today_report_nothing_in_a_running_session(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        $this->mark($enrollment, '2026-09-07', StudentAttendance::STATUS_PRESENT);
        // Dated in a month that has not arrived.
        $this->mark($enrollment, '2026-12-01', StudentAttendance::STATUS_PRESENT);

        $months = collect((new MadrassaStudentReport($student, $this->session))->months())->keyBy('key');

        $this->assertSame(1, $months['2026-09']['present']);
        // Still listed, so the table is a whole year, but counting nothing.
        $this->assertSame(0, $months['2026-12']['working_days']);
        $this->assertSame(0, $months['2026-12']['present']);
        $this->assertNull($months['2026-12']['attendance_percentage']);
    }

    public function test_the_month_table_reports_attendance_progress_and_prayer_per_month(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        $this->mark($enrollment, '2026-08-03', StudentAttendance::STATUS_PRESENT);
        $this->mark($enrollment, '2026-08-04', StudentAttendance::STATUS_ABSENT);
        $this->daily($enrollment, '2026-08-03');
        $this->daily($enrollment, '2026-08-04', ['sabaq' => null, 'sabaq_quantity' => null, 'sabqi' => 'Para 3']);
        $this->prayer($enrollment, '2026-08-03', 'Fajr', StudentPrayerAttendance::STATUS_PRESENT);

        // A different month, so the bucketing is doing real work.
        $this->mark($enrollment, '2026-09-07', StudentAttendance::STATUS_PRESENT);

        $months = collect((new MadrassaStudentReport($student, $this->session))->months())->keyBy('key');

        $august = $months['2026-08'];

        $this->assertSame(1, $august['present']);
        $this->assertSame(1, $august['absent']);
        $this->assertSame(50.0, $august['attendance_percentage']);
        $this->assertSame(1, $august['prepared_lessons']);
        $this->assertSame(1, $august['unprepared_lessons']);
        $this->assertSame(1, $august['prayer_present']);
        $this->assertSame(100.0, $august['prayer_percentage']);

        $this->assertSame(1, $months['2026-09']['present']);
        $this->assertSame(0, $months['2026-09']['prepared_lessons']);
    }

    /* ---------------------------------------------------------------- */
    /* Filters */
    /* ---------------------------------------------------------------- */

    public function test_the_report_filters_combine_with_and_logic(): void
    {
        // Nazra and named Zaid: the only one both filters match.
        $target = $this->student('Zaid Anwar');
        $this->madrassaEnrollment($target, ['academic_class_id' => $this->nazra->id, 'section_id' => null]);

        // Right class, wrong student.
        $this->madrassaEnrollment($this->student('Hamza Iqbal'), [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $response = $this->get(route('results.reports.pdf', [
            'academic_class_id' => $this->nazra->id,
            'student_id' => $target->id,
        ]));

        $this->assertInlinePdf($response);

        // A class the student is not in matches nothing rather than one
        // filter quietly winning over the other.
        $this->assertInlinePdf($this->get(route('results.reports.pdf', [
            'academic_class_id' => $this->hifzClass->id,
            'student_id' => $target->id,
        ])));
    }

    public function test_a_session_the_student_never_held_is_not_honoured(): void
    {
        $student = $this->student('Hamza Iqbal');
        $this->madrassaEnrollment($student);

        // The student holds no placement in nextSession, so asking for it
        // falls back to the session they actually held rather than
        // rendering a year they were not here for.
        $response = $this->get(route('students.results.pdf', [
            'student' => $student,
            'academic_session_id' => $this->nextSession->id,
        ]));

        $this->assertInlinePdf($response);
    }

    /* ---------------------------------------------------------------- */
    /* Performance */
    /* ---------------------------------------------------------------- */

    public function test_the_detailed_report_query_count_does_not_grow_with_the_records(): void
    {
        $few = $this->student('Hamza Iqbal');
        $fewEnrollment = $this->madrassaEnrollment($few);

        $many = $this->student('Bilal Ahmad');
        $manyEnrollment = $this->madrassaEnrollment($many);

        $this->seedMonth($fewEnrollment, 2);
        $this->seedMonth($manyEnrollment, 20);

        // Warmed first: the permission package fills its cache on the first
        // request of the process.
        $this->get(route('students.results.pdf', $few))->assertOk();

        $withFew = $this->countQueriesFor(route('students.results.pdf', $few));
        $withMany = $this->countQueriesFor(route('students.results.pdf', $many));

        $this->assertSame(
            $withFew,
            $withMany,
            "The report ran {$withMany} queries for 20 days of records and {$withFew} for 2."
        );
    }

    public function test_the_result_report_cost_per_student_stays_constant(): void
    {
        $add = function (int $count, string $prefix) {
            for ($index = 0; $index < $count; $index++) {
                $this->storedResult($this->madrassaEnrollment($this->student($prefix.' '.$index)));
            }
        };

        $add(2, 'First');

        // Warmed first: the permission package fills its cache on the first
        // request of the process.
        $this->get(route('results.reports.pdf'))->assertOk();
        $atTwo = $this->countQueriesFor(route('results.reports.pdf'));

        $add(4, 'Second');
        $atSix = $this->countQueriesFor(route('results.reports.pdf'));

        $add(4, 'Third');
        $atTen = $this->countQueriesFor(route('results.reports.pdf'));

        // This report is one section per student, so it legitimately costs
        // something per student - a whole page of registers cannot be drawn
        // for free. What it must not do is cost *more* per student as the
        // group grows, which is what an N+1 inside a section would look
        // like. The marginal cost of four more students is measured twice
        // and has to come out the same both times.
        $firstFour = ($atSix - $atTwo) / 4;
        $secondFour = ($atTen - $atSix) / 4;

        $this->assertSame(
            $firstFour,
            $secondFour,
            "Adding four students cost {$firstFour} queries each, then {$secondFour}. "
                .'Something inside a student section grows with the group.'
        );

        // And the per-student cost is a small constant rather than
        // something proportional to the registers a student holds.
        $this->assertLessThanOrEqual(20, $firstFour);
    }

    /**
     * Give an enrollment a run of daily records, marks and prayers.
     */
    private function seedMonth(StudentAcademicEnrollment $enrollment, int $days): void
    {
        $date = Carbon::parse('2026-08-03');

        for ($index = 0; $index < $days; $index++) {
            while (! StudentAttendance::isAttendanceDay($date)) {
                $date->addDay();
            }

            $key = $date->format('Y-m-d');

            $this->daily($enrollment, $key);
            $this->mark($enrollment, $key, StudentAttendance::STATUS_PRESENT);
            $this->prayer($enrollment, $key, 'Fajr', StudentPrayerAttendance::STATUS_PRESENT);

            $date->addDay();
        }
    }

    /**
     * Count the database queries one request runs.
     */
    private function countQueriesFor(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get($url)->assertOk();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }
}
