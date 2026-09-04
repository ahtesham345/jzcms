<?php

namespace Tests\Feature;

use App\Models\StudentAcademicEnrollment;
use App\Models\StudentResult;
use App\Support\GradeScale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers the madrassa result report.
 *
 * The report answers two questions at once: what was scored, and who has
 * not been marked yet. The second is the one most of these tests are about,
 * because it is the one a join would quietly destroy - a student with no
 * result has to appear, as Not Entered, or the page cannot be used for the
 * job it exists for.
 *
 * The rest is arithmetic and boundaries. The average is a total over a
 * total rather than a mean of percentages; the pass mark is the configured
 * ladder's and not a second opinion; and nothing outside the madrassa track
 * can be reached however the query string is edited.
 */
class StudentResultReportTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMadrassa();
    }

    /**
     * Write a result straight to the table.
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
     * Cut one report row out of the page, by a value it carries.
     *
     * A page-wide assertion could be satisfied by a value sitting in
     * another student's row, or in a summary card.
     */
    private function reportRowFor(string $content, string $needle): string
    {
        $table = $this->reportTable($content);

        $start = strpos($table, $needle);
        $this->assertNotFalse($start, "No report row containing {$needle}.");

        $rowStart = strrpos(substr($table, 0, $start), '<tr');
        $rowEnd = strpos($table, '</tr>', $start);

        return substr($table, $rowStart, $rowEnd - $rowStart);
    }

    /**
     * Cut the report table out of the page.
     *
     * The summary cards above it carry marks and percentages of their own,
     * and the filter selects list every department, class and section in
     * the institution - including the school ones, because the selects are
     * the same component every other page uses. Neither is report data, so
     * assertions about what the report shows are made against this.
     */
    private function reportTable(string $content): string
    {
        return substr($content, strrpos($content, 'Student Results'));
    }

    /* ---------------------------------------------------------------- */
    /* 1-2: the page and its defaults */
    /* ---------------------------------------------------------------- */

    public function test_the_report_page_loads(): void
    {
        $this->madrassaEnrollment($this->student('Hamza Iqbal'));

        $this->get(route('results.reports'))
            ->assertOk()
            ->assertSee('Madrassa Result Report')
            ->assertSee('Grade Breakdown')
            ->assertSee('Hamza Iqbal');
    }

    public function test_the_current_session_and_first_term_are_the_defaults(): void
    {
        $student = $this->student('Hamza Iqbal');
        $current = $this->madrassaEnrollment($student);

        // A second placement in a session that is not the current one.
        $current->update(['status' => 'Completed', 'end_date' => '2027-03-31']);
        $other = $student->academicEnrollments()->create([
            'academic_session_id' => $this->nextSession->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'start_date' => '2027-04-01',
            'status' => 'Active',
        ]);

        $this->storedResult($current, ['obtained_marks' => 61]);
        $this->storedResult($other, ['obtained_marks' => 72]);

        // No parameters at all: the current session and First Term.
        $response = $this->get(route('results.reports'))->assertOk();

        $response->assertSee($this->session->name);
        $response->assertSee('61.00');
        $response->assertDontSee('72.00');
        $response->assertSee('Student Results — '.StudentResult::TERM_FIRST, false);
    }

    /* ---------------------------------------------------------------- */
    /* 3-7: the filters */
    /* ---------------------------------------------------------------- */

    public function test_the_term_filter_works(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Hamza Iqbal'));

        $this->storedResult($enrollment, ['obtained_marks' => 61]);
        $this->storedResult($enrollment, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 72,
            'result_date' => '2027-03-15',
        ]);

        $this->get(route('results.reports', ['term' => StudentResult::TERM_FIRST]))
            ->assertOk()
            ->assertSee('61.00')
            ->assertDontSee('72.00');

        $this->get(route('results.reports', ['term' => StudentResult::TERM_FINAL]))
            ->assertOk()
            ->assertSee('72.00')
            ->assertDontSee('61.00');
    }

    public function test_the_session_filter_works(): void
    {
        $hamza = $this->student('Hamza Iqbal');
        $bilal = $this->student('Bilal Ahmad');

        $this->storedResult($this->madrassaEnrollment($hamza), ['obtained_marks' => 61]);
        $this->storedResult(
            $this->madrassaEnrollment($bilal, ['academic_session_id' => $this->nextSession->id]),
            ['obtained_marks' => 72]
        );

        $this->get(route('results.reports', ['academic_session_id' => $this->nextSession->id]))
            ->assertOk()
            ->assertSee('Bilal Ahmad')
            ->assertSee('72.00')
            ->assertDontSee('Hamza Iqbal');

        // All Sessions is a deliberate choice, not an absent parameter, so
        // it must not snap back to the current session.
        $this->get(route('results.reports', ['academic_session_id' => '']))
            ->assertOk()
            ->assertSee('Hamza Iqbal')
            ->assertSee('Bilal Ahmad');
    }

    public function test_the_department_filter_works(): void
    {
        $this->madrassaEnrollment($this->student('Hamza Iqbal'));
        $this->darsEnrollment($this->student('Bilal Ahmad', 'Dars-e-Nizami'));

        $this->get(route('results.reports', ['department_id' => $this->darsENizami->id]))
            ->assertOk()
            ->assertSee('Bilal Ahmad')
            ->assertDontSee('Hamza Iqbal');
    }

    public function test_the_class_filter_works(): void
    {
        $this->madrassaEnrollment($this->student('Hamza Iqbal'));
        $this->madrassaEnrollment($this->student('Zaid Anwar'), [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $this->get(route('results.reports', ['academic_class_id' => $this->nazra->id]))
            ->assertOk()
            ->assertSee('Zaid Anwar')
            ->assertDontSee('Hamza Iqbal');
    }

    public function test_the_section_filter_works(): void
    {
        $this->madrassaEnrollment($this->student('Hamza Iqbal'));
        $this->madrassaEnrollment($this->student('Zaid Anwar'), ['section_id' => $this->hifzB->id]);

        $this->get(route('results.reports', ['section_id' => $this->hifzB->id]))
            ->assertOk()
            ->assertSee('Zaid Anwar')
            ->assertDontSee('Hamza Iqbal');
    }

    /* ---------------------------------------------------------------- */
    /* 8-11: search and AND logic */
    /* ---------------------------------------------------------------- */

    public function test_search_by_name_works(): void
    {
        $this->madrassaEnrollment($this->student('Hamza Iqbal'));
        $this->madrassaEnrollment($this->student('Zaid Anwar'));

        $this->get(route('results.reports', ['search' => 'Zaid']))
            ->assertOk()
            ->assertSee('Zaid Anwar')
            ->assertDontSee('Hamza Iqbal');
    }

    public function test_search_by_registration_number_works(): void
    {
        $hamza = $this->student('Hamza Iqbal');
        $zaid = $this->student('Zaid Anwar');

        $this->madrassaEnrollment($hamza);
        $this->madrassaEnrollment($zaid);

        $this->get(route('results.reports', ['search' => $zaid->registration_number]))
            ->assertOk()
            ->assertSee('Zaid Anwar')
            ->assertDontSee('Hamza Iqbal');
    }

    public function test_search_by_roll_number_works(): void
    {
        $hamza = $this->student('Hamza Iqbal');
        $zaid = $this->student('Zaid Anwar');

        $this->madrassaEnrollment($hamza);
        $this->madrassaEnrollment($zaid);

        $this->get(route('results.reports', ['search' => $zaid->roll_number]))
            ->assertOk()
            ->assertSee('Zaid Anwar')
            ->assertDontSee('Hamza Iqbal');
    }

    public function test_the_filters_combine_with_and_logic(): void
    {
        // The one the combined filter should find: Nazra, and named Zaid.
        $target = $this->student('Zaid Anwar');
        $this->madrassaEnrollment($target, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        // Right class, wrong name.
        $this->madrassaEnrollment($this->student('Hamza Iqbal'), [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        // Right name, wrong class.
        $this->madrassaEnrollment($this->student('Zaid Bukhari'));

        $this->get(route('results.reports', [
            'academic_class_id' => $this->nazra->id,
            'search' => 'Zaid',
        ]))
            ->assertOk()
            ->assertSee('Zaid Anwar')
            ->assertDontSee('Hamza Iqbal')
            ->assertDontSee('Zaid Bukhari');
    }

    /* ---------------------------------------------------------------- */
    /* 12-15: what the rows say */
    /* ---------------------------------------------------------------- */

    public function test_a_student_without_a_result_appears_as_not_entered(): void
    {
        $this->madrassaEnrollment($this->student('Hamza Iqbal'));

        $content = $this->get(route('results.reports'))->assertOk()->getContent();

        $row = $this->reportRowFor($content, 'Hamza Iqbal');

        // Listed, with the marks columns dashed rather than the student
        // being dropped from the report.
        $this->assertStringContainsString('Not Entered', $row);
        $this->assertStringContainsString(StudentResult::TERM_FIRST, $row);
        $this->assertSame(5, substr_count($row, '>-</td>'));
    }

    public function test_a_first_term_result_appears_correctly(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Hamza Iqbal'));
        $this->storedResult($enrollment, ['obtained_marks' => 85]);

        $row = $this->reportRowFor(
            $this->get(route('results.reports'))->assertOk()->getContent(),
            'Hamza Iqbal'
        );

        $this->assertStringContainsString(StudentResult::TERM_FIRST, $row);
        $this->assertStringContainsString(StudentResult::TEST_GRAND, $row);
        $this->assertStringContainsString('100.00', $row);
        $this->assertStringContainsString('85.00', $row);
        $this->assertStringContainsString('85.00%', $row);
        $this->assertStringContainsString('30 Sep, 2026', $row);
        $this->assertStringContainsString('Passed', $row);
    }

    public function test_a_final_term_result_appears_correctly(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Hamza Iqbal'));
        $this->storedResult($enrollment, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 92,
            'result_date' => '2027-03-15',
        ]);

        $row = $this->reportRowFor(
            $this->get(route('results.reports', ['term' => StudentResult::TERM_FINAL]))
                ->assertOk()
                ->getContent(),
            'Hamza Iqbal'
        );

        $this->assertStringContainsString(StudentResult::TERM_FINAL, $row);
        $this->assertStringContainsString('92.00%', $row);
        $this->assertStringContainsString('15 Mar, 2027', $row);
    }

    public function test_all_terms_keeps_the_two_terms_separate(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Hamza Iqbal'));

        $this->storedResult($enrollment, ['obtained_marks' => 82]);
        $this->storedResult($enrollment, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 91,
            'result_date' => '2027-03-15',
        ]);

        $content = $this->get(route('results.reports', ['term' => 'All Terms']))
            ->assertOk()
            ->getContent();

        // One row per term, each carrying its own marks and its own grade.
        $firstRow = $this->reportRowFor($content, '82.00%');
        $finalRow = $this->reportRowFor($content, '91.00%');

        $this->assertStringContainsString(StudentResult::TERM_FIRST, $firstRow);
        $this->assertStringNotContainsString('91.00%', $firstRow);

        $this->assertStringContainsString(StudentResult::TERM_FINAL, $finalRow);
        $this->assertStringNotContainsString('82.00%', $finalRow);

        // No row combined the two: 86.5% would be their mean and 173 the
        // sum of their marks, and neither belongs to a term.
        //
        // Asserted against the table rather than the page: the Average
        // Percentage card legitimately reads 173 out of 200 across both
        // reported results, which is the total-over-total figure the
        // summary is supposed to show.
        $table = $this->reportTable($content);

        $this->assertStringNotContainsString('86.50%', $table);
        $this->assertStringNotContainsString('173.00', $table);
    }

    /* ---------------------------------------------------------------- */
    /* 16-19: the summary arithmetic */
    /* ---------------------------------------------------------------- */

    public function test_the_grade_breakdown_counts_each_grade(): void
    {
        // One result in each of three bands of the approved ladder.
        $this->storedResult($this->madrassaEnrollment($this->student('A Plus')), ['obtained_marks' => 95]);
        $this->storedResult($this->madrassaEnrollment($this->student('B Plus')), ['obtained_marks' => 75]);
        $this->storedResult($this->madrassaEnrollment($this->student('Failing')), ['obtained_marks' => 30]);
        // And one student nobody has marked.
        $this->madrassaEnrollment($this->student('Unmarked'));

        $content = $this->get(route('results.reports'))->assertOk()->getContent();

        $breakdown = substr(
            $content,
            strpos($content, 'Grade Breakdown'),
            strpos($content, 'Apply Filters') - strpos($content, 'Grade Breakdown')
        );

        // The bands come from the configured ladder, so the panel lists
        // every one of them including the empty ones.
        foreach (array_keys(GradeScale::ladder()) as $grade) {
            $this->assertStringContainsString(">{$grade}</p>", $breakdown);
        }

        $countFor = function (string $grade) use ($breakdown) {
            $at = strpos($breakdown, ">{$grade}</p>");
            $this->assertNotFalse($at, "No breakdown tile for {$grade}.");

            preg_match('/>(\d+)<\/p>/', substr($breakdown, $at), $matches);

            return (int) $matches[1];
        };

        $this->assertSame(1, $countFor('A+'));
        $this->assertSame(1, $countFor('B+'));
        $this->assertSame(1, $countFor('F'));
        $this->assertSame(0, $countFor('A'));
        $this->assertSame(1, $countFor('Not Entered'));
    }

    public function test_the_passed_and_failed_counts_follow_the_configured_ladder(): void
    {
        // 95 is A+, 45 is C, 30 is F under the approved configuration.
        // A+ through C pass; only F fails.
        $this->storedResult($this->madrassaEnrollment($this->student('A Plus')), ['obtained_marks' => 95]);
        $this->storedResult($this->madrassaEnrollment($this->student('Just A C')), ['obtained_marks' => 45]);
        $this->storedResult($this->madrassaEnrollment($this->student('Failing')), ['obtained_marks' => 30]);
        $this->madrassaEnrollment($this->student('Unmarked'));

        $content = $this->get(route('results.reports'))->assertOk()->getContent();

        $this->assertSame(2, $this->cardValue($content, 'Passed'));
        $this->assertSame(1, $this->cardValue($content, 'Failed'));
        $this->assertSame(3, $this->cardValue($content, 'Results Entered'));
        $this->assertSame(1, $this->cardValue($content, 'Not Entered'));
        $this->assertSame(4, $this->cardValue($content, 'Madrassa Students'));

        // The rule itself, stated once and read from the ladder rather than
        // written down a second time here.
        $this->assertTrue(GradeScale::isPassing('C'));
        $this->assertFalse(GradeScale::isPassing('F'));
        $this->assertSame('F', GradeScale::failingGrade());
    }

    public function test_the_average_percentage_is_total_marks_based_not_a_mean_of_percentages(): void
    {
        // The example from the brief: 90/100 and 10/100.
        $this->storedResult($this->madrassaEnrollment($this->student('Student A')), [
            'total_marks' => 100,
            'obtained_marks' => 90,
        ]);
        $this->storedResult($this->madrassaEnrollment($this->student('Student B')), [
            'total_marks' => 100,
            'obtained_marks' => 10,
        ]);

        $this->get(route('results.reports'))
            ->assertOk()
            ->assertSee('50.00%');
    }

    public function test_the_average_percentage_weighs_papers_by_their_total_marks(): void
    {
        // Where a mean of percentages and a total over a total differ: 45/50
        // is 90% and 100/500 is 20%, whose mean is 55%, but 145 out of 550
        // is 26.36%. The report must report the second.
        $this->storedResult($this->madrassaEnrollment($this->student('Small Paper')), [
            'total_marks' => 50,
            'obtained_marks' => 45,
        ]);
        $this->storedResult($this->madrassaEnrollment($this->student('Big Paper')), [
            'total_marks' => 500,
            'obtained_marks' => 100,
        ]);

        $response = $this->get(route('results.reports'))->assertOk();

        $response->assertSee('26.36%');
        $response->assertDontSee('55.00%');
    }

    public function test_the_average_percentage_is_not_available_when_nothing_is_marked(): void
    {
        $this->madrassaEnrollment($this->student('Unmarked'));

        $this->get(route('results.reports'))
            ->assertOk()
            ->assertSee('N/A');
    }

    /**
     * Read the number off one of the summary cards.
     */
    private function cardValue(string $content, string $label): int
    {
        $at = strpos($content, ">{$label}</p>");
        $this->assertNotFalse($at, "No summary card labelled {$label}.");

        preg_match('/<p class="text-3xl[^"]*">\s*([\d.]+)\s*<\/p>/', substr($content, $at), $matches);

        return (int) $matches[1];
    }

    /* ---------------------------------------------------------------- */
    /* 20-21: the track boundary */
    /* ---------------------------------------------------------------- */

    public function test_a_school_only_student_never_appears(): void
    {
        $this->madrassaEnrollment($this->student('Hamza Iqbal'));

        $school = $this->student('Usman Tariq', 'School');
        $schoolEnrollment = $this->schoolEnrollment($school);

        // A result written straight onto the school enrollment, bypassing
        // every rule the write path enforces. Even so it must not appear:
        // the report is bounded by the track before anything else runs.
        $this->storedResult($schoolEnrollment, ['obtained_marks' => 72]);

        $response = $this->get(route('results.reports'))
            ->assertOk()
            ->assertSee('Hamza Iqbal')
            ->assertDontSee('Usman Tariq')
            ->assertDontSee('72.00');

        // The school class, checked against the table: the Class select
        // above it lists every class in the institution, which is the
        // shared filter component and not report data.
        $this->assertStringNotContainsString(
            $this->primary->name,
            $this->reportTable($response->getContent())
        );
    }

    public function test_a_hifz_plus_school_student_appears_once(): void
    {
        $student = $this->student('Ali Raza', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $this->schoolEnrollment($student);

        $this->storedResult($madrassa, ['obtained_marks' => 61]);

        $response = $this->get(route('results.reports'))->assertOk();

        $response->assertSee('Showing 1 to 1 of 1 Madrassa students');
        // Counted by the link to their history, which each row renders
        // exactly once.
        $this->assertSame(
            1,
            substr_count($response->getContent(), route('students.results', $student->id).'"')
        );
        $table = $this->reportTable($response->getContent());

        $this->assertStringContainsString($this->hifz->name, $table);
        $this->assertStringNotContainsString($this->primaryB->name, $table);
    }

    public function test_a_track_parameter_in_the_query_string_is_ignored(): void
    {
        $this->madrassaEnrollment($this->student('Hamza Iqbal'));
        $this->schoolEnrollment($this->student('Usman Tariq', 'School'));

        // Nothing on this page reads a track, so naming one changes nothing.
        $this->get(route('results.reports', [
            'academic_track' => 'School',
            'track' => 'School',
        ]))
            ->assertOk()
            ->assertSee('Hamza Iqbal')
            ->assertDontSee('Usman Tariq');
    }

    /* ---------------------------------------------------------------- */
    /* 22: historical placement */
    /* ---------------------------------------------------------------- */

    public function test_a_result_keeps_its_historical_class_and_section_after_a_promotion(): void
    {
        $student = $this->student('Hamza Iqbal');

        // First Term sat in Nazra, with no section.
        $first = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);
        $this->storedResult($first, ['obtained_marks' => 61]);

        // Promoted into Hifz / Hifz-B in the next session.
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

        // The default report is the current session, where the First Term
        // result lives. The placement it names must be the one the test was
        // sat in, not the one the student holds now.
        $row = $this->reportRowFor(
            $this->get(route('results.reports'))->assertOk()->getContent(),
            'Hamza Iqbal'
        );

        $this->assertStringContainsString($this->nazra->name, $row);
        $this->assertStringContainsString($this->session->name, $row);
        $this->assertStringContainsString('No section', $row);

        $this->assertStringNotContainsString($this->hifzB->name, $row);
        $this->assertStringNotContainsString($this->nextSession->name, $row);
    }

    /* ---------------------------------------------------------------- */
    /* 23-24: navigation */
    /* ---------------------------------------------------------------- */

    public function test_the_student_name_links_to_the_existing_history_page(): void
    {
        $student = $this->student('Hamza Iqbal');
        $this->storedResult($this->madrassaEnrollment($student), ['obtained_marks' => 61]);

        $this->get(route('results.reports'))
            ->assertOk()
            ->assertSee(route('students.results', $student->id), false);

        // And that page is the Chunk 2 one, still working.
        $this->get(route('students.results', $student))
            ->assertOk()
            ->assertSee('Result History');
    }

    public function test_a_result_links_to_the_existing_detail_page(): void
    {
        $result = $this->storedResult(
            $this->madrassaEnrollment($this->student('Hamza Iqbal')),
            ['obtained_marks' => 61]
        );

        $this->get(route('results.reports'))
            ->assertOk()
            ->assertSee(route('results.show', $result->id), false);

        $this->get(route('results.show', $result))
            ->assertOk()
            ->assertSee('Enrollment at Time of Result');
    }

    /* ---------------------------------------------------------------- */
    /* 25-27: pagination and access */
    /* ---------------------------------------------------------------- */

    public function test_the_report_paginates_at_twenty_five_students_and_preserves_filters(): void
    {
        for ($index = 0; $index < 26; $index++) {
            $this->madrassaEnrollment($this->student('Student '.str_pad((string) $index, 2, '0', STR_PAD_LEFT)));
        }

        $response = $this->get(route('results.reports', [
            'term' => StudentResult::TERM_FINAL,
            'search' => 'Student',
        ]))->assertOk();

        $response->assertSee('Showing 1 to 25 of 26 Madrassa students');

        // Every page link carries the filters, so paging through a filtered
        // report stays filtered rather than silently widening on page two.
        $response->assertSee('term='.rawurlencode(StudentResult::TERM_FINAL), false);
        $response->assertSee('search=Student', false);
        $response->assertSee('page=2', false);

        $this->get(route('results.reports', [
            'term' => StudentResult::TERM_FINAL,
            'search' => 'Student',
            'page' => 2,
        ]))
            ->assertOk()
            ->assertSee('Showing 26 to 26 of 26 Madrassa students');
    }

    public function test_guests_cannot_access_the_report(): void
    {
        $this->madrassaEnrollment($this->student('Hamza Iqbal'));

        auth()->logout();

        $this->get(route('results.reports'))->assertRedirect(route('login'));
    }

    public function test_a_manipulated_filter_cannot_expose_another_students_data(): void
    {
        $hamza = $this->student('Hamza Iqbal');
        $usman = $this->student('Usman Tariq', 'School');

        $this->storedResult($this->madrassaEnrollment($hamza), ['obtained_marks' => 61]);
        $schoolEnrollment = $this->schoolEnrollment($usman);
        $this->storedResult($schoolEnrollment, ['obtained_marks' => 72]);

        // Every id the report might have been tempted to read from a query
        // string, handed to it at once alongside the school department and
        // class. None of them can widen the report past the madrassa track.
        $this->get(route('results.reports', [
            'student_id' => $usman->id,
            'student_academic_enrollment_id' => $schoolEnrollment->id,
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
        ]))
            ->assertOk()
            ->assertDontSee('Usman Tariq')
            ->assertDontSee('72.00');
    }

    /* ---------------------------------------------------------------- */
    /* 28: performance */
    /* ---------------------------------------------------------------- */

    public function test_the_query_count_does_not_grow_with_the_number_of_students(): void
    {
        for ($index = 0; $index < 3; $index++) {
            $this->storedResult(
                $this->madrassaEnrollment($this->student('Few '.$index)),
                ['obtained_marks' => 60 + $index]
            );
        }

        // Warmed first. The permission package fills its cache on the first
        // request of the process, and those queries would otherwise be
        // counted against whichever page happened to run first.
        $this->get(route('results.reports'))->assertOk();

        $withFew = $this->countQueriesFor(route('results.reports'));

        for ($index = 0; $index < 17; $index++) {
            $this->storedResult(
                $this->madrassaEnrollment($this->student('More '.$index)),
                ['obtained_marks' => 60 + $index]
            );
        }

        $withMany = $this->countQueriesFor(route('results.reports'));

        // 20 students on one page against 3. With the placement eager
        // loaded, the rows paired in one query and every card aggregated in
        // SQL, the number of queries must not move.
        $this->assertSame(
            $withFew,
            $withMany,
            "The report ran {$withMany} queries for 20 students and {$withFew} for 3. "
                .'Something is being loaded per row.'
        );
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
