<?php

namespace Tests\Feature;

use App\Models\StudentAcademicEnrollment;
use App\Models\StudentResult;
use App\Support\GradeScale;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers Madrassa result entry: the Grand Test, First and Final Term.
 *
 * The cases that matter most are the boundary ones. A result belongs to a
 * madrassa enrollment and to nothing else, so a school-only student cannot
 * receive one and a Hifz + School student can only receive one through
 * their madrassa side. The arithmetic matters next: the percentage and the
 * grade are the application's to compute, never the browser's, and the
 * marks a result is computed from have to be possible marks.
 *
 * The academic structure these tests are written against is built by
 * BuildsMadrassaFixtures, shared with the daily record and roster tests so
 * all three describe one madrassa rather than three that look alike.
 */
class StudentResultTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMadrassa();
    }

    /**
     * A complete result submission for one enrollment.
     *
     * Kept here rather than in the shared fixtures trait: it is this
     * module's payload, and the trait is shared with tests that have no
     * business knowing about results.
     *
     * @return array<string, mixed>
     */
    private function resultPayload(StudentAcademicEnrollment $enrollment, array $overrides = []): array
    {
        return array_merge([
            'student_id' => $enrollment->student_id,
            'student_academic_enrollment_id' => $enrollment->id,
            'term' => StudentResult::TERM_FIRST,
            'test_type' => StudentResult::TEST_GRAND,
            'total_marks' => 100,
            'obtained_marks' => 85,
            'result_date' => '2026-09-30',
            'remarks' => 'Good performance',
        ], $overrides);
    }

    /**
     * Write a result straight to the table, bypassing the form.
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

    /* ---------------------------------------------------------------- */
    /* 1-2: recording a result for each term */
    /* ---------------------------------------------------------------- */

    public function test_madrassa_student_can_receive_a_first_term_grand_test_result(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $response = $this->post(route('results.store'), $this->resultPayload($enrollment));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('results.index'));

        $this->assertDatabaseHas('student_results', [
            'student_academic_enrollment_id' => $enrollment->id,
            'term' => StudentResult::TERM_FIRST,
            'test_type' => StudentResult::TEST_GRAND,
        ]);
    }

    public function test_madrassa_student_can_receive_a_final_term_grand_test_result(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // The First Term result already on file: the two terms are separate
        // results against the same enrollment, and recording one must not
        // stand in the way of the other.
        $this->storedResult($enrollment);

        $response = $this->post(route('results.store'), $this->resultPayload($enrollment, [
            'term' => StudentResult::TERM_FINAL,
            'obtained_marks' => 92,
            'result_date' => '2027-03-15',
        ]));

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_results', [
            'student_academic_enrollment_id' => $enrollment->id,
            'term' => StudentResult::TERM_FINAL,
            'test_type' => StudentResult::TEST_GRAND,
        ]);

        $this->assertSame(2, StudentResult::count());
    }

    /* ---------------------------------------------------------------- */
    /* 3-4: who may receive a result at all */
    /* ---------------------------------------------------------------- */

    public function test_school_only_student_cannot_receive_a_madrassa_result(): void
    {
        $student = $this->student('Usman Tariq', 'School');
        $enrollment = $this->schoolEnrollment($student);

        $response = $this->post(route('results.store'), $this->resultPayload($enrollment));

        $response->assertSessionHasErrors('student_academic_enrollment_id');
        $this->assertDatabaseCount('student_results', 0);
    }

    public function test_hifz_plus_school_student_is_handled_through_the_madrassa_enrollment_only(): void
    {
        $student = $this->student('Ali Raza', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        // The madrassa side is accepted.
        $this->post(route('results.store'), $this->resultPayload($madrassa))
            ->assertSessionHasNoErrors();

        // The school side is refused, even though it belongs to the same
        // student and that student is on a madrassa programme.
        $this->post(route('results.store'), $this->resultPayload($school, [
            'term' => StudentResult::TERM_FINAL,
        ]))->assertSessionHasErrors('student_academic_enrollment_id');

        $this->assertSame(1, StudentResult::count());
        $this->assertSame(
            $madrassa->id,
            StudentResult::firstOrFail()->student_academic_enrollment_id
        );

        // And the student appears once on the roster, through the madrassa
        // enrollment, rather than once per track. Counted by the link to
        // their profile, which each roster row renders exactly once - the
        // name itself would also be found in the search box above the
        // table.
        //
        // The closing quote is part of what is counted: the same row also
        // links to students/{id}/results, which has the profile URL as a
        // prefix and would otherwise be counted as a second row.
        $response = $this->get(route('results.index'));
        $response->assertOk();
        $this->assertSame(
            1,
            substr_count($response->getContent(), route('students.show', $student->id).'"')
        );
    }

    /* ---------------------------------------------------------------- */
    /* 5-6: the arithmetic */
    /* ---------------------------------------------------------------- */

    public function test_percentage_is_calculated_by_the_application(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->post(route('results.store'), $this->resultPayload($enrollment, [
            'total_marks' => 500,
            'obtained_marks' => 375,
            // Sent deliberately, and deliberately wrong. Neither column is
            // fillable and both are recomputed on save, so this must have
            // no effect at all.
            'percentage' => 99.99,
            'grade' => 'A+',
        ]))->assertSessionHasNoErrors();

        $result = StudentResult::firstOrFail();

        $this->assertSame('75.00', (string) $result->percentage);
        $this->assertNotSame('A+', $result->grade);

        // The unrounded case: 2 out of 3 is 66.666..., stored to the
        // column's two decimals.
        $this->assertSame(66.67, StudentResult::calculatePercentage(3, 2));
        $this->assertSame(100.0, StudentResult::calculatePercentage(50, 50));
        $this->assertSame(0.0, StudentResult::calculatePercentage(50, 0));

        // Impossible totals produce no percentage rather than an error.
        $this->assertNull(StudentResult::calculatePercentage(0, 10));
        $this->assertNull(StudentResult::calculatePercentage(-5, 10));
    }

    public function test_grade_is_calculated_from_the_configured_ladder(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->post(route('results.store'), $this->resultPayload($enrollment, [
            'total_marks' => 100,
            'obtained_marks' => 95,
        ]))->assertSessionHasNoErrors();

        $result = StudentResult::firstOrFail();

        // The project's own ladder, from config('jzcms.grades'): 95% is an
        // A+ under it, and the module reads that config rather than
        // carrying a second ladder of its own.
        $this->assertSame('A+', $result->grade);
        $this->assertSame('A+', GradeScale::forPercentage(90.0));
        $this->assertSame('A', GradeScale::forPercentage(89.99));
        $this->assertSame('F', GradeScale::forPercentage(0.0));

        // No percentage means no grade, which is a different statement
        // from a grade of F.
        $this->assertNull(GradeScale::forPercentage(null));
    }

    public function test_grade_boundaries_follow_the_configuration(): void
    {
        // Changing the institution's ladder changes the grades, without
        // this module knowing anything about the letters involved.
        config(['jzcms.grades' => [
            'Excellent' => ['min' => 80],
            'Pass' => ['min' => 40],
            'Fail' => ['min' => 0],
        ]]);

        $this->assertSame('Excellent', GradeScale::forPercentage(85.0));
        $this->assertSame('Pass', GradeScale::forPercentage(40.0));
        $this->assertSame('Fail', GradeScale::forPercentage(39.99));
    }

    /* ---------------------------------------------------------------- */
    /* 7-9: the marks a result may be computed from */
    /* ---------------------------------------------------------------- */

    public function test_obtained_marks_cannot_exceed_total_marks(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->post(route('results.store'), $this->resultPayload($enrollment, [
            'total_marks' => 100,
            'obtained_marks' => 101,
        ]))->assertSessionHasErrors('obtained_marks');

        $this->assertDatabaseCount('student_results', 0);
    }

    public function test_total_marks_cannot_be_zero_or_negative(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->post(route('results.store'), $this->resultPayload($enrollment, [
            'total_marks' => 0,
            'obtained_marks' => 0,
        ]))->assertSessionHasErrors('total_marks');

        $this->post(route('results.store'), $this->resultPayload($enrollment, [
            'total_marks' => -100,
            'obtained_marks' => 0,
        ]))->assertSessionHasErrors('total_marks');

        $this->assertDatabaseCount('student_results', 0);
    }

    public function test_negative_obtained_marks_are_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->post(route('results.store'), $this->resultPayload($enrollment, [
            'obtained_marks' => -1,
        ]))->assertSessionHasErrors('obtained_marks');

        $this->assertDatabaseCount('student_results', 0);
    }

    /* ---------------------------------------------------------------- */
    /* 10-11: one result per enrollment, term and test type */
    /* ---------------------------------------------------------------- */

    public function test_duplicate_result_for_the_same_enrollment_term_and_test_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $this->storedResult($enrollment);

        $this->post(route('results.store'), $this->resultPayload($enrollment))
            ->assertSessionHasErrors('term');

        $this->assertSame(1, StudentResult::count());
    }

    public function test_the_database_refuses_a_duplicate_the_validator_never_saw(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $this->storedResult($enrollment);

        // The unique index is the final guard behind the validation rule:
        // a writer that bypasses the form entirely still cannot file two
        // Grand Test results against one enrollment and term.
        $this->expectException(QueryException::class);

        $this->storedResult($enrollment, ['obtained_marks' => 90]);
    }

    public function test_an_existing_result_can_be_edited_without_creating_a_duplicate(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $result = $this->storedResult($enrollment);

        $response = $this->put(route('results.update', $result), $this->resultPayload($enrollment, [
            'obtained_marks' => 91,
            'remarks' => 'Corrected after re-checking the paper',
        ]));

        $response->assertSessionHasNoErrors();

        $result->refresh();

        $this->assertSame(1, StudentResult::count());
        $this->assertSame('91.00', (string) $result->obtained_marks);
        $this->assertSame('91.00', (string) $result->percentage);
        $this->assertSame('A+', $result->grade);
        $this->assertSame('Corrected after re-checking the paper', $result->remarks);
    }

    /* ---------------------------------------------------------------- */
    /* 12: the history a result belongs to */
    /* ---------------------------------------------------------------- */

    public function test_a_result_keeps_the_session_class_and_section_it_was_recorded_under(): void
    {
        $student = $this->student('Hamza Iqbal');
        $enrollment = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $result = $this->storedResult($enrollment);

        // Promoted into a new class, a new section and the next session.
        // The old enrollment is closed and a new one opened, which is what
        // the promotion module does.
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

        $result->refresh()->load('studentAcademicEnrollment');
        $stored = $result->studentAcademicEnrollment;

        // Still the placement the test was sat under, not the current one.
        $this->assertSame($this->session->id, $stored->academic_session_id);
        $this->assertSame($this->nazra->id, $stored->academic_class_id);
        $this->assertNull($stored->section_id);

        // And the result page says so.
        $this->get(route('results.show', $result))
            ->assertOk()
            ->assertSee($this->nazra->name)
            ->assertSee($this->session->name)
            ->assertDontSee($this->hifzB->name);
    }

    /* ---------------------------------------------------------------- */
    /* 13: who may reach the module */
    /* ---------------------------------------------------------------- */

    public function test_guests_cannot_access_the_result_routes(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $result = $this->storedResult($enrollment);

        // Signed out for the whole case: the fixtures signed an
        // administrator in during setUp.
        auth()->logout();

        $this->get(route('results.index'))->assertRedirect(route('login'));
        $this->get(route('results.create'))->assertRedirect(route('login'));
        $this->get(route('results.show', $result))->assertRedirect(route('login'));
        $this->get(route('results.edit', $result))->assertRedirect(route('login'));
        $this->post(route('results.store'), $this->resultPayload($enrollment, [
            'term' => StudentResult::TERM_FINAL,
        ]))->assertRedirect(route('login'));
        $this->put(route('results.update', $result), $this->resultPayload($enrollment))
            ->assertRedirect(route('login'));

        // Nothing was written on the way past.
        $this->assertSame(1, StudentResult::count());
    }

    /* ---------------------------------------------------------------- */
    /* 14: the student profile */
    /* ---------------------------------------------------------------- */

    public function test_the_student_profile_shows_madrassa_results(): void
    {
        $student = $this->student('Zaid Anwar');
        $enrollment = $this->madrassaEnrollment($student);

        $this->storedResult($enrollment, ['obtained_marks' => 85]);

        $response = $this->get(route('students.show', $student));

        $response->assertOk();
        $response->assertSee('Results');
        $response->assertSee(StudentResult::TERM_FIRST.' '.StudentResult::TEST_GRAND);
        $response->assertSee('85.00');
        $response->assertSee('85.00%');
        // The Final Term has not been marked, so the profile says so rather
        // than leaving the row out.
        $response->assertSee('Not Entered');
    }

    public function test_a_school_only_student_profile_shows_no_results_section(): void
    {
        $student = $this->student('Usman Tariq', 'School');
        $this->schoolEnrollment($student);

        $this->assertNull(StudentResult::summaryForStudent($student));

        $this->get(route('students.show', $student))
            ->assertOk()
            ->assertDontSee('Not Entered');
    }

    /* ---------------------------------------------------------------- */
    /* 15: what a hand-edited request can reach */
    /* ---------------------------------------------------------------- */

    public function test_a_school_enrollment_cannot_be_submitted_through_a_manipulated_request(): void
    {
        $student = $this->student('Ali Raza', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        // The form is opened for the madrassa enrollment and the id is
        // swapped for the school one on the way out. Both ids are real and
        // both belong to this student, so only the track check can refuse
        // it - which is the point.
        $this->post(route('results.store'), $this->resultPayload($madrassa, [
            'student_academic_enrollment_id' => $school->id,
        ]))->assertSessionHasErrors('student_academic_enrollment_id');

        $this->assertDatabaseCount('student_results', 0);
    }

    public function test_a_result_cannot_be_moved_to_another_students_enrollment(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Hamza Iqbal'));
        $other = $this->madrassaEnrollment($this->student('Bilal Ahmad'));

        $result = $this->storedResult($enrollment);

        $this->put(route('results.update', $result), $this->resultPayload($other, [
            'obtained_marks' => 99,
        ]))->assertSessionHasErrors('student_academic_enrollment_id');

        $result->refresh();

        $this->assertSame($enrollment->id, $result->student_academic_enrollment_id);
        $this->assertSame('85.00', (string) $result->obtained_marks);
    }

    public function test_a_result_cannot_be_created_against_an_inactive_enrollment(): void
    {
        $enrollment = $this->madrassaEnrollment(null, [
            'status' => 'Completed',
            'end_date' => '2027-03-31',
        ]);

        $this->post(route('results.store'), $this->resultPayload($enrollment))
            ->assertSessionHasErrors('student_academic_enrollment_id');

        $this->assertDatabaseCount('student_results', 0);
    }

    public function test_an_existing_result_stays_correctable_after_the_placement_is_closed(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $result = $this->storedResult($enrollment);

        $enrollment->update(['status' => 'Completed', 'end_date' => '2027-03-31']);

        $this->put(route('results.update', $result), $this->resultPayload($enrollment, [
            'obtained_marks' => 88,
        ]))->assertSessionHasNoErrors();

        $this->assertSame('88.00', (string) $result->refresh()->obtained_marks);
    }

    /* ---------------------------------------------------------------- */
    /* The listing */
    /* ---------------------------------------------------------------- */

    public function test_the_index_shows_not_entered_for_a_term_with_no_result(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Hamza Iqbal'));
        $this->storedResult($enrollment);

        // The First Term is recorded, so its row offers View and Edit.
        $this->get(route('results.index', ['term' => StudentResult::TERM_FIRST]))
            ->assertOk()
            ->assertSee('Hamza Iqbal')
            ->assertSee('Recorded')
            ->assertDontSee('Not Entered');

        // The Final Term is not.
        $this->get(route('results.index', ['term' => StudentResult::TERM_FINAL]))
            ->assertOk()
            ->assertSee('Hamza Iqbal')
            ->assertSee('Not Entered');
    }

    public function test_the_index_never_lists_a_school_only_student(): void
    {
        $this->madrassaEnrollment($this->student('Hamza Iqbal'));
        $this->schoolEnrollment($this->student('Usman Tariq', 'School'));

        $this->get(route('results.index'))
            ->assertOk()
            ->assertSee('Hamza Iqbal')
            ->assertDontSee('Usman Tariq');
    }

    public function test_the_create_form_refuses_a_school_enrollment(): void
    {
        $student = $this->student('Usman Tariq', 'School');
        $enrollment = $this->schoolEnrollment($student);

        $this->get(route('results.create', ['student_academic_enrollment_id' => $enrollment->id]))
            ->assertRedirect(route('results.index'))
            ->assertSessionHas('error');
    }

    public function test_the_create_form_opens_for_a_madrassa_enrollment(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Hamza Iqbal'));

        $this->get(route('results.create', [
            'student_academic_enrollment_id' => $enrollment->id,
            'term' => StudentResult::TERM_FINAL,
        ]))
            ->assertOk()
            ->assertSee('Hamza Iqbal')
            ->assertSee(StudentResult::TERM_FINAL)
            ->assertSee(StudentResult::TEST_GRAND)
            // The two derived values are shown as pending rather than as
            // fields: there is no input behind either of them.
            ->assertSee('Calculated on save');
    }

    public function test_the_edit_form_shows_the_stored_percentage_and_grade(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Hamza Iqbal'));
        $result = $this->storedResult($enrollment);

        $this->get(route('results.edit', $result))
            ->assertOk()
            ->assertSee('Hamza Iqbal')
            ->assertSee('85.00%')
            ->assertSee('Calculated by the system')
            ->assertDontSee('Calculated on save');
    }

    public function test_opening_a_term_that_is_already_marked_leads_to_the_existing_result(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $result = $this->storedResult($enrollment);

        $this->get(route('results.create', [
            'student_academic_enrollment_id' => $enrollment->id,
            'term' => StudentResult::TERM_FIRST,
        ]))->assertRedirect(route('results.edit', $result));
    }
}
