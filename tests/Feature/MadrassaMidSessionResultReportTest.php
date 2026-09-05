<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers the result report for a madrassa student promoted mid-session.
 *
 * A madrassa student may hold several placements inside one academic
 * session, because a stage finishes when the student finishes it. They still
 * sit one Grand Test per session per term. The report has to reconcile the
 * two: a term already marked under the placement the student sat it in must
 * not be reported as still missing under the placement they were promoted
 * into, and a term nobody has marked must still be reported as missing.
 *
 * Nothing here moves a result between enrollments. Each result stays on the
 * placement its test was taken under, and the tests assert that it does.
 */
class MadrassaMidSessionResultReportTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMadrassa();
    }

    /**
     * Write a result straight to the table, against one placement.
     */
    private function storedResult(StudentAcademicEnrollment $enrollment, array $overrides = []): StudentResult
    {
        return StudentResult::create(array_merge([
            'student_academic_enrollment_id' => $enrollment->id,
            'term' => StudentResult::TERM_FIRST,
            'test_type' => StudentResult::TEST_GRAND,
            'total_marks' => 100,
            'obtained_marks' => 85,
            'result_date' => '2026-09-30',
        ], $overrides));
    }

    /**
     * A student who sat First Term in Nazra and was then promoted to Hifz,
     * both inside the running session.
     *
     * @return array{0: Student, 1: StudentAcademicEnrollment, 2: StudentAcademicEnrollment}
     */
    private function promotedMidSession(): array
    {
        $student = $this->student('Ahmed Ali');

        $nazra = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
            'start_date' => '2026-05-01',
            'end_date' => '2026-07-15',
            'status' => 'Completed',
        ]);

        $hifz = $this->madrassaEnrollment($student, [
            'start_date' => '2026-07-15',
            'status' => 'Active',
        ]);

        return [$student, $nazra, $hifz];
    }

    /**
     * Open the report.
     */
    private function report(array $filters = [])
    {
        return $this->get(route('results.reports', array_merge([
            'academic_session_id' => $this->session->id,
            'term' => StudentResult::TERM_FIRST,
        ], $filters)));
    }

    /**
     * The report rows for one student.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rowsFor($response, Student $student): array
    {
        return array_values(array_filter(
            $response->viewData('rows'),
            fn ($row) => $row['student']->id === $student->id
        ));
    }

    /* ---------------------------------------------------------------- */
    /* The single-placement cases, unchanged */
    /* ---------------------------------------------------------------- */

    public function test_a_student_with_no_result_is_reported_as_not_entered(): void
    {
        $student = $this->student('Ahmed Ali');
        $this->madrassaEnrollment($student);

        $response = $this->report()->assertOk();
        $rows = $this->rowsFor($response, $student);

        $this->assertCount(1, $rows);
        $this->assertSame('Not Entered', $rows[0]['status']);
        $this->assertSame(1, $response->viewData('summary')['students']);
        $this->assertSame(0, $response->viewData('summary')['entered']);
        $this->assertSame(1, $response->viewData('summary')['not_entered']);
    }

    public function test_a_student_with_a_result_is_reported_once_with_it(): void
    {
        $student = $this->student('Ahmed Ali');
        $this->storedResult($this->madrassaEnrollment($student));

        $response = $this->report()->assertOk();
        $rows = $this->rowsFor($response, $student);

        $this->assertCount(1, $rows);
        $this->assertSame('Passed', $rows[0]['status']);
        $this->assertSame(1, $response->viewData('summary')['entered']);
        $this->assertSame(0, $response->viewData('summary')['not_entered']);
    }

    /* ---------------------------------------------------------------- */
    /* The regression: a mid-session promotion */
    /* ---------------------------------------------------------------- */

    public function test_a_promoted_student_is_reported_once_for_a_term_already_marked(): void
    {
        [$student, $nazra] = $this->promotedMidSession();
        $this->storedResult($nazra);

        $response = $this->report()->assertOk();
        $rows = $this->rowsFor($response, $student);

        // Once, as Passed, under the placement the test was sat in - not a
        // second time as Not Entered under the placement they moved into.
        $this->assertCount(1, $rows);
        $this->assertSame('Passed', $rows[0]['status']);
        $this->assertSame($this->nazra->id, $rows[0]['enrollment']->academic_class_id);
    }

    public function test_a_promoted_student_does_not_inflate_the_summary(): void
    {
        [, $nazra] = $this->promotedMidSession();
        $this->storedResult($nazra);

        $summary = $this->report()->assertOk()->viewData('summary');

        $this->assertSame(1, $summary['students']);
        $this->assertSame(1, $summary['entered']);
        $this->assertSame(0, $summary['not_entered']);
        $this->assertSame(1, $summary['passed']);
        $this->assertSame(0, $summary['failed']);
    }

    public function test_the_result_stays_on_the_enrollment_it_was_recorded_against(): void
    {
        [, $nazra, $hifz] = $this->promotedMidSession();
        $result = $this->storedResult($nazra);

        $this->report()->assertOk();

        // Reporting reads; it never rewrites. The row is where it was put.
        $this->assertSame($nazra->id, $result->fresh()->student_academic_enrollment_id);
        $this->assertSame(1, StudentResult::count());
        $this->assertSame(0, StudentResult::where('student_academic_enrollment_id', $hifz->id)->count());

        // And both placements are still on file, untouched.
        $this->assertSame('Completed', $nazra->fresh()->status);
        $this->assertSame('Active', $hifz->fresh()->status);
    }

    /* ---------------------------------------------------------------- */
    /* A term nobody has marked is still reported as missing */
    /* ---------------------------------------------------------------- */

    public function test_a_term_with_no_result_anywhere_is_still_reported_as_missing(): void
    {
        [$student, $nazra] = $this->promotedMidSession();

        // First Term was sat in Nazra. Final Term has not been sat at all.
        $this->storedResult($nazra);

        $response = $this->report(['term' => StudentResult::TERM_FINAL])->assertOk();
        $rows = $this->rowsFor($response, $student);

        // The current placement is the one still missing it, and it is
        // reported - the exclusion is bounded by the term being reported.
        $this->assertCount(1, $rows);
        $this->assertSame('Not Entered', $rows[0]['status']);
        $this->assertSame($this->hifzClass->id, $rows[0]['enrollment']->academic_class_id);
        $this->assertSame(1, $response->viewData('summary')['not_entered']);
    }

    public function test_a_promoted_student_with_no_result_at_all_is_still_reported(): void
    {
        [$student] = $this->promotedMidSession();

        $response = $this->report()->assertOk();
        $rows = $this->rowsFor($response, $student);

        // Nothing marked anywhere, so nothing supersedes the active
        // placement: the student is listed once, as missing.
        $this->assertCount(1, $rows);
        $this->assertSame('Not Entered', $rows[0]['status']);
        $this->assertSame($this->hifzClass->id, $rows[0]['enrollment']->academic_class_id);
    }

    public function test_all_terms_reports_the_marked_term_once_and_the_missing_one_once(): void
    {
        [$student, $nazra] = $this->promotedMidSession();
        $this->storedResult($nazra);

        $response = $this->report(['term' => 'All Terms'])->assertOk();
        $rows = $this->rowsFor($response, $student);

        // Two rows for the student: one per term, not one per placement per
        // term. The marked term reads Passed, the unmarked one Not Entered.
        $this->assertCount(2, $rows);

        $byTerm = collect($rows)->keyBy('term');
        $this->assertSame('Passed', $byTerm[StudentResult::TERM_FIRST]['status']);
        $this->assertSame('Not Entered', $byTerm[StudentResult::TERM_FINAL]['status']);

        $summary = $this->report(['term' => 'All Terms'])->viewData('summary');
        $this->assertSame(1, $summary['students']);
        $this->assertSame(1, $summary['entered']);
        $this->assertSame(1, $summary['not_entered']);
    }

    /* ---------------------------------------------------------------- */
    /* Another student's placements never suppress this one's */
    /* ---------------------------------------------------------------- */

    public function test_another_students_result_does_not_suppress_this_students_row(): void
    {
        $marked = $this->student('Ahmed Ali');
        $this->storedResult($this->madrassaEnrollment($marked));

        $unmarked = $this->student('Bilal Ahmad');
        $this->madrassaEnrollment($unmarked, ['academic_class_id' => $this->nazra->id, 'section_id' => null]);

        $response = $this->report()->assertOk();

        $this->assertCount(1, $this->rowsFor($response, $marked));
        $this->assertCount(1, $this->rowsFor($response, $unmarked));
        $this->assertSame('Not Entered', $this->rowsFor($response, $unmarked)[0]['status']);
        $this->assertSame(2, $response->viewData('summary')['students']);
    }

    public function test_a_result_in_another_session_does_not_suppress_this_sessions_row(): void
    {
        $student = $this->student('Ahmed Ali');

        // Last year's placement, marked.
        $previous = $this->madrassaEnrollment($student, [
            'academic_session_id' => $this->nextSession->id,
            'status' => 'Completed',
            'end_date' => '2027-03-31',
        ]);
        $this->storedResult($previous);

        // This year's placement, not marked.
        $this->madrassaEnrollment($student, ['academic_class_id' => $this->nazra->id, 'section_id' => null]);

        $response = $this->report()->assertOk();
        $rows = $this->rowsFor($response, $student);

        // The suppression is scoped to one session, so this session's
        // missing result is still reported.
        $this->assertCount(1, $rows);
        $this->assertSame('Not Entered', $rows[0]['status']);
        $this->assertSame($this->nazra->id, $rows[0]['enrollment']->academic_class_id);
    }

    /* ---------------------------------------------------------------- */
    /* The school is not in this report at all */
    /* ---------------------------------------------------------------- */

    public function test_the_school_track_is_still_absent_from_the_report(): void
    {
        $student = $this->student('Bilal Ahmad', 'Hifz + School');
        $this->madrassaEnrollment($student);
        $schoolSide = $this->schoolEnrollment($student);

        $response = $this->report()->assertOk();
        $rows = $this->rowsFor($response, $student);

        // One row, the madrassa side. The school placement is outside this
        // report's boundary and stays outside it.
        $this->assertCount(1, $rows);
        $this->assertSame('Madrassa', $rows[0]['enrollment']->academic_track);
        $this->assertNotSame($schoolSide->id, $rows[0]['enrollment']->id);
    }

    public function test_a_school_placement_does_not_suppress_the_madrassa_row(): void
    {
        $student = $this->student('Bilal Ahmad', 'Hifz + School');
        $this->madrassaEnrollment($student);
        $this->schoolEnrollment($student);

        $rows = $this->rowsFor($this->report()->assertOk(), $student);

        $this->assertCount(1, $rows);
        $this->assertSame('Not Entered', $rows[0]['status']);
    }

    /* ---------------------------------------------------------------- */
    /* The printed report lists the same placements */
    /* ---------------------------------------------------------------- */

    public function test_the_printed_report_lists_the_promoted_student_once(): void
    {
        [, $nazra] = $this->promotedMidSession();
        $this->storedResult($nazra);

        $response = $this->get(route('results.reports.pdf', [
            'academic_session_id' => $this->session->id,
            'term' => StudentResult::TERM_FIRST,
        ]))->assertOk();

        $this->assertSame(
            'application/pdf',
            strtok((string) $response->headers->get('content-type'), ';')
        );
    }

    public function test_the_printed_report_and_the_page_list_the_same_placements(): void
    {
        [, $nazra] = $this->promotedMidSession();
        $this->storedResult($nazra);

        // A second student with nothing marked, so the comparison covers
        // both halves of the scope.
        $this->madrassaEnrollment($this->student('Bilal Ahmad'), [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $onScreen = collect($this->report()->assertOk()->viewData('rows'))
            ->pluck('enrollment.id')
            ->unique()
            ->sort()
            ->values()
            ->all();

        // The PDF controller builds its list from the same scope; asserting
        // the scope directly keeps this about the placements rather than
        // about PDF bytes.
        $fromScope = StudentAcademicEnrollment::query()
            ->where('academic_track', StudentResult::ACADEMIC_TRACK)
            ->forResultReport([StudentResult::TERM_FIRST])
            ->where('academic_session_id', $this->session->id)
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($onScreen, $fromScope);
        $this->assertCount(2, $fromScope);
    }
}
