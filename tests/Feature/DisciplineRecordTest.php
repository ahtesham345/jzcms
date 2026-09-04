<?php

namespace Tests\Feature;

use App\Models\DisciplineRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers the Discipline module.
 *
 * Three things are being pinned down here.
 *
 * What may be written: the category and the severity come from two fixed
 * lists and nothing else may be filed under either, the student must exist,
 * and the recorder is taken from the signed-in user rather than from the
 * request however the form is edited.
 *
 * What may be read: a student's history is bounded by the route's student,
 * so no query parameter reaches another student's incidents. The filters
 * narrow, combine with AND, and survive pagination.
 *
 * What must not change: an incident belongs to the student, so promoting
 * them into a new session, class and section leaves the record exactly as
 * it was recorded.
 */
class DisciplineRecordTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    /**
     * The user every test in this class is signed in as.
     */
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Builds the academic structure and signs in as an administrator.
        $this->buildMadrassa();

        $this->admin = auth()->user();
    }

    /**
     * A complete submission for one student.
     *
     * Deliberately carries no recorded_by. The tests that care about the
     * recorder add one themselves, to prove it is ignored.
     *
     * @return array<string, mixed>
     */
    private function payload(Student $student, array $overrides = []): array
    {
        return array_merge([
            'student_id' => $student->id,
            'date' => '2026-08-10',
            'category' => DisciplineRecord::CATEGORY_BEHAVIOR,
            'severity' => DisciplineRecord::SEVERITY_LOW,
            'description' => 'Talking during the lesson after being asked to stop.',
            'action_taken' => 'Verbal Warning',
            'remarks' => 'Settled down afterwards.',
        ], $overrides);
    }

    /**
     * Write a record straight to the table, bypassing the form.
     */
    private function record(Student $student, array $overrides = []): DisciplineRecord
    {
        $record = new DisciplineRecord(array_merge([
            'student_id' => $student->id,
            'date' => '2026-08-10',
            'category' => DisciplineRecord::CATEGORY_BEHAVIOR,
            'severity' => DisciplineRecord::SEVERITY_LOW,
            'description' => 'Talking during the lesson.',
            'action_taken' => 'Verbal Warning',
        ], $overrides));

        $record->recorded_by = $overrides['recorded_by'] ?? $this->admin->id;
        $record->save();

        return $record;
    }

    /* ---------------------------------------------------------------- */
    /* 1-2: reaching the module at all */
    /* ---------------------------------------------------------------- */

    public function test_a_guest_cannot_access_the_discipline_list(): void
    {
        auth()->logout();

        $this->get(route('discipline.index'))->assertRedirect(route('login'));
    }

    public function test_a_guest_cannot_reach_any_discipline_page(): void
    {
        $student = $this->student('Hamza Iqbal');
        $record = $this->record($student);

        auth()->logout();

        // Every route the module adds, including the write ones. A page
        // being read-only is not a reason for a guest to see it.
        $this->get(route('discipline.create'))->assertRedirect(route('login'));
        $this->get(route('discipline.show', $record))->assertRedirect(route('login'));
        $this->get(route('discipline.edit', $record))->assertRedirect(route('login'));
        $this->get(route('students.discipline', $student))->assertRedirect(route('login'));
        $this->post(route('discipline.store'), $this->payload($student))->assertRedirect(route('login'));
        $this->put(route('discipline.update', $record), $this->payload($student))->assertRedirect(route('login'));

        // The refused write really was refused.
        $this->assertSame(1, DisciplineRecord::count());
    }

    public function test_an_authenticated_user_can_view_the_discipline_list(): void
    {
        $student = $this->student('Hamza Iqbal');
        $this->record($student, ['category' => DisciplineRecord::CATEGORY_UNIFORM]);

        $this->get(route('discipline.index'))
            ->assertOk()
            ->assertSee('Discipline Records')
            ->assertSee('Hamza Iqbal')
            ->assertSee($student->registration_number)
            ->assertSee(DisciplineRecord::CATEGORY_UNIFORM)
            ->assertSee($this->admin->name);
    }

    /* ---------------------------------------------------------------- */
    /* 3-4: creating a record */
    /* ---------------------------------------------------------------- */

    public function test_an_admin_can_create_a_discipline_record(): void
    {
        $student = $this->student('Hamza Iqbal');

        $this->get(route('discipline.create'))->assertOk()->assertSee('Hamza Iqbal');

        $this->post(route('discipline.store'), $this->payload($student, [
            'category' => DisciplineRecord::CATEGORY_FIGHTING,
            'severity' => DisciplineRecord::SEVERITY_HIGH,
            'description' => 'Fought with another student in the courtyard.',
            'action_taken' => 'Parent Contact',
        ]))
            ->assertRedirect(route('discipline.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('discipline_records', [
            'student_id' => $student->id,
            'date' => '2026-08-10',
            'category' => DisciplineRecord::CATEGORY_FIGHTING,
            'severity' => DisciplineRecord::SEVERITY_HIGH,
            'description' => 'Fought with another student in the courtyard.',
            'action_taken' => 'Parent Contact',
        ]);
    }

    public function test_a_discipline_record_stores_the_authenticated_recorder(): void
    {
        $student = $this->student('Hamza Iqbal');

        $this->post(route('discipline.store'), $this->payload($student))->assertRedirect();

        $this->assertDatabaseHas('discipline_records', [
            'student_id' => $student->id,
            'recorded_by' => $this->admin->id,
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* 5-8: what the server refuses */
    /* ---------------------------------------------------------------- */

    public function test_the_required_fields_are_validated(): void
    {
        $this->post(route('discipline.store'), [])
            ->assertSessionHasErrors(['student_id', 'date', 'category', 'severity', 'description']);

        $this->assertDatabaseCount('discipline_records', 0);
    }

    public function test_an_invalid_category_is_rejected(): void
    {
        $student = $this->student('Hamza Iqbal');

        $this->post(route('discipline.store'), $this->payload($student, ['category' => 'Vandalism']))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseCount('discipline_records', 0);
    }

    public function test_an_invalid_severity_is_rejected(): void
    {
        $student = $this->student('Hamza Iqbal');

        $this->post(route('discipline.store'), $this->payload($student, ['severity' => 'Critical']))
            ->assertSessionHasErrors('severity');

        $this->assertDatabaseCount('discipline_records', 0);
    }

    public function test_the_student_must_exist(): void
    {
        $this->post(route('discipline.store'), $this->payload($this->student(), ['student_id' => 999999]))
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseCount('discipline_records', 0);
    }

    public function test_an_invalid_date_is_rejected(): void
    {
        $student = $this->student('Hamza Iqbal');

        $this->post(route('discipline.store'), $this->payload($student, ['date' => 'the day before Eid']))
            ->assertSessionHasErrors('date');

        $this->assertDatabaseCount('discipline_records', 0);
    }

    public function test_action_taken_and_remarks_are_optional(): void
    {
        $student = $this->student('Hamza Iqbal');

        $this->post(route('discipline.store'), $this->payload($student, [
            'action_taken' => '',
            'remarks' => '',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        // Blank optional text is stored as nothing rather than as an empty
        // string, so a record with no action reads as having none.
        $record = DisciplineRecord::firstOrFail();
        $this->assertNull($record->action_taken);
        $this->assertNull($record->remarks);
    }

    /* ---------------------------------------------------------------- */
    /* 9-10: viewing and correcting one record */
    /* ---------------------------------------------------------------- */

    public function test_an_admin_can_view_a_discipline_record(): void
    {
        $student = $this->student('Hamza Iqbal');
        $record = $this->record($student, [
            'category' => DisciplineRecord::CATEGORY_ACADEMIC,
            'severity' => DisciplineRecord::SEVERITY_MEDIUM,
            'description' => 'Did not submit the homework for a third week.',
            'action_taken' => 'Counseling',
            'remarks' => 'Father informed.',
        ]);

        $this->get(route('discipline.show', $record))
            ->assertOk()
            ->assertSee('Hamza Iqbal')
            ->assertSee($student->registration_number)
            ->assertSee('10 Aug, 2026')
            ->assertSee(DisciplineRecord::CATEGORY_ACADEMIC)
            ->assertSee(DisciplineRecord::SEVERITY_MEDIUM)
            ->assertSee('Did not submit the homework for a third week.')
            ->assertSee('Counseling')
            ->assertSee('Father informed.')
            ->assertSee($this->admin->name);
    }

    public function test_an_admin_can_edit_a_discipline_record(): void
    {
        $student = $this->student('Hamza Iqbal');
        $record = $this->record($student);

        $this->get(route('discipline.edit', $record))
            ->assertOk()
            ->assertSee('Talking during the lesson.');

        $this->put(route('discipline.update', $record), $this->payload($student, [
            'severity' => DisciplineRecord::SEVERITY_MEDIUM,
            'description' => 'Talking during the lesson, second occasion.',
            'action_taken' => 'Written Warning',
        ]))
            ->assertRedirect(route('discipline.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('discipline_records', [
            'id' => $record->id,
            'severity' => DisciplineRecord::SEVERITY_MEDIUM,
            'description' => 'Talking during the lesson, second occasion.',
            'action_taken' => 'Written Warning',
        ]);
    }

    public function test_an_edit_does_not_change_who_recorded_the_incident(): void
    {
        $student = $this->student('Hamza Iqbal');
        $recorder = User::factory()->create(['name' => 'Qari Abdul Rahman']);
        $record = $this->record($student, ['recorded_by' => $recorder->id]);

        // Corrected by somebody else, who also tries to claim the entry.
        $this->put(route('discipline.update', $record), $this->payload($student, [
            'description' => 'Corrected description.',
            'recorded_by' => $this->admin->id,
        ]))->assertRedirect();

        $this->assertDatabaseHas('discipline_records', [
            'id' => $record->id,
            'description' => 'Corrected description.',
            'recorded_by' => $recorder->id,
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* 11-12: the history belongs to one student */
    /* ---------------------------------------------------------------- */

    public function test_the_history_shows_only_that_students_records(): void
    {
        $hamza = $this->student('Hamza Iqbal');
        $bilal = $this->student('Bilal Ahmad');

        $this->record($hamza, ['description' => 'Hamza was talking in class.']);
        $this->record($bilal, ['description' => 'Bilal was out of uniform.']);

        $this->get(route('students.discipline', $hamza))
            ->assertOk()
            ->assertSee('Hamza was talking in class.')
            ->assertDontSee('Bilal was out of uniform.');
    }

    public function test_another_students_records_cannot_be_reached_through_the_history_route(): void
    {
        $hamza = $this->student('Hamza Iqbal');
        $bilal = $this->student('Bilal Ahmad');

        $this->record($hamza, ['description' => 'Hamza was talking in class.']);
        $this->record($bilal, ['description' => 'Bilal was out of uniform.']);

        // Every plausible way of naming another student in the query
        // string. None of them is a filter this page reads, so none of them
        // widens what it shows.
        $response = $this->get(route('students.discipline', $hamza).'?student_id='.$bilal->id
            .'&student='.$bilal->id
            .'&filters[student_id]='.$bilal->id);

        $response->assertOk()
            ->assertSee('Hamza was talking in class.')
            ->assertDontSee('Bilal was out of uniform.')
            ->assertDontSee('Bilal Ahmad');

        // And the summary counted the same one record, not two.
        $this->assertSame(1, $response->viewData('summary')['total']);
    }

    /* ---------------------------------------------------------------- */
    /* 13-17: the filters */
    /* ---------------------------------------------------------------- */

    public function test_the_category_filter_works(): void
    {
        $student = $this->student('Hamza Iqbal');

        // The listing shows the action taken rather than the description,
        // so that is what these tests tell the rows apart by.
        $this->record($student, [
            'category' => DisciplineRecord::CATEGORY_UNIFORM,
            'action_taken' => 'Sent to change shoes',
        ]);
        $this->record($student, [
            'category' => DisciplineRecord::CATEGORY_FIGHTING,
            'action_taken' => 'Separated and warned',
        ]);

        $this->get(route('discipline.index', ['category' => DisciplineRecord::CATEGORY_UNIFORM]))
            ->assertOk()
            ->assertSee('Sent to change shoes')
            ->assertDontSee('Separated and warned');
    }

    public function test_the_severity_filter_works(): void
    {
        $student = $this->student('Hamza Iqbal');

        $this->record($student, [
            'severity' => DisciplineRecord::SEVERITY_HIGH,
            'action_taken' => 'Parent called in',
        ]);
        $this->record($student, [
            'severity' => DisciplineRecord::SEVERITY_LOW,
            'action_taken' => 'Reminded quietly',
        ]);

        $this->get(route('discipline.index', ['severity' => DisciplineRecord::SEVERITY_HIGH]))
            ->assertOk()
            ->assertSee('Parent called in')
            ->assertDontSee('Reminded quietly');
    }

    public function test_the_date_range_filter_works(): void
    {
        $student = $this->student('Hamza Iqbal');

        $this->record($student, ['date' => '2026-08-01', 'action_taken' => 'Before the range']);
        $this->record($student, ['date' => '2026-08-15', 'action_taken' => 'Inside the range']);
        $this->record($student, ['date' => '2026-09-01', 'action_taken' => 'After the range']);

        // Both bounds are inclusive, which is what an office means by
        // "from the 10th to the 20th".
        $this->get(route('discipline.index', ['date_from' => '2026-08-10', 'date_to' => '2026-08-20']))
            ->assertOk()
            ->assertSee('Inside the range')
            ->assertDontSee('Before the range')
            ->assertDontSee('After the range');
    }

    public function test_the_search_filter_matches_a_student(): void
    {
        $hamza = $this->student('Hamza Iqbal');
        $bilal = $this->student('Bilal Ahmad');

        $this->record($hamza, ['action_taken' => 'Warned Hamza']);
        $this->record($bilal, ['action_taken' => 'Warned Bilal']);

        // By name, and then by the registration number, which is how the
        // office usually looks a student up.
        $this->get(route('discipline.index', ['search' => 'Hamza']))
            ->assertOk()
            ->assertSee('Warned Hamza')
            ->assertDontSee('Warned Bilal');

        $this->get(route('discipline.index', ['search' => $bilal->registration_number]))
            ->assertOk()
            ->assertSee('Warned Bilal')
            ->assertDontSee('Warned Hamza');
    }

    public function test_the_filters_combine_with_and(): void
    {
        $hamza = $this->student('Hamza Iqbal');
        $bilal = $this->student('Bilal Ahmad');

        // The one row that satisfies every condition at once.
        $this->record($hamza, [
            'date' => '2026-08-15',
            'category' => DisciplineRecord::CATEGORY_FIGHTING,
            'severity' => DisciplineRecord::SEVERITY_HIGH,
            'action_taken' => 'Matches every filter',
        ]);

        // Each of these matches all but one of them, so a filter set that
        // combined with OR would let it through.
        $this->record($hamza, [
            'date' => '2026-08-15',
            'category' => DisciplineRecord::CATEGORY_UNIFORM,
            'severity' => DisciplineRecord::SEVERITY_HIGH,
            'action_taken' => 'Wrong category',
        ]);
        $this->record($hamza, [
            'date' => '2026-08-15',
            'category' => DisciplineRecord::CATEGORY_FIGHTING,
            'severity' => DisciplineRecord::SEVERITY_LOW,
            'action_taken' => 'Wrong severity',
        ]);
        $this->record($hamza, [
            'date' => '2026-09-20',
            'category' => DisciplineRecord::CATEGORY_FIGHTING,
            'severity' => DisciplineRecord::SEVERITY_HIGH,
            'action_taken' => 'Outside the date range',
        ]);
        $this->record($bilal, [
            'date' => '2026-08-15',
            'category' => DisciplineRecord::CATEGORY_FIGHTING,
            'severity' => DisciplineRecord::SEVERITY_HIGH,
            'action_taken' => 'Wrong student',
        ]);

        $response = $this->get(route('discipline.index', [
            'search' => 'Hamza',
            'category' => DisciplineRecord::CATEGORY_FIGHTING,
            'severity' => DisciplineRecord::SEVERITY_HIGH,
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]));

        $response->assertOk()
            ->assertSee('Matches every filter')
            ->assertDontSee('Wrong category')
            ->assertDontSee('Wrong severity')
            ->assertDontSee('Outside the date range')
            ->assertDontSee('Wrong student');

        $this->assertSame(1, $response->viewData('records')->total());
    }

    public function test_the_history_filters_combine_with_and(): void
    {
        $student = $this->student('Hamza Iqbal');

        $this->record($student, [
            'date' => '2026-08-15',
            'category' => DisciplineRecord::CATEGORY_FIGHTING,
            'severity' => DisciplineRecord::SEVERITY_HIGH,
            'description' => 'Matches every filter.',
        ]);
        $this->record($student, [
            'date' => '2026-08-15',
            'category' => DisciplineRecord::CATEGORY_FIGHTING,
            'severity' => DisciplineRecord::SEVERITY_LOW,
            'description' => 'Wrong severity.',
        ]);

        $this->get(route('students.discipline', $student).'?'.http_build_query([
            'category' => DisciplineRecord::CATEGORY_FIGHTING,
            'severity' => DisciplineRecord::SEVERITY_HIGH,
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]))
            ->assertOk()
            ->assertSee('Matches every filter.')
            ->assertDontSee('Wrong severity.');
    }

    public function test_pagination_preserves_the_filters(): void
    {
        $student = $this->student('Hamza Iqbal');

        // More than one page of matching records, plus one that must never
        // appear on either page.
        for ($day = 1; $day <= 25; $day++) {
            $this->record($student, [
                'date' => '2026-08-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT),
                'category' => DisciplineRecord::CATEGORY_UNIFORM,
                'action_taken' => 'Uniform action '.$day,
            ]);
        }

        $this->record($student, [
            'date' => '2026-08-15',
            'category' => DisciplineRecord::CATEGORY_FIGHTING,
            'action_taken' => 'Broke up a fight',
        ]);

        $filters = ['category' => DisciplineRecord::CATEGORY_UNIFORM];

        // The links on page one carry the filter, so following one keeps
        // the list filtered rather than silently widening it.
        $this->get(route('discipline.index', $filters))
            ->assertOk()
            ->assertSee('category='.DisciplineRecord::CATEGORY_UNIFORM, false)
            ->assertDontSee('Broke up a fight');

        $second = $this->get(route('discipline.index', $filters + ['page' => 2]));

        $second->assertOk()->assertDontSee('Broke up a fight');

        // 25 uniform incidents over two pages of 20, and the fight is not
        // among them.
        $this->assertSame(25, $second->viewData('records')->total());
        $this->assertSame(5, $second->viewData('records')->count());
    }

    public function test_history_pagination_preserves_the_filters(): void
    {
        $student = $this->student('Hamza Iqbal');

        for ($day = 1; $day <= 25; $day++) {
            $this->record($student, [
                'date' => '2026-08-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT),
                'severity' => DisciplineRecord::SEVERITY_LOW,
                'description' => 'Low incident number '.$day.'.',
            ]);
        }

        $this->record($student, [
            'date' => '2026-08-15',
            'severity' => DisciplineRecord::SEVERITY_HIGH,
            'description' => 'A high severity incident.',
        ]);

        $second = $this->get(route('students.discipline', $student)
            .'?severity='.DisciplineRecord::SEVERITY_LOW.'&page=2');

        $second->assertOk()->assertDontSee('A high severity incident.');

        $this->assertSame(25, $second->viewData('records')->total());
    }

    public function test_an_unrecognised_filter_value_narrows_nothing(): void
    {
        $student = $this->student('Hamza Iqbal');
        $this->record($student, ['action_taken' => 'An ordinary action']);

        // A hand-edited category that this module does not have narrows
        // nothing rather than narrowing to nothing, so the page still reads
        // as a list rather than as an empty one.
        $this->get(route('discipline.index', ['category' => 'Vandalism', 'severity' => 'Critical']))
            ->assertOk()
            ->assertSee('An ordinary action');
    }

    /* ---------------------------------------------------------------- */
    /* 18-21: the profile summary and the derived status */
    /* ---------------------------------------------------------------- */

    public function test_the_student_profile_shows_the_discipline_summary(): void
    {
        $student = $this->student('Hamza Iqbal');

        $this->record($student, ['severity' => DisciplineRecord::SEVERITY_LOW, 'date' => '2026-08-01']);
        $this->record($student, ['severity' => DisciplineRecord::SEVERITY_MEDIUM, 'date' => '2026-08-10']);
        $this->record($student, ['severity' => DisciplineRecord::SEVERITY_HIGH, 'date' => '2026-08-20']);

        $response = $this->get(route('students.show', $student));

        $response->assertOk()
            ->assertSee('Discipline')
            ->assertSee('Total Incidents')
            ->assertSee('View Discipline History')
            // The latest incident, taken as a maximum in SQL rather than by
            // reading the records back.
            ->assertSee('20 Aug, 2026');

        $summary = $response->viewData('disciplineSummary');

        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['low']);
        $this->assertSame(1, $summary['medium']);
        $this->assertSame(1, $summary['high']);
    }

    public function test_no_records_gives_a_good_status(): void
    {
        $student = $this->student('Hamza Iqbal');

        $response = $this->get(route('students.show', $student));

        $response->assertOk()->assertSee(DisciplineRecord::STATUS_GOOD);

        $this->assertSame(DisciplineRecord::STATUS_GOOD, $response->viewData('disciplineSummary')['status']);
    }

    public function test_non_high_incidents_give_a_has_warnings_status(): void
    {
        $student = $this->student('Hamza Iqbal');

        $this->record($student, ['severity' => DisciplineRecord::SEVERITY_LOW]);
        $this->record($student, ['severity' => DisciplineRecord::SEVERITY_MEDIUM]);

        $response = $this->get(route('students.show', $student));

        $response->assertOk()->assertSee(DisciplineRecord::STATUS_HAS_WARNINGS);

        $this->assertSame(
            DisciplineRecord::STATUS_HAS_WARNINGS,
            $response->viewData('disciplineSummary')['status']
        );
    }

    public function test_a_high_severity_incident_gives_a_serious_concern_status(): void
    {
        $student = $this->student('Hamza Iqbal');

        $this->record($student, ['severity' => DisciplineRecord::SEVERITY_LOW]);
        $this->record($student, ['severity' => DisciplineRecord::SEVERITY_HIGH]);

        $response = $this->get(route('students.show', $student));

        $response->assertOk()->assertSee(DisciplineRecord::STATUS_SERIOUS_CONCERN);

        $this->assertSame(
            DisciplineRecord::STATUS_SERIOUS_CONCERN,
            $response->viewData('disciplineSummary')['status']
        );
    }

    public function test_the_status_is_derived_rather_than_stored(): void
    {
        $student = $this->student('Hamza Iqbal');
        $record = $this->record($student, ['severity' => DisciplineRecord::SEVERITY_HIGH]);

        $this->assertSame(
            DisciplineRecord::STATUS_SERIOUS_CONCERN,
            DisciplineRecord::summaryForStudent($student)['status']
        );

        // Downgrade the one High incident and the status follows
        // immediately: there is no stored copy of it to go stale.
        $record->update(['severity' => DisciplineRecord::SEVERITY_LOW]);

        $this->assertSame(
            DisciplineRecord::STATUS_HAS_WARNINGS,
            DisciplineRecord::summaryForStudent($student->fresh())['status']
        );

        // There is no such column, which is the other half of the claim.
        $this->assertArrayNotHasKey('discipline_status', $student->fresh()->getAttributes());
    }

    public function test_the_history_status_ignores_the_filters(): void
    {
        $student = $this->student('Hamza Iqbal');

        $this->record($student, ['severity' => DisciplineRecord::SEVERITY_HIGH]);
        $this->record($student, ['severity' => DisciplineRecord::SEVERITY_LOW]);

        // Filtering the High incident out of view must not turn a Serious
        // Concern into a lesser status: the badge describes the student,
        // not the filtered table.
        $response = $this->get(route('students.discipline', $student)
            .'?severity='.DisciplineRecord::SEVERITY_LOW);

        $response->assertOk();

        $this->assertSame(DisciplineRecord::STATUS_SERIOUS_CONCERN, $response->viewData('status'));
        $this->assertSame(1, $response->viewData('summary')['total']);
    }

    /* ---------------------------------------------------------------- */
    /* 22: history survives a promotion */
    /* ---------------------------------------------------------------- */

    public function test_a_promotion_does_not_break_the_discipline_history(): void
    {
        $student = $this->student('Hamza Iqbal');
        $this->madrassaEnrollment($student);

        $record = $this->record($student, [
            'date' => '2026-08-10',
            'category' => DisciplineRecord::CATEGORY_FIGHTING,
            'severity' => DisciplineRecord::SEVERITY_HIGH,
            'description' => 'Recorded before the promotion.',
        ]);

        $before = $record->fresh()->getAttributes();

        // A real promotion: the old enrollment is completed, a new one is
        // opened in the next session, class and section, and the student's
        // placement columns move with it.
        $student->promote([
            'academic_track' => 'Madrassa',
            'academic_session_id' => $this->nextSession->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->hifzB->id,
            'promotion_date' => '2027-04-01',
        ]);

        // The record is untouched, down to the column values. It carries no
        // session, class or section of its own, so there is nothing a
        // promotion could have rewritten.
        $this->assertSame($before, $record->fresh()->getAttributes());

        $this->get(route('students.discipline', $student))
            ->assertOk()
            ->assertSee('Recorded before the promotion.')
            // The header shows the placement as it stands now, which is the
            // new one.
            ->assertSee($this->nextSession->name);

        $this->assertSame(
            DisciplineRecord::STATUS_SERIOUS_CONCERN,
            DisciplineRecord::summaryForStudent($student->fresh())['status']
        );
    }

    public function test_a_change_of_class_leaves_the_history_intact(): void
    {
        $student = $this->student('Hamza Iqbal');

        $this->record($student, ['description' => 'Recorded in the old class.']);

        // The placement columns change without a promotion - a correction,
        // or a move between sections. The record is attached to the
        // student, so none of it reaches the incident.
        $student->update([
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->hifzB->id,
        ]);

        $this->get(route('students.discipline', $student))
            ->assertOk()
            ->assertSee('Recorded in the old class.');

        $this->assertSame(1, DisciplineRecord::summaryForStudent($student->fresh())['total']);
    }

    /* ---------------------------------------------------------------- */
    /* 23: the recorder cannot be spoofed */
    /* ---------------------------------------------------------------- */

    public function test_recorded_by_cannot_be_spoofed_through_the_request(): void
    {
        $student = $this->student('Hamza Iqbal');
        $someoneElse = User::factory()->create(['name' => 'Someone Else']);

        // The form does not carry this field. A hand-edited request that
        // adds it must change nothing: the record is credited to whoever is
        // signed in.
        $this->post(route('discipline.store'), $this->payload($student, [
            'recorded_by' => $someoneElse->id,
        ]))->assertRedirect();

        $record = DisciplineRecord::firstOrFail();

        $this->assertSame($this->admin->id, $record->recorded_by);
        $this->assertNotSame($someoneElse->id, $record->recorded_by);
    }

    public function test_recorded_by_is_not_mass_assignable(): void
    {
        $student = $this->student('Hamza Iqbal');
        $someoneElse = User::factory()->create(['name' => 'Someone Else']);

        // The guard behind the controller: even a direct create() with the
        // column in the array leaves it unset.
        $record = DisciplineRecord::create([
            'student_id' => $student->id,
            'date' => '2026-08-10',
            'category' => DisciplineRecord::CATEGORY_BEHAVIOR,
            'severity' => DisciplineRecord::SEVERITY_LOW,
            'description' => 'Written straight to the model.',
            'recorded_by' => $someoneElse->id,
        ]);

        $this->assertNull($record->recorded_by);
    }

    /* ---------------------------------------------------------------- */
    /* Performance */
    /* ---------------------------------------------------------------- */

    public function test_the_list_query_count_does_not_grow_with_the_records(): void
    {
        $students = collect(['Hamza Iqbal', 'Bilal Ahmad', 'Usman Tariq'])
            ->map(fn ($name) => $this->student($name));

        foreach ($students as $student) {
            $this->record($student, ['description' => 'First incident for '.$student->full_name.'.']);
        }

        // One request first, thrown away. The sidebar's permission check
        // loads the permission tables once per process, and counting that
        // against the first measurement would hide what is being measured.
        $this->queriesForIndex();

        $withThree = $this->queriesForIndex();

        // Four more records across the same students. The student and the
        // recorder are eager loaded, so this must cost the same number of
        // queries rather than two more per row.
        foreach ($students as $student) {
            $this->record($student, ['description' => 'Second incident for '.$student->full_name.'.']);
        }
        $this->record($students->first(), ['description' => 'Third incident.']);

        $withSeven = $this->queriesForIndex();

        $this->assertSame($withThree, $withSeven);
    }

    /**
     * Count the queries one request to the discipline list costs.
     *
     * The log is flushed and disabled around the request so that the
     * inserts the fixtures make between two calls cannot be counted as part
     * of either.
     */
    private function queriesForIndex(): int
    {
        \DB::flushQueryLog();
        \DB::enableQueryLog();

        $this->get(route('discipline.index'))->assertOk();

        $queries = count(\DB::getQueryLog());

        \DB::disableQueryLog();
        \DB::flushQueryLog();

        return $queries;
    }

    public function test_the_profile_summary_costs_one_query_however_long_the_history(): void
    {
        $student = $this->student('Hamza Iqbal');

        for ($day = 1; $day <= 12; $day++) {
            $this->record($student, [
                'date' => '2026-08-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT),
            ]);
        }

        \DB::enableQueryLog();
        $summary = DisciplineRecord::summaryForStudent($student);
        $queries = \DB::getQueryLog();
        \DB::disableQueryLog();

        // One aggregate, not twelve rows read back and counted in PHP.
        $this->assertCount(1, $queries);
        $this->assertSame(12, $summary['total']);
    }
}
