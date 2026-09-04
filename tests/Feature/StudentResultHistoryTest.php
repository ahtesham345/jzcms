<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers one student's madrassa result history.
 *
 * A read-only page, and the cases that matter are about what it may reach.
 * The student comes from the route and every result is drawn through that
 * student's own madrassa enrollments, so the page cannot show another
 * student's marks and cannot show the school side of a dual-track student
 * however the query string is edited.
 *
 * The other half is historical accuracy. A result is tied to the enrollment
 * it was recorded against, and after a promotion the old result must still
 * name the class the test was sat in rather than the class the student sits
 * in now.
 */
class StudentResultHistoryTest extends TestCase
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
     * Promote a student into a new madrassa placement.
     *
     * Closes the current enrollment and opens the next one, which is what
     * the promotion module does. The old row stays, and it is what the old
     * results keep pointing at.
     */
    private function promote(
        Student $student,
        StudentAcademicEnrollment $from,
        array $overrides = []
    ): StudentAcademicEnrollment {
        $from->update(['status' => 'Completed', 'end_date' => '2027-03-31']);

        return $student->academicEnrollments()->create(array_merge([
            'academic_session_id' => $this->nextSession->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'start_date' => '2027-04-01',
            'status' => 'Active',
        ], $overrides));
    }

    /* ---------------------------------------------------------------- */
    /* 1-4: the page and what it lists */
    /* ---------------------------------------------------------------- */

    public function test_the_result_history_page_is_accessible(): void
    {
        $student = $this->student('Hamza Iqbal');
        $this->madrassaEnrollment($student);

        $this->get(route('students.results', $student))
            ->assertOk()
            ->assertSee('Result History')
            ->assertSee('Hamza Iqbal')
            ->assertSee($student->registration_number);
    }

    public function test_a_first_term_result_appears_in_the_history(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        $this->storedResult($enrollment, ['obtained_marks' => 85]);

        $this->get(route('students.results', $student))
            ->assertOk()
            ->assertSee(StudentResult::TERM_FIRST)
            ->assertSee(StudentResult::TEST_GRAND)
            ->assertSee('85.00')
            ->assertSee('85.00%')
            // The configured ladder puts 85% in A, and it is the stored
            // grade that is displayed rather than one worked out in Blade.
            ->assertSee('A');
    }

    public function test_a_final_term_result_appears_in_the_history(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        $this->storedResult($enrollment, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 92,
            'result_date' => '2027-03-15',
        ]);

        $this->get(route('students.results', $student))
            ->assertOk()
            ->assertSee(StudentResult::TERM_FINAL)
            ->assertSee('92.00')
            ->assertSee('15 Mar, 2027');
    }

    public function test_multiple_results_are_listed_newest_first(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        // Written oldest first so the ordering under test is the query's,
        // not the order they happen to have been inserted in.
        $this->storedResult($enrollment, [
            'term' => StudentResult::TERM_FIRST,
            'result_date' => '2026-09-30',
        ]);
        $this->storedResult($enrollment, [
            'term' => StudentResult::TERM_FINAL,
            'result_date' => '2027-03-15',
        ]);

        $content = $this->get(route('students.results', $student))
            ->assertOk()
            ->getContent();

        // Both dates are on the page; the later one comes first in the
        // table. Searched from the history heading onwards so the summary
        // cards and the term summary above it cannot decide the answer.
        $table = $this->historyTable($content);

        $this->assertLessThan(
            strpos($table, '30 Sep, 2026'),
            strpos($table, '15 Mar, 2027')
        );
    }

    /* ---------------------------------------------------------------- */
    /* 5-9: the filters */
    /* ---------------------------------------------------------------- */

    public function test_the_term_filter_narrows_the_history(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        $this->storedResult($enrollment, ['obtained_marks' => 61, 'result_date' => '2026-09-30']);
        $this->storedResult($enrollment, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 72,
            'result_date' => '2027-03-15',
        ]);

        $this->get(route('students.results', ['student' => $student, 'term' => StudentResult::TERM_FIRST]))
            ->assertOk()
            ->assertSee('61.00')
            ->assertDontSee('72.00');

        $this->get(route('students.results', ['student' => $student, 'term' => StudentResult::TERM_FINAL]))
            ->assertOk()
            ->assertSee('72.00')
            ->assertDontSee('61.00');
    }

    public function test_the_session_filter_narrows_the_history(): void
    {
        $student = $this->student('Hamza Iqbal');
        $first = $this->madrassaEnrollment($student);
        $second = $this->promote($student, $first);

        $this->storedResult($first, ['obtained_marks' => 61, 'result_date' => '2026-09-30']);
        $this->storedResult($second, ['obtained_marks' => 72, 'result_date' => '2027-09-30']);

        $this->get(route('students.results', [
            'student' => $student,
            'academic_session_id' => $this->session->id,
        ]))
            ->assertOk()
            ->assertSee('61.00')
            ->assertDontSee('72.00');

        $this->get(route('students.results', [
            'student' => $student,
            'academic_session_id' => $this->nextSession->id,
        ]))
            ->assertOk()
            ->assertSee('72.00')
            ->assertDontSee('61.00');
    }

    public function test_the_from_date_filter_narrows_the_history(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        $this->storedResult($enrollment, ['obtained_marks' => 61, 'result_date' => '2026-09-30']);
        $this->storedResult($enrollment, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 72,
            'result_date' => '2027-03-15',
        ]);

        $this->get(route('students.results', ['student' => $student, 'date_from' => '2027-01-01']))
            ->assertOk()
            ->assertSee('72.00')
            ->assertDontSee('61.00');
    }

    public function test_the_to_date_filter_narrows_the_history(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        $this->storedResult($enrollment, ['obtained_marks' => 61, 'result_date' => '2026-09-30']);
        $this->storedResult($enrollment, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 72,
            'result_date' => '2027-03-15',
        ]);

        $this->get(route('students.results', ['student' => $student, 'date_to' => '2026-12-31']))
            ->assertOk()
            ->assertSee('61.00')
            ->assertDontSee('72.00');
    }

    public function test_the_filters_combine_with_and_logic(): void
    {
        $student = $this->student('Hamza Iqbal');
        $first = $this->madrassaEnrollment($student);
        $second = $this->promote($student, $first);

        // First Term in the old session - the row the combined filter
        // should find.
        $this->storedResult($first, ['obtained_marks' => 61, 'result_date' => '2026-09-30']);
        // Final Term in the old session: right session, wrong term.
        $this->storedResult($first, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 72,
            'result_date' => '2027-03-15',
        ]);
        // First Term in the new session: right term, wrong session.
        $this->storedResult($second, ['obtained_marks' => 83, 'result_date' => '2027-09-30']);

        $this->get(route('students.results', [
            'student' => $student,
            'term' => StudentResult::TERM_FIRST,
            'academic_session_id' => $this->session->id,
        ]))
            ->assertOk()
            ->assertSee('61.00')
            ->assertDontSee('72.00')
            ->assertDontSee('83.00');
    }

    public function test_an_unrecognised_term_filter_narrows_nothing(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student);

        $this->storedResult($enrollment, ['obtained_marks' => 61]);

        // A hand-edited term is dropped rather than passed to the query, so
        // the page shows the whole history instead of an empty one.
        $this->get(route('students.results', ['student' => $student, 'term' => 'Third Term']))
            ->assertOk()
            ->assertSee('61.00');
    }

    /* ---------------------------------------------------------------- */
    /* 10-11: pagination */
    /* ---------------------------------------------------------------- */

    public function test_the_history_paginates_at_twenty_results(): void
    {
        $student = $this->student('Hamza Iqbal');

        // 21 results needs 21 enrollments: one result per enrollment per
        // term per test, which the unique index enforces. Each session gets
        // its own enrollment, which is also the realistic shape of a long
        // history.
        $results = $this->manyResults($student, 21);

        $firstPage = $this->get(route('students.results', $student))->assertOk();

        // Newest first, so the oldest of the 21 falls onto page two.
        $firstPage->assertSee('Showing 1 to 20 of 21 results');
        $firstPage->assertDontSee($results->last()->result_date->format('d M, Y'));

        $this->get(route('students.results', ['student' => $student, 'page' => 2]))
            ->assertOk()
            ->assertSee($results->last()->result_date->format('d M, Y'));
    }

    public function test_pagination_preserves_the_filters(): void
    {
        $student = $this->student('Hamza Iqbal');
        $this->manyResults($student, 21);

        $response = $this->get(route('students.results', [
            'student' => $student,
            'term' => StudentResult::TERM_FIRST,
            'date_from' => '2000-01-01',
        ]))->assertOk();

        // Every page link carries the filters, so paging through a filtered
        // history stays filtered rather than silently widening on page two.
        // rawurlencode, not urlencode: the paginator percent-encodes the
        // space rather than writing it as a plus.
        $response->assertSee('term='.rawurlencode(StudentResult::TERM_FIRST), false);
        $response->assertSee('date_from=2000-01-01', false);
        $response->assertSee('page=2', false);
    }

    /**
     * Give a student one First Term result in each of many sessions.
     *
     * @return Collection<int, StudentResult>
     */
    private function manyResults(Student $student, int $count)
    {
        // Numbered across calls, not just within one: a test that builds
        // two students' histories would otherwise collide on the session
        // name.
        static $sequence = 0;

        $results = collect();

        for ($index = 0; $index < $count; $index++) {
            $sequence++;

            $session = AcademicSession::create([
                'name' => 'Session '.(2000 + $index).'-'.$sequence,
                'start_date' => (2000 + $index).'-04-01',
                'end_date' => (2001 + $index).'-03-31',
                'status' => true,
            ]);

            $enrollment = $student->academicEnrollments()->create([
                'academic_session_id' => $session->id,
                'academic_track' => 'Madrassa',
                'department_id' => $this->hifz->id,
                'academic_class_id' => $this->hifzClass->id,
                'section_id' => $this->hifzA->id,
                'start_date' => (2000 + $index).'-04-01',
                'status' => 'Completed',
            ]);

            $results->push($this->storedResult($enrollment, [
                'result_date' => (2000 + $index).'-09-30',
            ]));
        }

        // Newest first, matching the order the page lists them in.
        return $results->sortByDesc('result_date')->values();
    }

    /* ---------------------------------------------------------------- */
    /* 12: the detail page */
    /* ---------------------------------------------------------------- */

    public function test_the_result_detail_page_shows_the_student_enrollment_and_result(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $result = $this->storedResult($enrollment, [
            'obtained_marks' => 85,
            'remarks' => 'Strong Tajweed',
        ]);

        $this->get(route('results.show', $result))
            ->assertOk()
            // Student
            ->assertSee('Hamza Iqbal')
            ->assertSee($student->registration_number)
            ->assertSee('Roll No.')
            // Enrollment at time of result
            ->assertSee('Enrollment at Time of Result')
            ->assertSee($this->session->name)
            ->assertSee($this->hifz->name)
            ->assertSee($this->nazra->name)
            ->assertSee('No section')
            ->assertSee($student->student_type)
            // Result
            ->assertSee(StudentResult::TERM_FIRST)
            ->assertSee(StudentResult::TEST_GRAND)
            ->assertSee('30 Sep, 2026')
            ->assertSee('100.00')
            ->assertSee('85.00')
            ->assertSee('85.00%')
            ->assertSee('Strong Tajweed');
    }

    public function test_the_detail_page_displays_the_stored_percentage_and_grade(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Hamza Iqbal'));

        // 2 out of 3 rounds to 66.67 on save. The page must show that
        // stored value rather than recomputing anything of its own.
        $result = $this->storedResult($enrollment, [
            'total_marks' => 3,
            'obtained_marks' => 2,
        ]);

        $this->assertSame('66.67', (string) $result->percentage);

        $this->get(route('results.show', $result))
            ->assertOk()
            ->assertSee('66.67%')
            ->assertSee($result->grade);
    }

    /* ---------------------------------------------------------------- */
    /* 13-14: historical enrollment safety */
    /* ---------------------------------------------------------------- */

    public function test_a_first_term_result_keeps_its_old_class_after_a_promotion(): void
    {
        $student = $this->student('Hamza Iqbal');

        // 2026 session, Nazra, no section.
        $first = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $this->storedResult($first, ['obtained_marks' => 61, 'result_date' => '2026-09-30']);

        // Promoted into the next session, Hifz, Hifz-B.
        $this->promote($student, $first);

        $content = $this->get(route('students.results', $student))
            ->assertOk()
            ->getContent();

        $row = $this->historyRowFor($content, '30 Sep, 2026');

        // The placement the test was actually sat in.
        $this->assertStringContainsString($this->nazra->name, $row);
        $this->assertStringContainsString($this->session->name, $row);
        // Not the one the student holds now.
        $this->assertStringNotContainsString($this->hifzB->name, $row);
        $this->assertStringNotContainsString($this->nextSession->name, $row);
    }

    public function test_a_result_recorded_after_a_promotion_shows_the_new_class(): void
    {
        $student = $this->student('Hamza Iqbal');

        $first = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);
        $this->storedResult($first, ['obtained_marks' => 61, 'result_date' => '2026-09-30']);

        $second = $this->promote($student, $first);
        $this->storedResult($second, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 72,
            'result_date' => '2027-09-30',
        ]);

        $content = $this->get(route('students.results', $student))
            ->assertOk()
            ->getContent();

        // The old row keeps Nazra; the new row carries the new placement.
        // Both are true at once, which is the whole point of hanging a
        // result off its enrollment.
        $oldRow = $this->historyRowFor($content, '30 Sep, 2026');
        $newRow = $this->historyRowFor($content, '30 Sep, 2027');

        $this->assertStringContainsString($this->nazra->name, $oldRow);
        $this->assertStringNotContainsString($this->hifzB->name, $oldRow);

        $this->assertStringContainsString($this->hifzClass->name, $newRow);
        $this->assertStringContainsString($this->hifzB->name, $newRow);
        $this->assertStringContainsString($this->nextSession->name, $newRow);
    }

    public function test_results_across_multiple_madrassa_enrollments_are_all_listed(): void
    {
        $student = $this->student('Hamza Iqbal');

        $first = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);
        $second = $this->promote($student, $first);

        $this->storedResult($first, ['obtained_marks' => 61, 'result_date' => '2026-09-30']);
        $this->storedResult($first, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 72,
            'result_date' => '2027-03-15',
        ]);
        $this->storedResult($second, ['obtained_marks' => 83, 'result_date' => '2027-09-30']);

        $this->get(route('students.results', $student))
            ->assertOk()
            ->assertSee('Showing 1 to 3 of 3 results')
            ->assertSee('61.00')
            ->assertSee('72.00')
            ->assertSee('83.00')
            // Being promoted does not start the history over.
            ->assertSee('2 Madrassa placements');
    }

    /**
     * Cut one row out of the history table, by the date it carries.
     *
     * The assertions above are about what a single row says, and a page
     * wide assertion could be satisfied by a value sitting in another row
     * entirely.
     */
    private function historyRowFor(string $content, string $date): string
    {
        $table = $this->historyTable($content);

        $start = strpos($table, $date);
        $this->assertNotFalse($start, "No history row for {$date}.");

        $end = strpos($table, '</tr>', $start);

        return substr($table, $start, $end - $start);
    }

    /**
     * Cut the history table out of the page.
     *
     * Anchored on the last "Result History" rather than the first: the
     * phrase is also the page title and the header, and the summary cards
     * above the table carry the same dates the rows do.
     */
    private function historyTable(string $content): string
    {
        return substr($content, strrpos($content, 'Result History'));
    }

    /* ---------------------------------------------------------------- */
    /* 15-17: the track boundary */
    /* ---------------------------------------------------------------- */

    public function test_a_school_enrollment_never_appears_in_the_history(): void
    {
        $student = $this->student('Ali Raza', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->storedResult($madrassa, ['obtained_marks' => 61]);

        // A result written straight onto the school enrollment, bypassing
        // every rule the write path enforces. Even so it must not surface
        // here: the page draws from the madrassa enrollments alone.
        $this->storedResult($school, ['obtained_marks' => 72]);

        $this->get(route('students.results', $student))
            ->assertOk()
            ->assertSee('61.00')
            ->assertDontSee('72.00')
            ->assertDontSee($this->primary->name)
            ->assertDontSee($this->primaryB->name)
            ->assertSee('Showing 1 to 1 of 1 results');
    }

    public function test_a_hifz_plus_school_student_appears_once_through_the_madrassa_side(): void
    {
        $student = $this->student('Ali Raza', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $this->schoolEnrollment($student);

        $this->storedResult($madrassa, ['obtained_marks' => 61]);

        $this->get(route('students.results', $student))
            ->assertOk()
            // One result, one madrassa placement. The school enrollment is
            // not counted as a placement this page knows about.
            ->assertSee('Showing 1 to 1 of 1 results')
            ->assertSee('1 Madrassa placement')
            ->assertSee($this->hifz->name)
            // The school placement's class and section, which only a school
            // row could put on this page. The department name itself is not
            // asserted on: "School" is also part of this student's
            // "Hifz + School" programme, which legitimately appears.
            ->assertDontSee($this->primary->name)
            ->assertDontSee($this->primaryB->name);
    }

    public function test_a_school_only_student_gets_the_empty_state(): void
    {
        $student = $this->student('Usman Tariq', 'School');
        $this->schoolEnrollment($student);

        $this->get(route('students.results', $student))
            ->assertOk()
            ->assertSee('This student has no Madrassa results.')
            ->assertSee('has no Madrassa enrollment')
            // No table, no filters, no summary cards to imply otherwise.
            ->assertDontSee('Term Summary')
            ->assertDontSee('Results Recorded');
    }

    /* ---------------------------------------------------------------- */
    /* 18-19: security */
    /* ---------------------------------------------------------------- */

    public function test_one_students_history_never_shows_another_students_results(): void
    {
        $hamza = $this->student('Hamza Iqbal');
        $bilal = $this->student('Bilal Ahmad');

        $this->storedResult($this->madrassaEnrollment($hamza), ['obtained_marks' => 61]);
        $this->storedResult($this->madrassaEnrollment($bilal), ['obtained_marks' => 72]);

        $this->get(route('students.results', $hamza))
            ->assertOk()
            ->assertSee('61.00')
            ->assertDontSee('72.00')
            ->assertDontSee('Bilal Ahmad');
    }

    public function test_a_manipulated_query_string_cannot_reach_another_students_results(): void
    {
        $hamza = $this->student('Hamza Iqbal');
        $bilal = $this->student('Bilal Ahmad');

        $this->storedResult($this->madrassaEnrollment($hamza), ['obtained_marks' => 61]);
        $bilalEnrollment = $this->madrassaEnrollment($bilal);
        $this->storedResult($bilalEnrollment, ['obtained_marks' => 72]);

        // Every id the page might have been tempted to read from a query
        // string, handed to it at once. The student is the route parameter
        // and the enrollments are gathered from that student alone, so none
        // of these is read.
        $this->get(route('students.results', [
            'student' => $hamza,
            'student_id' => $bilal->id,
            'student_academic_enrollment_id' => $bilalEnrollment->id,
            'enrollment_id' => $bilalEnrollment->id,
        ]))
            ->assertOk()
            ->assertSee('61.00')
            ->assertDontSee('72.00')
            ->assertDontSee('Bilal Ahmad');
    }

    public function test_a_session_filter_naming_another_students_session_shows_nothing_extra(): void
    {
        $hamza = $this->student('Hamza Iqbal');
        $bilal = $this->student('Bilal Ahmad');

        // Hamza is only ever in the first session; Bilal is in the next.
        $this->storedResult($this->madrassaEnrollment($hamza), ['obtained_marks' => 61]);
        $this->storedResult(
            $this->madrassaEnrollment($bilal, ['academic_session_id' => $this->nextSession->id]),
            ['obtained_marks' => 72]
        );

        // The session filter narrows Hamza's own enrollments. It cannot
        // widen the page to a session he was never enrolled in.
        $this->get(route('students.results', [
            'student' => $hamza,
            'academic_session_id' => $this->nextSession->id,
        ]))
            ->assertOk()
            ->assertDontSee('72.00')
            ->assertSee('No results found.');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $student = $this->student('Hamza Iqbal');
        $this->storedResult($this->madrassaEnrollment($student));

        auth()->logout();

        $this->get(route('students.results', $student))
            ->assertRedirect(route('login'));
    }

    /* ---------------------------------------------------------------- */
    /* 20: performance */
    /* ---------------------------------------------------------------- */

    public function test_the_query_count_does_not_grow_with_the_number_of_results(): void
    {
        $few = $this->student('Hamza Iqbal');
        $many = $this->student('Bilal Ahmad');

        $this->manyResults($few, 2);
        $this->manyResults($many, 18);

        // Warmed first. The permission package fills its cache on the
        // first request of the process, and those two queries would
        // otherwise be counted against whichever page happened to run
        // first rather than against the number of results.
        $this->get(route('students.results', $few))->assertOk();

        // Both fit on one page, so the only difference between the two
        // requests is how many rows and how many placements are rendered.
        // With the placement eager loaded and the cards aggregated in SQL,
        // the number of queries must not move.
        $withFew = $this->countQueriesFor(route('students.results', $few));
        $withMany = $this->countQueriesFor(route('students.results', $many));

        $this->assertSame(
            $withFew,
            $withMany,
            "The history ran {$withMany} queries for 18 results and {$withFew} for 2. "
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
