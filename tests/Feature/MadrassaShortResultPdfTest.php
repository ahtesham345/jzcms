<?php

namespace Tests\Feature;

use App\Models\MadrassaDailyRecord;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentAttendance;
use App\Models\StudentResult;
use App\Support\GradeScale;
use App\Support\MadrassaStudentReport;
use App\Support\ResultReportLanguage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers the short madrassa result PDF.
 *
 * Three documents now exist and the point of these tests is that they stay
 * three: the short result PDF for one student, the same short document for
 * a filtered group, and the long detailed track record. The bug being
 * fixed was the first two quietly opening the third, so several of these
 * assert on which route a page points at as much as on what comes back.
 *
 * The PDF bytes are not parsed. What is asserted is that a real inline PDF
 * comes back from the right route, that the group covers the right
 * students, and - through the shared report builder - that the figures
 * behind it are the existing modules' figures rather than new ones.
 */
class MadrassaShortResultPdfTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMadrassa();

        // Inside the fixtures' 2026-04-01 to 2027-03-31 session, so "a
        // session still running" is stable rather than calendar-dependent.
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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

    /**
     * Count the pages in a rendered PDF.
     *
     * dompdf writes one "/Type /Page" object per page and a single
     * "/Type /Pages" tree node, so the trailing character keeps the tree
     * node out of the count. Not a guess about the library's internals: it
     * is the page tree the PDF spec requires.
     */
    /**
     * Render the short report view the way the controller does.
     *
     * The view takes its labels, direction and font from the language, so a
     * test that renders it directly has to supply the same things.
     */
    private function renderShortReport(MadrassaStudentReport $report, string $language): string
    {
        return View::make('results.pdf.short-result', [
            'reports' => [$report],
            'terms' => StudentResult::TERMS,
            'termLabel' => StudentResult::TERM_FIRST,
            'testType' => StudentResult::TEST_GRAND,
            'heading' => [
                'session' => $this->session,
                'department' => null,
                'academicClass' => null,
                'section' => null,
                'student' => null,
                'search' => null,
            ],
            'capped' => false,
            'generatedAt' => now(),
            'language' => $language,
            't' => ResultReportLanguage::translator($language),
            'direction' => ResultReportLanguage::direction($language),
            'fontFamily' => ResultReportLanguage::fontFamily($language),
        ])->render();
    }

    private function pdfPages(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    private function assertInlinePdf($response): void
    {
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $disposition = $response->headers->get('Content-Disposition');

        $this->assertStringStartsWith('inline;', $disposition);
        $this->assertStringNotContainsString('attachment', $disposition);
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    /* ---------------------------------------------------------------- */
    /* 1-2: the row PDF action */
    /* ---------------------------------------------------------------- */

    public function test_the_row_pdf_action_generates_the_short_result_pdf(): void
    {
        $student = $this->student('Fawad Ahmed');
        $enrollment = $this->madrassaEnrollment($student);
        $this->storedResult($enrollment);

        $this->assertInlinePdf($this->get(route('students.results.short-pdf', $student)));
    }

    public function test_the_results_page_row_pdf_button_points_at_the_short_report(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        $content = $this->get(route('results.index'))->assertOk()->getContent();

        // The row action opens the short report...
        $this->assertStringContainsString(
            route('students.results.short-pdf', ['student' => $student->id, 'term' => StudentResult::TERM_FIRST]),
            $content
        );

        // ...and not the long detailed track record, which is what the bug
        // was. Checked with the closing quote so the short URL, which has
        // no such prefix, cannot satisfy it by accident.
        $this->assertStringNotContainsString(
            route('students.results.pdf', $student->id).'"',
            $content
        );
    }

    public function test_the_short_and_detailed_reports_are_different_documents(): void
    {
        $student = $this->student('Fawad Ahmed');
        $enrollment = $this->madrassaEnrollment($student);
        $this->storedResult($enrollment);

        MadrassaDailyRecord::create([
            'student_academic_enrollment_id' => $enrollment->id,
            'record_date' => '2026-09-07',
            'record_type' => MadrassaDailyRecord::TYPE_HIFZ,
            'sabaq' => 'Surah Al-Baqarah',
            'sabaq_quantity' => '1 page',
        ]);

        $short = $this->get(route('students.results.short-pdf', $student));
        $detailed = $this->get(route('students.results.pdf', $student));

        $this->assertInlinePdf($short);
        $this->assertInlinePdf($detailed);

        // The short report leaves out the daily record, prayer and
        // attendance histories, so it is materially smaller. If the two
        // ever converge in size again, the row action is pointing at the
        // wrong document.
        $this->assertLessThan(
            strlen($detailed->getContent()),
            strlen($short->getContent())
        );
    }

    public function test_a_school_only_student_has_no_short_result_pdf(): void
    {
        $student = $this->student('Usman Tariq', 'School');
        $this->schoolEnrollment($student);

        $this->get(route('students.results.short-pdf', $student))->assertNotFound();
    }

    public function test_guests_cannot_reach_the_short_result_pdf(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->madrassaEnrollment($student);

        auth()->logout();

        $this->get(route('students.results.short-pdf', $student))->assertRedirect(route('login'));
        $this->get(route('results.reports.pdf'))->assertRedirect(route('login'));
    }

    /* ---------------------------------------------------------------- */
    /* 3-5: the group printable report */
    /* ---------------------------------------------------------------- */

    public function test_the_printable_report_button_points_at_the_group_short_report(): void
    {
        $this->storedResult($this->madrassaEnrollment($this->student('Fawad Ahmed')));

        $this->get(route('results.reports'))
            ->assertOk()
            ->assertSee(route('results.reports.pdf', ['term' => StudentResult::TERM_FIRST]), false);
    }

    public function test_the_group_report_covers_every_filtered_student(): void
    {
        $fawad = $this->student('Fawad Ahmed');
        $other = $this->student('Zaid Anwar');

        // Both in Nazra, so a class filter returns the pair.
        $this->storedResult($this->madrassaEnrollment($fawad, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]), ['obtained_marks' => 61]);

        $this->storedResult($this->madrassaEnrollment($other, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]), ['obtained_marks' => 72]);

        // And one outside the filter.
        $this->storedResult($this->madrassaEnrollment($this->student('Hamza Iqbal')));

        $response = $this->get(route('results.reports.pdf', [
            'academic_class_id' => $this->nazra->id,
        ]));

        $this->assertInlinePdf($response);

        // Two students' worth of document rather than one. Compared against
        // the same report narrowed to a single student, so the assertion is
        // about the group growing rather than about an absolute size.
        $single = $this->get(route('results.reports.pdf', [
            'academic_class_id' => $this->nazra->id,
            'student_id' => $fawad->id,
        ]));

        $this->assertInlinePdf($single);
        $this->assertLessThan(strlen($response->getContent()), strlen($single->getContent()));
    }

    /* ---------------------------------------------------------------- */
    /* 4: the filters */
    /* ---------------------------------------------------------------- */

    public function test_the_group_report_respects_every_filter(): void
    {
        $this->madrassaEnrollment($this->student('Hamza Iqbal'));

        $nazra = $this->student('Zaid Anwar');
        $this->madrassaEnrollment($nazra, ['academic_class_id' => $this->nazra->id, 'section_id' => null]);

        $sectionB = $this->student('Bilal Ahmad');
        $this->madrassaEnrollment($sectionB, ['section_id' => $this->hifzB->id]);

        $dars = $this->student('Kamran Ali', 'Dars-e-Nizami');
        $this->darsEnrollment($dars);

        $nextSession = $this->student('Future Student');
        $this->madrassaEnrollment($nextSession, ['academic_session_id' => $this->nextSession->id]);

        // Each filter on its own reaches the report and comes back with a
        // document rather than an error.
        foreach ([
            ['academic_session_id' => $this->nextSession->id],
            ['department_id' => $this->darsENizami->id],
            ['academic_class_id' => $this->nazra->id],
            ['section_id' => $this->hifzB->id],
            ['term' => StudentResult::TERM_FINAL],
            ['search' => 'Zaid'],
        ] as $filter) {
            $this->assertInlinePdf($this->get(route('results.reports.pdf', $filter)));
        }
    }

    public function test_the_search_filter_narrows_the_group_report(): void
    {
        $this->storedResult($this->madrassaEnrollment($this->student('Zaid Anwar')));
        $this->storedResult($this->madrassaEnrollment($this->student('Hamza Iqbal')));
        $this->storedResult($this->madrassaEnrollment($this->student('Bilal Ahmad')));

        $all = $this->get(route('results.reports.pdf'));
        $searched = $this->get(route('results.reports.pdf', ['search' => 'Zaid']));

        $this->assertInlinePdf($all);
        $this->assertInlinePdf($searched);

        // One student rather than three.
        $this->assertLessThan(strlen($all->getContent()), strlen($searched->getContent()));
    }

    public function test_the_filters_combine_with_and_logic(): void
    {
        $target = $this->student('Zaid Anwar');
        $this->storedResult($this->madrassaEnrollment($target, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]));

        // Right class, wrong name.
        $this->storedResult($this->madrassaEnrollment($this->student('Hamza Iqbal'), [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]));

        $both = $this->get(route('results.reports.pdf', ['academic_class_id' => $this->nazra->id]));
        $anded = $this->get(route('results.reports.pdf', [
            'academic_class_id' => $this->nazra->id,
            'search' => 'Zaid',
        ]));

        $this->assertInlinePdf($both);
        $this->assertInlinePdf($anded);
        $this->assertLessThan(strlen($both->getContent()), strlen($anded->getContent()));

        // A class the student is not in matches nothing rather than one
        // filter quietly winning over the other.
        $none = $this->get(route('results.reports.pdf', [
            'academic_class_id' => $this->hifzClass->id,
            'search' => 'Zaid',
        ]));

        $this->assertInlinePdf($none);
        $this->assertLessThan(strlen($anded->getContent()), strlen($none->getContent()));
    }

    /* ---------------------------------------------------------------- */
    /* 6-8: Madrassa-only scope */
    /* ---------------------------------------------------------------- */

    public function test_a_school_only_student_never_appears_in_the_group_report(): void
    {
        $this->storedResult($this->madrassaEnrollment($this->student('Fawad Ahmed')));

        $school = $this->student('Usman Tariq', 'School');
        $schoolEnrollment = $this->schoolEnrollment($school);

        // Written straight onto the school enrollment, bypassing every rule
        // the write paths enforce.
        $this->storedResult($schoolEnrollment, ['obtained_marks' => 72]);

        $withMadrassa = $this->get(route('results.reports.pdf'));
        $this->assertInlinePdf($withMadrassa);

        // Asking for the school student by every id the report might have
        // been tempted to read returns a document with nobody in it.
        $manipulated = $this->get(route('results.reports.pdf', [
            'academic_track' => 'School',
            'student_id' => $school->id,
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
        ]));

        $this->assertInlinePdf($manipulated);
        $this->assertLessThan(strlen($withMadrassa->getContent()), strlen($manipulated->getContent()));
    }

    public function test_a_hifz_plus_school_student_is_reported_through_the_madrassa_side_only(): void
    {
        $student = $this->student('Ali Raza', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->storedResult($madrassa, ['obtained_marks' => 61]);
        $this->storedResult($school, ['obtained_marks' => 99]);

        $this->assertInlinePdf($this->get(route('students.results.short-pdf', $student)));

        // One placement and one result reach the document, and it is the
        // madrassa one.
        $report = new MadrassaStudentReport($student, $this->session);

        $this->assertSame(1, $report->enrollments()->count());
        $this->assertSame($madrassa->id, $report->enrollments()->first()->id);
        $this->assertSame(
            '61.00',
            (string) $report->resultsByTerm()[StudentResult::TERM_FIRST]->obtained_marks
        );
    }

    /* ---------------------------------------------------------------- */
    /* 9-11: the result content */
    /* ---------------------------------------------------------------- */

    public function test_the_first_term_result_is_reported(): void
    {
        $student = $this->student('Fawad Ahmed');
        $enrollment = $this->madrassaEnrollment($student);
        $this->storedResult($enrollment, ['obtained_marks' => 85]);

        $this->assertInlinePdf($this->get(route('students.results.short-pdf', [
            'student' => $student,
            'term' => StudentResult::TERM_FIRST,
        ])));

        $result = (new MadrassaStudentReport($student, $this->session))
            ->resultsByTerm()[StudentResult::TERM_FIRST];

        $this->assertSame('85.00', (string) $result->percentage);
        $this->assertSame('A', $result->grade);
    }

    public function test_the_final_term_result_is_reported_separately(): void
    {
        $student = $this->student('Fawad Ahmed');
        $enrollment = $this->madrassaEnrollment($student);

        $this->storedResult($enrollment, ['obtained_marks' => 82]);
        $this->storedResult($enrollment, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 91,
            'result_date' => '2027-03-15',
        ]);

        $this->assertInlinePdf($this->get(route('students.results.short-pdf', [
            'student' => $student,
            'term' => StudentResult::TERM_FINAL,
        ])));

        // All Terms prints both, and they stay separate.
        $bothTerms = $this->get(route('students.results.short-pdf', [
            'student' => $student,
            'term' => 'All Terms',
        ]));

        $this->assertInlinePdf($bothTerms);

        $byTerm = (new MadrassaStudentReport($student, $this->session))->resultsByTerm();

        $this->assertSame('82.00', (string) $byTerm[StudentResult::TERM_FIRST]->percentage);
        $this->assertSame('91.00', (string) $byTerm[StudentResult::TERM_FINAL]->percentage);
    }

    public function test_the_percentage_and_grade_come_from_the_existing_logic(): void
    {
        $student = $this->student('Fawad Ahmed');
        $enrollment = $this->madrassaEnrollment($student);

        // 45 out of 100 is a C under the approved ladder, and a C passes.
        $result = $this->storedResult($enrollment, ['obtained_marks' => 45]);

        $this->assertSame('45.00', (string) $result->percentage);
        $this->assertSame('C', $result->grade);
        $this->assertTrue(GradeScale::isPassing($result->grade));
        $this->assertSame(
            'Passed',
            MadrassaStudentReport::statusFor($result)
        );

        $this->assertInlinePdf($this->get(route('students.results.short-pdf', $student)));
    }

    /* ---------------------------------------------------------------- */
    /* The compact summary and month table */
    /* ---------------------------------------------------------------- */

    public function test_the_short_report_carries_the_compact_summary_and_months(): void
    {
        $student = $this->student('Fawad Ahmed');
        $enrollment = $this->madrassaEnrollment($student);
        $this->storedResult($enrollment);

        StudentAttendance::create([
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => '2026-08-03',
            'attendance_period' => 'Morning',
            'status' => StudentAttendance::STATUS_PRESENT,
        ]);

        MadrassaDailyRecord::create([
            'student_academic_enrollment_id' => $enrollment->id,
            'record_date' => '2026-08-03',
            'record_type' => MadrassaDailyRecord::TYPE_HIFZ,
            'sabaq' => 'Surah Al-Baqarah',
            'sabaq_quantity' => '1 page',
            'manzil' => 'Para 1 to 5',
        ]);

        $this->assertInlinePdf($this->get(route('students.results.short-pdf', $student)));

        // The figures the compact summary and the month table are rendered
        // from, taken from the existing registers rather than recomputed.
        $report = new MadrassaStudentReport($student, $this->session);

        $this->assertSame(1, $report->attendanceSummary()['present']);
        $this->assertSame(1, $report->progressSummary()['prepared_lessons']);
        $this->assertSame(1, $report->progressSummary()['manzil']);

        $months = collect($report->months())->keyBy('key');

        // The session's twelve months, in order, April through March.
        $this->assertCount(12, $months);
        $this->assertSame(1, $months['2026-08']['present']);
        $this->assertSame(1, $months['2026-08']['prepared_lessons']);
    }

    /* ---------------------------------------------------------------- */
    /* 12: the detailed report is untouched */
    /* ---------------------------------------------------------------- */

    public function test_the_detailed_student_performance_pdf_still_works(): void
    {
        $student = $this->student('Fawad Ahmed');
        $enrollment = $this->madrassaEnrollment($student);
        $this->storedResult($enrollment);

        $this->assertInlinePdf($this->get(route('students.results.pdf', $student)));
    }

    /* ---------------------------------------------------------------- */
    /* Layout: the document has to be compact */
    /* ---------------------------------------------------------------- */

    public function test_one_students_short_report_fits_on_a_page_or_two(): void
    {
        $student = $this->student('Fawad Ahmed');
        $enrollment = $this->madrassaEnrollment($student);

        $this->storedResult($enrollment, ['obtained_marks' => 85]);
        $this->storedResult($enrollment, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 91,
            'result_date' => '2027-03-15',
        ]);

        // Enough of a register behind it that the summary and the month
        // table have real numbers to print.
        foreach (['2026-08-03', '2026-08-04', '2026-09-07'] as $date) {
            StudentAttendance::create([
                'student_academic_enrollment_id' => $enrollment->id,
                'attendance_date' => $date,
                'attendance_period' => 'Morning',
                'status' => StudentAttendance::STATUS_PRESENT,
            ]);

            MadrassaDailyRecord::create([
                'student_academic_enrollment_id' => $enrollment->id,
                'record_date' => $date,
                'record_type' => MadrassaDailyRecord::TYPE_HIFZ,
                'sabaq' => 'Surah Al-Baqarah',
                'sabaq_quantity' => '1 page',
                'sabqi' => 'Para 3',
                'manzil' => 'Para 1 to 5',
            ]);
        }

        $pdf = $this->get(route('students.results.short-pdf', $student))->assertOk()->getContent();

        // The bug this guards against produced eleven pages for exactly
        // this document, most of them holding a line or two: a float inside
        // the fixed footer was pushing every table down a page at a time.
        // Two is the agreed ceiling, and the month table is the only thing
        // entitled to reach for the second page.
        $this->assertLessThanOrEqual(
            2,
            $this->pdfPages($pdf),
            'The short result report has grown past two pages for one student.'
        );
    }

    public function test_a_group_report_costs_about_a_page_per_student(): void
    {
        foreach (['Fawad Ahmed', 'Zaid Anwar'] as $name) {
            $this->storedResult($this->madrassaEnrollment($this->student($name), [
                'academic_class_id' => $this->nazra->id,
                'section_id' => null,
            ]));
        }

        $pdf = $this->get(route('results.reports.pdf', ['academic_class_id' => $this->nazra->id]))
            ->assertOk()
            ->getContent();

        // One controlled break between the two students and none after the
        // last, so two students are two pages rather than two plus a blank
        // sheet.
        $this->assertLessThanOrEqual(
            4,
            $this->pdfPages($pdf),
            'The group report is spending more than two pages per student.'
        );

        $this->assertGreaterThanOrEqual(2, $this->pdfPages($pdf));
    }

    public function test_the_short_report_uses_its_own_layout(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        $report = new MadrassaStudentReport($student, $this->session);

        $html = $this->renderShortReport($report, ResultReportLanguage::ENGLISH);

        // The footer lays itself out without floats, which is the fix, and
        // the students are separated by the sibling rule rather than by a
        // break after every block.
        $this->assertStringContainsString('footer-bar', $html);
        $this->assertStringContainsString('student-report', $html);
        $this->assertStringNotContainsString('float: left', $html);
        $this->assertStringNotContainsString('float: right', $html);
        $this->assertStringNotContainsString('page-break-after: always', $html);

        // English keeps dompdf's page box and its CSS page counters.
        $this->assertStringContainsString('@page', $html);
        $this->assertStringContainsString('counter(page)', $html);

        // Every section is still on the page.
        // The headings the report prints in English. They come from the
        // language file now, which is why the wording moved.
        foreach ([
            'Student Information', 'Registration No.', 'Result',
            'Attendance &amp; Progress Summary', 'Track Record', 'Monthly Report',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function test_the_detailed_report_still_uses_the_shared_layout(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        // The shared layout is untouched, floats and all, so the detailed
        // report renders exactly as it did before the short report's layout
        // was split off.
        $shared = file_get_contents(resource_path('views/results/pdf/layout.blade.php'));

        $this->assertStringContainsString('float: left', $shared);
        $this->assertStringContainsString("@extends('results.pdf.layout'", file_get_contents(
            resource_path('views/results/pdf/student-performance.blade.php')
        ));

        $this->assertInlinePdf($this->get(route('students.results.pdf', $student)));
    }

    /* ---------------------------------------------------------------- */
    /* Language: English and Urdu */
    /* ---------------------------------------------------------------- */

    public function test_the_english_pdf_still_generates(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        // Both the explicit and the default form.
        $this->assertInlinePdf($this->get(route('students.results.short-pdf', $student)));
        $this->assertInlinePdf($this->get(route('students.results.short-pdf', [
            'student' => $student,
            'language' => ResultReportLanguage::ENGLISH,
        ])));
        $this->assertInlinePdf($this->get(route('results.reports.pdf', [
            'language' => ResultReportLanguage::ENGLISH,
        ])));
    }

    public function test_the_urdu_pdf_generates(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        $single = $this->get(route('students.results.short-pdf', [
            'student' => $student,
            'language' => ResultReportLanguage::URDU,
        ]));

        $this->assertInlinePdf($single);

        $group = $this->get(route('results.reports.pdf', [
            'language' => ResultReportLanguage::URDU,
        ]));

        $this->assertInlinePdf($group);
    }

    public function test_the_urdu_pdf_embeds_a_font_that_can_draw_urdu(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        $pdf = $this->get(route('students.results.short-pdf', [
            'student' => $student,
            'language' => ResultReportLanguage::URDU,
        ]))->assertOk()->getContent();

        preg_match_all('#/BaseFont\s*/([A-Za-z0-9+\-_]+)#', $pdf, $fonts);
        $embedded = implode(' ', array_unique($fonts[1]));

        // XB Riyaz carries the Arabic-script glyphs and the OpenType tables
        // Urdu needs. DejaVu, which the English report uses, has no Arabic
        // script at all - an Urdu report set in it would be empty boxes.
        $this->assertStringContainsString('Riyaz', $embedded);
    }

    public function test_urdu_letters_are_shaped_rather_than_left_disconnected(): void
    {
        // This is the whole reason Urdu is rendered by a different engine.
        // "کک" is the same letter twice; drawn correctly it is two different
        // glyphs - an initial form and a final one - because Arabic-script
        // letters change shape by position. A renderer that does no shaping
        // draws the same glyph twice.
        $glyphs = $this->urduGlyphs('کک');

        $this->assertCount(2, $glyphs, 'Expected two glyphs for a two-letter word.');
        $this->assertNotSame(
            $glyphs[0],
            $glyphs[1],
            'Both letters drew the same glyph, so the Urdu text was not shaped.'
        );

        // And a middle letter takes a third, medial form.
        $three = $this->urduGlyphs('ککک');

        $this->assertCount(3, $three);
        $this->assertSame($glyphs[0], $three[0]);
        $this->assertSame($glyphs[1], $three[2]);
        $this->assertNotSame($three[0], $three[1]);
        $this->assertNotSame($three[1], $three[2]);
    }

    /**
     * Draw a word through the Urdu engine and return the glyph codes used.
     *
     * @return array<int, string>
     */
    private function urduGlyphs(string $word): array
    {
        $tmp = storage_path('app/mpdf');

        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tmp,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $mpdf->WriteHTML('<html><head><meta charset="utf-8"></head><body>'
            .'<p dir="rtl" style="font-family: xbriyaz; font-size: 20pt;">'.$word.'</p>'
            .'</body></html>');

        $pdf = $mpdf->Output('', Destination::STRING_RETURN);

        preg_match_all('#stream\r?\n#', $pdf, $hits, PREG_OFFSET_CAPTURE);

        foreach ($hits[0] as $hit) {
            $start = $hit[1] + strlen($hit[0]);
            $end = strpos($pdf, 'endstream', $start);
            $raw = substr($pdf, $start, $end - $start);

            $inflated = @gzuncompress($raw);
            if ($inflated === false) {
                $inflated = @gzinflate($raw);
            }
            if ($inflated === false) {
                $inflated = $raw;
            }

            if (preg_match('#\[\((.*?)\)\]\s*TJ#s', $inflated, $run)) {
                $text = preg_replace_callback('#\\\\([nrtbf()\\\\]|[0-7]{1,3})#', function ($m) {
                    return match ($m[1]) {
                        'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\b", 'f' => "\f",
                        '(' => '(', ')' => ')', '\\' => '\\',
                        default => chr(octdec($m[1])),
                    };
                }, $run[1]);

                // Two bytes per glyph in mPDF's subset encoding.
                return str_split(bin2hex($text), 4);
            }
        }

        return [];
    }

    public function test_an_invalid_language_falls_back_to_english(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        $english = $this->get(route('students.results.short-pdf', $student))
            ->assertOk()->getContent();

        foreach (['zz', 'fr', '', '../ur', '<script>', 'URDU'] as $bogus) {
            $response = $this->get(route('students.results.short-pdf', [
                'student' => $student,
                'language' => $bogus,
            ]));

            $this->assertInlinePdf($response);

            // Byte-for-byte the English report, apart from the timestamp
            // dompdf writes into every document.
            $this->assertSame(
                strlen($english),
                strlen($response->getContent()),
                "The language \"{$bogus}\" did not fall back to the English report."
            );
        }

        // And the rule itself, stated once.
        $this->assertSame(ResultReportLanguage::ENGLISH, ResultReportLanguage::normalize('zz'));
        $this->assertSame(ResultReportLanguage::ENGLISH, ResultReportLanguage::normalize(null));
        $this->assertSame(ResultReportLanguage::URDU, ResultReportLanguage::normalize('ur'));
    }

    public function test_english_labels_appear_in_the_english_report(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        $html = $this->renderShortReport(
            new MadrassaStudentReport($student, $this->session),
            ResultReportLanguage::ENGLISH
        );

        foreach ([
            'Student Information', 'First Term', 'Final Term', 'Total Marks',
            'Obtained Marks', 'Percentage', 'Grade', 'Status', 'Working Days',
            'Present Days', 'Absent Days', 'Prepared Lessons', 'Unprepared Lessons',
            'Unprepared Sabqi', 'Manzil', 'Track Record', 'Period', 'Track / Class',
            'Monthly Report',
        ] as $label) {
            $this->assertStringContainsString($label, $html, "Missing English label: {$label}");
        }
    }

    public function test_urdu_labels_appear_in_the_urdu_report(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        $html = $this->renderShortReport(
            new MadrassaStudentReport($student, $this->session),
            ResultReportLanguage::URDU
        );

        foreach ([
            'طالب علم کی معلومات', 'پہلا ٹرم', 'آخری ٹرم', 'کل نمبر',
            'حاصل کردہ نمبر', 'فیصد', 'گریڈ', 'حالت', 'حاضر ایام',
            'غیر حاضر ایام', 'تیار سبق', 'غیر تیار سبق', 'غیر تیار سبقی',
            'منزل', 'تعلیمی سفر', 'مدت', 'ماہانہ رپورٹ',
        ] as $label) {
            $this->assertStringContainsString($label, $html, "Missing Urdu label: {$label}");
        }

        // The document declares itself right to left, and no English
        // heading is left behind in it.
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringNotContainsString('Student Information', $html);
        $this->assertStringNotContainsString('Monthly Report', $html);
    }

    public function test_the_urdu_layout_leaves_out_the_css_dompdf_only_understands(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        $html = $this->renderShortReport(
            new MadrassaStudentReport($student, $this->session),
            ResultReportLanguage::URDU
        );

        // mPDF takes its margins from the constructor. Handed an @page rule
        // as well it adds the two together, which collapses the usable
        // height and spreads a one-page report over hundreds of pages.
        $this->assertStringNotContainsString('@page', $html);

        // And it does not implement the CSS page counters, so the Urdu
        // footer uses mPDF's own placeholders.
        $this->assertStringNotContainsString('counter(page)', $html);
        $this->assertStringContainsString('{PAGENO}', $html);
    }

    public function test_every_label_is_translated_in_both_languages(): void
    {
        $english = ResultReportLanguage::labels(ResultReportLanguage::ENGLISH);
        $urdu = ResultReportLanguage::labels(ResultReportLanguage::URDU);

        // Same keys either way, so a heading cannot exist in one language
        // and quietly fall back to the other's wording on a report.
        $this->assertSame(array_keys($english), array_keys($urdu));

        foreach ($english as $key => $value) {
            $this->assertNotSame(
                $value,
                $urdu[$key],
                "The label \"{$key}\" is still English on the Urdu report."
            );
        }
    }

    public function test_the_language_selector_is_offered_on_the_result_pages(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        foreach ([
            route('results.index'),
            route('results.reports'),
            route('students.results', $student),
        ] as $url) {
            $response = $this->get($url)->assertOk();

            $response->assertSee('English');
            $response->assertSee('اردو', false);
            $response->assertSee('language=ur', false);
        }
    }

    /* ---------------------------------------------------------------- */
    /* Track Record */
    /* ---------------------------------------------------------------- */

    public function test_one_placement_shows_a_single_track_record_row(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]));

        $html = $this->renderShortReport(
            new MadrassaStudentReport($student, $this->session),
            ResultReportLanguage::ENGLISH
        );

        $rows = $this->trackRecordRows($html);

        $this->assertCount(1, $rows);
        $this->assertStringContainsString($this->nazra->name, $rows[0]);
        // The placement has no section, and the report says so rather than
        // leaving the cell blank.
        $this->assertStringContainsString('No Section', $rows[0]);
    }

    public function test_multiple_placements_all_appear_in_chronological_order(): void
    {
        $student = $this->student('Fawad Ahmed');

        // The progression: Nazra first, then promoted into Hifz / Hifz-A.
        // The two placements sit in consecutive sessions because that is how
        // this schema records a promotion - one Madrassa enrollment per
        // student per session. With All Sessions chosen the report shows the
        // whole progression.
        $first = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
            'start_date' => '2026-04-01',
        ]);
        $first->update(['status' => 'Completed', 'end_date' => '2026-09-30']);

        $student->academicEnrollments()->create([
            'academic_session_id' => $this->nextSession->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'start_date' => '2026-10-01',
            'status' => 'Active',
        ]);

        // No session selected - the whole madrassa history.
        $html = $this->renderShortReport(
            new MadrassaStudentReport($student, null),
            ResultReportLanguage::ENGLISH
        );

        $rows = $this->trackRecordRows($html);

        $this->assertCount(2, $rows);

        // Earliest first, and each row keeps its own class and section.
        $this->assertStringContainsString($this->nazra->name, $rows[0]);
        $this->assertStringContainsString('No Section', $rows[0]);
        $this->assertStringContainsString('Apr 2026', $rows[0]);
        $this->assertStringContainsString('Sep 2026', $rows[0]);

        $this->assertStringContainsString($this->hifzClass->name, $rows[1]);
        $this->assertStringContainsString($this->hifzA->name, $rows[1]);
        $this->assertStringContainsString('Oct 2026', $rows[1]);

        // The older row is not rewritten with where the student sits now.
        $this->assertStringNotContainsString($this->hifzA->name, $rows[0]);
    }

    public function test_the_track_record_keeps_each_placement_historical_class(): void
    {
        $student = $this->student('Fawad Ahmed');

        $enrollment = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $track = (new MadrassaStudentReport($student, $this->session))->trackRecord();

        $this->assertCount(1, $track);
        $this->assertSame($this->nazra->name, $track[0]['class']);
        $this->assertNull($track[0]['section']);

        // Promote the student out of it. The old row must not follow them.
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

        $track = (new MadrassaStudentReport($student, $this->session))->trackRecord();

        $this->assertCount(1, $track);
        $this->assertSame($this->nazra->name, $track[0]['class']);
        $this->assertNotSame($this->hifzClass->name, $track[0]['class']);
    }

    public function test_the_track_record_excludes_other_sessions(): void
    {
        $student = $this->student('Fawad Ahmed');

        $first = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);
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

        // Selecting the first session shows that session's placement only.
        $track = (new MadrassaStudentReport($student, $this->session))->trackRecord();
        $this->assertCount(1, $track);
        $this->assertSame($this->nazra->name, $track[0]['class']);

        // And selecting the next one shows the other.
        $track = (new MadrassaStudentReport($student, $this->nextSession))->trackRecord();
        $this->assertCount(1, $track);
        $this->assertSame($this->hifzClass->name, $track[0]['class']);
    }

    public function test_the_track_record_never_shows_a_school_placement(): void
    {
        $student = $this->student('Ali Raza', 'Hifz + School');
        $this->madrassaEnrollment($student);
        $this->schoolEnrollment($student);

        $track = (new MadrassaStudentReport($student, $this->session))->trackRecord();

        // One row, and it is the madrassa one. The school placement holds
        // Primary Section / Primary-B and neither may appear.
        $this->assertCount(1, $track);
        $this->assertSame('Madrassa', $track[0]['enrollment']->academic_track);
        $this->assertSame($this->hifzClass->name, $track[0]['class']);

        $html = $this->renderShortReport(
            new MadrassaStudentReport($student, $this->session),
            ResultReportLanguage::ENGLISH
        );

        $rows = implode('', $this->trackRecordRows($html));

        $this->assertStringNotContainsString($this->primary->name, $rows);
        $this->assertStringNotContainsString($this->primaryB->name, $rows);
    }

    public function test_the_track_record_replaces_the_current_position_line(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        $html = $this->renderShortReport(
            new MadrassaStudentReport($student, $this->session),
            ResultReportLanguage::ENGLISH
        );

        $this->assertStringContainsString('Track Record', $html);
        $this->assertStringNotContainsString('Current Position', $html);

        // Everything else the report carries is still there.
        foreach ([
            'Student Information', 'Result', 'Total Marks', 'Obtained Marks',
            'Percentage', 'Grade', 'Status', 'Working Days', 'Present Days',
            'Absent Days', 'Prepared Lessons', 'Unprepared Lessons',
            'Unprepared Sabqi', 'Manzil', 'Monthly Report',
        ] as $label) {
            $this->assertStringContainsString($label, $html, "Lost section: {$label}");
        }
    }

    public function test_the_track_record_is_translated_into_urdu(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        $html = $this->renderShortReport(
            new MadrassaStudentReport($student, $this->session),
            ResultReportLanguage::URDU
        );

        $this->assertStringContainsString('تعلیمی سفر', $html);
        $this->assertStringContainsString('مدت', $html);
        $this->assertStringNotContainsString('Track Record', $html);

        $this->assertInlinePdf($this->get(route('students.results.short-pdf', [
            'student' => $student,
            'language' => ResultReportLanguage::URDU,
        ])));
    }

    public function test_the_track_record_reaches_the_generated_pdf(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        $this->assertInlinePdf($this->get(route('students.results.short-pdf', $student)));
        $this->assertInlinePdf($this->get(route('results.reports.pdf')));
    }

    /**
     * A student may hold more than one Madrassa placement in a session.
     *
     * This test used to assert the opposite, and its note said that if the
     * madrassa ever needed to record a mid-session move - Qaida to Nazra to
     * Hifz inside one year - the unique index would have to be relaxed
     * first. It has been: a madrassa stage is finished when the student
     * finishes it, so the placements a session-scoped Track Record shows are
     * exactly the stages the student passed through during it.
     *
     * The result module is unaffected. A result hangs off an enrollment id
     * and is unique on enrollment + term + test type, so each placement
     * carries its own term results and nothing became ambiguous.
     */
    public function test_two_madrassa_placements_in_one_session_are_recorded_separately(): void
    {
        $student = $this->student('Fawad Ahmed');

        $nazra = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
            'end_date' => '2026-09-30',
            'status' => 'Completed',
        ]);

        $hifz = $student->academicEnrollments()->create([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'start_date' => '2026-10-01',
            'status' => 'Active',
        ]);

        $this->assertSame(2, $student->academicEnrollments()->count());

        // A term result on each placement: the unique index is per
        // enrollment, so neither collides with the other.
        $this->storedResult($nazra);
        $this->storedResult($hifz);

        // Both stages appear in the Track Record, earliest first.
        $report = new MadrassaStudentReport($student->fresh(), $this->session);
        $trackRecord = $report->trackRecord();

        $this->assertCount(2, $trackRecord);
        $this->assertSame($this->nazra->id, $trackRecord[0]['enrollment']->academic_class_id);
        $this->assertSame($this->hifzClass->id, $trackRecord[1]['enrollment']->academic_class_id);

        $this->assertInlinePdf($this->get(route('students.results.short-pdf', $student)));
    }

    /**
     * Cut the Track Record table's rows out of the rendered report.
     *
     * @return array<int, string>
     */
    private function trackRecordRows(string $html): array
    {
        $start = strpos($html, 'Track Record');
        $this->assertNotFalse($start, 'The report has no Track Record section.');

        $section = substr($html, $start);
        $end = strpos($section, '</table>');

        if ($end === false) {
            return [];
        }

        $section = substr($section, 0, $end);

        // The body rows only: the head carries the column labels.
        $body = strpos($section, '<tbody>');
        if ($body === false) {
            return [];
        }

        preg_match_all('#<tr>(.*?)</tr>#s', substr($section, $body), $rows);

        return $rows[1];
    }

    /* ---------------------------------------------------------------- */
    /* Readability */
    /* ---------------------------------------------------------------- */

    public function test_the_report_is_set_in_a_readable_size(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        $html = $this->renderShortReport(
            new MadrassaStudentReport($student, $this->session),
            ResultReportLanguage::ENGLISH
        );

        // The report was set in 9px, which is legible on screen and poor on
        // paper. The body and the table text are the two that matter.
        preg_match('#body\s*\{[^}]*font-size:\s*([\d.]+)px#s', $html, $body);
        preg_match('#th,\s*td\s*\{[^}]*font-size:\s*([\d.]+)px#s', $html, $cells);

        $this->assertGreaterThanOrEqual(11, (float) $body[1]);
        $this->assertGreaterThanOrEqual(11, (float) $cells[1]);
    }

    public function test_the_detailed_report_is_still_reachable_from_the_student_pages(): void
    {
        $student = $this->student('Fawad Ahmed');
        $this->storedResult($this->madrassaEnrollment($student));

        // Its own action on the student's result history page.
        $this->get(route('students.results', $student))
            ->assertOk()
            ->assertSee('Student Detailed Report')
            ->assertSee(route('students.results.pdf', $student->id), false);

        // And on the reports page, alongside - not instead of - the short
        // result PDF.
        $reports = $this->get(route('results.reports'))->assertOk();

        $reports->assertSee('Detailed report (PDF)');
        $reports->assertSee('Result PDF');
        $reports->assertSee(route('students.results.pdf', $student->id), false);
    }
}
