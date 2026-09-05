<?php

namespace Tests\Feature;

use App\Models\MadrassaDailyRecord;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers the daily roster, the student history and the navigation between
 * them.
 *
 * The roster is how a whole madrassa class is worked through for one day:
 * pick the date and the placement, load the students, and open or correct
 * each one's record. What it must never do is write anything by itself -
 * loading a date is a read, and a student with no record simply has none.
 *
 * Most assertions are made against the view data rather than the markup.
 * The filter selects legitimately name every class, section and session in
 * the institution, so "the page does not contain Primary-B" would be a
 * statement about the dropdowns, not about the roster.
 */
class MadrassaDailyRosterTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMadrassa();
    }

    /* ---------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------- */

    /**
     * Load the roster for a date and placement.
     *
     * @param  array<string, mixed>  $filters
     */
    private function loadRoster(array $filters = [])
    {
        return $this->get(route('hifz.index', array_merge([
            'record_date' => self::MONDAY,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
        ], $filters)));
    }

    /**
     * The names on the loaded roster, in the order it drew them.
     *
     * @return array<int, string>
     */
    private function rosterNames($response): array
    {
        $roster = $response->viewData('roster');

        return $roster === null
            ? []
            : $roster->map(fn ($enrollment) => $enrollment->student->full_name)->values()->all();
    }

    /* ---------------------------------------------------------------- */
    /* 1-5: who appears on the roster */
    /* ---------------------------------------------------------------- */

    public function test_the_madrassa_roster_loads_for_a_date_and_class(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $response = $this->loadRoster();

        $response->assertOk();
        $response->assertSee('Daily Roster');
        $this->assertSame('loaded', $response->viewData('rosterState'));
        $this->assertSame(['Ahtesham Shakeel'], $this->rosterNames($response));
    }

    public function test_school_only_students_do_not_appear_on_the_roster(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $this->schoolEnrollment($this->student('Usman Tariq', 'School'));

        // Asked for on the school side too, so the absence is not just the
        // class filter doing the work.
        $bySchoolClass = $this->loadRoster([
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
        ]);

        $this->assertSame(['Ahtesham Shakeel'], $this->rosterNames($this->loadRoster()));
        $this->assertSame([], $this->rosterNames($bySchoolClass));
    }

    public function test_hifz_students_appear_on_the_roster(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel', 'Hifz'));

        $response = $this->loadRoster();

        $this->assertSame(['Ahtesham Shakeel'], $this->rosterNames($response));
        // Shown by programme, not by the raw student_type column.
        $response->assertSee(MadrassaDailyRecord::TYPE_HIFZ);
    }

    public function test_a_hifz_plus_school_student_appears_once(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $this->madrassaEnrollment($student);
        $this->schoolEnrollment($student);

        $response = $this->loadRoster();

        // One row, not two: the school enrollment is not a row this module
        // can see.
        $this->assertSame(['Hamza Iqbal'], $this->rosterNames($response));
        $this->assertSame(
            MadrassaDailyRecord::ACADEMIC_TRACK,
            $response->viewData('roster')->first()->academic_track
        );
    }

    public function test_dars_e_nizami_students_appear_on_their_own_roster(): void
    {
        $this->darsEnrollment($this->student('Bilal Ahmad', 'Dars-e-Nizami'));
        $this->darsEnrollment($this->student('Owais Raza', 'Dars-e-Nizami + Computer'));

        $response = $this->loadRoster([
            'department_id' => $this->darsENizami->id,
            'academic_class_id' => $this->salEAwwal->id,
        ]);

        $this->assertEqualsCanonicalizing(
            ['Bilal Ahmad', 'Owais Raza'],
            $this->rosterNames($response)
        );
        // Both programmes are kept on the one Dars-e-Nizami record type.
        $response->assertSee(MadrassaDailyRecord::TYPE_DARS_E_NIZAMI);
    }

    /* ---------------------------------------------------------------- */
    /* 6-11: filters */
    /* ---------------------------------------------------------------- */

    public function test_the_department_filter_narrows_the_roster(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $this->darsEnrollment($this->student('Bilal Ahmad', 'Dars-e-Nizami'));

        $this->assertSame(['Ahtesham Shakeel'], $this->rosterNames($this->loadRoster()));

        $this->assertSame(['Bilal Ahmad'], $this->rosterNames($this->loadRoster([
            'department_id' => $this->darsENizami->id,
            'academic_class_id' => $this->salEAwwal->id,
        ])));
    }

    public function test_the_class_filter_narrows_the_roster(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $this->madrassaEnrollment($this->student('Zaid Khan'), [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $this->assertSame(['Ahtesham Shakeel'], $this->rosterNames($this->loadRoster()));
        $this->assertSame(['Zaid Khan'], $this->rosterNames($this->loadRoster([
            'academic_class_id' => $this->nazra->id,
        ])));
    }

    public function test_the_section_filter_narrows_the_roster(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'), ['section_id' => $this->hifzA->id]);
        $this->madrassaEnrollment($this->student('Zaid Khan'), ['section_id' => $this->hifzB->id]);

        $this->assertSame(['Ahtesham Shakeel'], $this->rosterNames($this->loadRoster([
            'section_id' => $this->hifzA->id,
        ])));
        $this->assertSame(['Zaid Khan'], $this->rosterNames($this->loadRoster([
            'section_id' => $this->hifzB->id,
        ])));
        // No section chosen draws the whole class.
        $this->assertCount(2, $this->rosterNames($this->loadRoster()));
    }

    public function test_the_session_filter_narrows_the_roster(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $this->madrassaEnrollment($this->student('Zaid Khan'), [
            'academic_session_id' => $this->nextSession->id,
            'start_date' => '2027-04-01',
        ]);

        $this->assertSame(['Ahtesham Shakeel'], $this->rosterNames($this->loadRoster([
            'academic_session_id' => $this->session->id,
        ])));
        $this->assertSame(['Zaid Khan'], $this->rosterNames($this->loadRoster([
            'academic_session_id' => $this->nextSession->id,
        ])));
    }

    public function test_the_date_decides_which_days_records_the_roster_shows(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $this->record($enrollment, ['record_date' => self::MONDAY]);

        $monday = $this->loadRoster(['record_date' => self::MONDAY]);
        $tuesday = $this->loadRoster(['record_date' => self::TUESDAY]);

        // The same student both days; only Monday has a record against it.
        $this->assertNotNull($monday->viewData('roster')->first()->dailyRecordForDate);
        $this->assertNull($tuesday->viewData('roster')->first()->dailyRecordForDate);
    }

    public function test_the_roster_filters_combine_with_and_logic(): void
    {
        $this->darsEnrollment($this->student('Bilal Ahmad', 'Dars-e-Nizami'));

        // A department and a class from different branches. The student
        // matches each on its own but not both together, so the roster is
        // empty rather than one filter quietly winning.
        $this->assertSame([], $this->rosterNames($this->loadRoster([
            'department_id' => $this->darsENizami->id,
            'academic_class_id' => $this->hifzClass->id,
        ])));

        $this->assertSame(['Bilal Ahmad'], $this->rosterNames($this->loadRoster([
            'department_id' => $this->darsENizami->id,
            'academic_class_id' => $this->salEAwwal->id,
        ])));
    }

    public function test_the_program_filter_narrows_the_roster(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel', 'Hifz'));
        $this->madrassaEnrollment($this->student('Bilal Ahmad', 'Dars-e-Nizami'));

        $this->assertSame(['Ahtesham Shakeel'], $this->rosterNames($this->loadRoster([
            'record_type' => MadrassaDailyRecord::TYPE_HIFZ,
        ])));
        $this->assertSame(['Bilal Ahmad'], $this->rosterNames($this->loadRoster([
            'record_type' => MadrassaDailyRecord::TYPE_DARS_E_NIZAMI,
        ])));
    }

    public function test_a_filter_cannot_reach_a_student_whose_enrollment_has_ended(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $this->madrassaEnrollment($this->student('Zaid Khan'), ['status' => 'Completed', 'end_date' => '2026-06-30']);
        $this->madrassaEnrollment($this->student('Owais Raza'), ['status' => 'Left', 'end_date' => '2026-06-30']);

        $this->assertSame(['Ahtesham Shakeel'], $this->rosterNames($this->loadRoster()));
    }

    /* ---------------------------------------------------------------- */
    /* 12-14: search */
    /* ---------------------------------------------------------------- */

    public function test_the_roster_searches_by_student_name(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $this->madrassaEnrollment($this->student('Zaid Khan'));

        $this->assertSame(['Ahtesham Shakeel'], $this->rosterNames($this->loadRoster(['search' => 'Ahtesham'])));
    }

    public function test_the_roster_searches_by_registration_number(): void
    {
        $wanted = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($wanted);
        $this->madrassaEnrollment($this->student('Zaid Khan'));

        $this->assertSame(
            ['Ahtesham Shakeel'],
            $this->rosterNames($this->loadRoster(['search' => $wanted->registration_number]))
        );
    }

    public function test_the_roster_searches_by_roll_number(): void
    {
        $wanted = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($wanted);
        $this->madrassaEnrollment($this->student('Zaid Khan'));

        $this->assertSame(
            ['Ahtesham Shakeel'],
            $this->rosterNames($this->loadRoster(['search' => $wanted->roll_number]))
        );
    }

    public function test_search_combines_with_the_placement_filters(): void
    {
        $inHifz = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($inHifz);

        // The same search term, but a class the student is not in.
        $this->assertSame([], $this->rosterNames($this->loadRoster([
            'search' => 'Ahtesham',
            'academic_class_id' => $this->nazra->id,
        ])));

        $this->assertSame(['Ahtesham Shakeel'], $this->rosterNames($this->loadRoster([
            'search' => 'Ahtesham',
        ])));
    }

    /* ---------------------------------------------------------------- */
    /* 15-18: status and actions */
    /* ---------------------------------------------------------------- */

    public function test_a_student_with_a_record_shows_recorded(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $record = $this->record($enrollment, ['record_date' => self::MONDAY]);

        $response = $this->loadRoster();

        $response->assertOk()->assertSee('Recorded');
        $this->assertSame(
            $record->id,
            $response->viewData('roster')->first()->dailyRecordForDate->id
        );
    }

    public function test_a_student_without_a_record_shows_not_recorded(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $response = $this->loadRoster();

        $response->assertOk()->assertSee('Not Recorded');
        $this->assertNull($response->viewData('roster')->first()->dailyRecordForDate);
    }

    public function test_the_add_action_opens_the_right_student_enrollment_and_date(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $response = $this->loadRoster();

        // The link the roster draws names this student's enrollment and the
        // day that was loaded. Asserted on the escaped markup, since Blade
        // writes the ampersands as entities.
        $response->assertSee(
            'hifz/create?student_academic_enrollment_id='.$enrollment->id.'&amp;record_date='.self::MONDAY,
            false
        );

        $form = $this->get(route('hifz.create', [
            'student_academic_enrollment_id' => $enrollment->id,
            'record_date' => self::MONDAY,
        ]));

        $form->assertOk();
        $form->assertSee('Ahtesham Shakeel');
        $this->assertSame($enrollment->id, $form->viewData('enrollment')->id);
        $this->assertSame(self::MONDAY, $form->viewData('recordDate'));
        // The record type is already settled: the form never asks.
        $this->assertSame(MadrassaDailyRecord::TYPE_HIFZ, $form->viewData('recordType'));
    }

    public function test_the_add_action_preselects_the_madrassa_enrollment_for_a_dual_track_student(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $form = $this->get(route('hifz.create', [
            'student_academic_enrollment_id' => $this->loadRoster()->viewData('roster')->first()->id,
            'record_date' => self::MONDAY,
        ]));

        $form->assertOk();
        $this->assertSame($madrassa->id, $form->viewData('enrollment')->id);
        $this->assertNotSame($school->id, $form->viewData('enrollment')->id);
    }

    public function test_a_recorded_day_offers_view_and_edit_but_not_add(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $record = $this->record($enrollment, ['record_date' => self::MONDAY]);

        $response = $this->loadRoster();

        $response->assertSee(route('hifz.show', $record->id), false);
        $response->assertDontSee('Add Daily Record');

        // And the create link for this student and day is not on the page
        // at all, so a duplicate cannot be started by clicking.
        $response->assertDontSee(
            route('hifz.create', [
                'student_academic_enrollment_id' => $enrollment->id,
                'record_date' => self::MONDAY,
            ]),
            false
        );
    }

    public function test_the_view_action_opens_the_record(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $record = $this->record($enrollment);

        $this->get(route('hifz.show', $record->id))
            ->assertOk()
            ->assertSee('Ahtesham Shakeel')
            ->assertSee($record->record_date->format('l, d F Y'));
    }

    public function test_the_edit_action_opens_the_record_for_correction(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $record = $this->record($enrollment);

        $edit = $this->get(route('hifz.edit', $record->id));

        $edit->assertOk();
        $this->assertSame($record->id, $edit->viewData('record')->id);
        $this->assertSame($enrollment->id, $edit->viewData('enrollment')->id);

        $this->put(route('hifz.update', $record->id), $this->hifzPayload($enrollment, [
            'remarks' => 'Corrected from the roster',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Corrected from the roster', $record->fresh()->remarks);
    }

    /* ---------------------------------------------------------------- */
    /* The date: weekends, and never writing on a read */
    /* ---------------------------------------------------------------- */

    public function test_a_sunday_shows_the_off_day_panel_instead_of_a_roster(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $response = $this->loadRoster(['record_date' => self::SUNDAY]);

        $response->assertOk();
        $response->assertSee('Weekly Off Day');
        $this->assertSame('off_day', $response->viewData('rosterState'));
        // No roster is even queried for a day nobody sits.
        $this->assertNull($response->viewData('roster'));
    }

    public function test_a_saturday_shows_a_roster_like_any_other_working_day(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $response = $this->loadRoster(['record_date' => self::SATURDAY]);

        $response->assertOk();
        $response->assertDontSee('Weekly Off Day');
        $this->assertNotSame('off_day', $response->viewData('rosterState'));
        $this->assertNotNull($response->viewData('roster'));
    }

    public function test_the_off_day_still_cannot_create_a_record(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $this->post(route('hifz.store'), $this->hifzPayload($enrollment, ['record_date' => self::SUNDAY]))
            ->assertSessionHasErrors('record_date');

        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_loading_a_date_never_creates_a_record(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $this->madrassaEnrollment($this->student('Zaid Khan'));

        foreach ([self::MONDAY, self::TUESDAY, self::WEDNESDAY, self::SATURDAY] as $date) {
            $this->loadRoster(['record_date' => $date])->assertOk();
        }

        // Reading a day is reading. Nothing is filled in on the student's
        // behalf, and an unrecorded day stays unrecorded.
        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_the_roster_waits_for_a_class_before_loading(): void
    {
        $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));

        $response = $this->get(route('hifz.index', ['record_date' => self::MONDAY]));

        $response->assertOk();
        $this->assertSame('needs_class', $response->viewData('rosterState'));
        $this->assertNull($response->viewData('roster'));
        $response->assertSee('Choose a class to load the roster.');
    }

    public function test_the_page_is_idle_until_a_date_is_chosen(): void
    {
        $response = $this->get(route('hifz.index'));

        $response->assertOk();
        $this->assertSame('idle', $response->viewData('rosterState'));
        $this->assertNull($response->viewData('roster'));
    }

    public function test_a_date_outside_the_enrollment_period_cannot_be_recorded(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'), [
            'start_date' => self::WEDNESDAY,
        ]);

        $this->post(route('hifz.store'), $this->hifzPayload($enrollment, ['record_date' => self::MONDAY]))
            ->assertSessionHasErrors('record_date');

        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    /* ---------------------------------------------------------------- */
    /* 21-23: student history and the profile */
    /* ---------------------------------------------------------------- */

    public function test_the_student_history_page_lists_that_students_records(): void
    {
        $mine = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $theirs = $this->madrassaEnrollment($this->student('Zaid Khan'));

        $this->record($mine, ['record_date' => self::MONDAY]);
        $this->record($mine, ['record_date' => self::WEDNESDAY]);
        $this->record($theirs, ['record_date' => self::MONDAY]);

        $response = $this->get(route('students.hifz', $mine->student_id));

        $response->assertOk();
        $response->assertSee('Ahtesham Shakeel');

        $records = $response->viewData('records');

        $this->assertCount(2, $records);
        // Newest first.
        $this->assertSame(self::WEDNESDAY, $records->first()->record_date->format('Y-m-d'));
        $this->assertSame(2, $response->viewData('summary')['total']);
    }

    public function test_the_history_filters_by_date_range_session_and_program(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->record($enrollment, ['record_date' => self::MONDAY]);
        $this->record($enrollment, ['record_date' => self::WEDNESDAY]);
        $this->record($enrollment, ['record_date' => self::FRIDAY]);

        $ranged = $this->get(route('students.hifz', [
            'student' => $student->id,
            'date_from' => self::TUESDAY,
            'date_to' => self::THURSDAY,
        ]));

        $this->assertCount(1, $ranged->viewData('records'));
        $this->assertSame(self::WEDNESDAY, $ranged->viewData('records')->first()->record_date->format('Y-m-d'));

        $this->assertCount(3, $this->get(route('students.hifz', [
            'student' => $student->id,
            'academic_session_id' => $this->session->id,
        ]))->viewData('records'));

        $this->assertCount(0, $this->get(route('students.hifz', [
            'student' => $student->id,
            'academic_session_id' => $this->nextSession->id,
        ]))->viewData('records'));

        $this->assertCount(3, $this->get(route('students.hifz', [
            'student' => $student->id,
            'record_type' => MadrassaDailyRecord::TYPE_HIFZ,
        ]))->viewData('records'));

        $this->assertCount(0, $this->get(route('students.hifz', [
            'student' => $student->id,
            'record_type' => MadrassaDailyRecord::TYPE_DARS_E_NIZAMI,
        ]))->viewData('records'));
    }

    public function test_the_history_is_paginated(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $date = Carbon::parse(self::MONDAY);

        for ($created = 0; $created < 25; $created++) {
            while (! MadrassaDailyRecord::isRecordableDay($date)) {
                $date->addDay();
            }

            $this->record($enrollment, ['record_date' => $date->format('Y-m-d')]);
            $date->addDay();
        }

        $first = $this->get(route('students.hifz', $student->id));

        $first->assertOk();
        $first->assertSee('Showing 1 to 20 of 25 daily records');
        $this->assertCount(20, $first->viewData('records')->items());

        $this->assertCount(
            5,
            $this->get(route('students.hifz', ['student' => $student->id, 'page' => 2]))->viewData('records')->items()
        );
    }

    public function test_the_student_profile_links_to_the_history_and_summarises_it(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->record($enrollment, ['record_date' => self::MONDAY]);
        $this->record($enrollment, ['record_date' => self::WEDNESDAY]);

        $profile = $this->get(route('students.show', $student->id));

        $profile->assertOk();
        $profile->assertSee('Hifz &amp; Quran / Daily Academic Record', false);
        $profile->assertSee(route('students.hifz', $student->id), false);

        $summary = $profile->viewData('madrassaDailyRecordSummary');

        $this->assertSame(2, $summary['total']);
        $this->assertSame(self::WEDNESDAY, $summary['latest_date']->format('Y-m-d'));
        $this->assertSame(MadrassaDailyRecord::TYPE_HIFZ, $summary['record_type']);
    }

    public function test_a_school_only_student_gets_no_profile_section_and_no_history(): void
    {
        $student = $this->student('Usman Tariq', 'School');
        $this->schoolEnrollment($student);

        $profile = $this->get(route('students.show', $student->id));

        $profile->assertOk();
        $this->assertNull($profile->viewData('madrassaDailyRecordSummary'));
        $profile->assertDontSee('Hifz &amp; Quran / Daily Academic Record', false);

        // The history page is reachable by URL but has nothing to show: it
        // can only ever read the student's madrassa enrollments, and there
        // are none.
        $history = $this->get(route('students.hifz', $student->id));

        $history->assertOk();
        $this->assertCount(0, $history->viewData('records'));
    }

    public function test_the_record_detail_page_offers_the_three_ways_back(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $record = $this->record($this->madrassaEnrollment($student));

        $response = $this->get(route('hifz.show', $record->id));

        $response->assertOk();
        $response->assertSee('Back to Hifz &amp; Quran', false);
        $response->assertSee('Back to Student Profile');
        $response->assertSee('Edit Record');
        $response->assertSee(route('hifz.index'), false);
        $response->assertSee(route('hifz.edit', $record->id), false);
        $response->assertSee(route('students.show', $student->id).'#hifz-quran', false);
    }

    /* ---------------------------------------------------------------- */
    /* 24: historical accuracy */
    /* ---------------------------------------------------------------- */

    public function test_a_record_still_shows_nazra_section_a_after_promotion_to_hifz_section_b(): void
    {
        $student = $this->student('Ahtesham Shakeel');

        // 1 August is a Saturday in 2026, so the record is dated the first
        // working day of the month instead. The placement is the point.
        $nazra = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->hifzA->id,
        ]);
        $record = $this->record($nazra, ['record_date' => self::MONDAY]);

        $student->promote([
            'academic_track' => 'Madrassa',
            'academic_session_id' => $this->nextSession->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'promotion_date' => '2027-04-01',
        ]);

        $student->refresh();

        // The student has moved on.
        $current = $student->activeEnrollmentForTrack('Madrassa');
        $this->assertSame($this->hifzClass->id, $current->academic_class_id);
        $this->assertSame($this->hifzB->id, $current->section_id);

        // The record has not.
        $history = $this->get(route('students.hifz', $student->id));
        $listed = $history->viewData('records')->first();

        $this->assertSame($nazra->id, $listed->student_academic_enrollment_id);
        $this->assertSame('Nazra', $listed->studentAcademicEnrollment->academicClass->name);
        $this->assertSame('Hifz-A', $listed->studentAcademicEnrollment->section->name);
        $this->assertSame($this->session->id, $listed->studentAcademicEnrollment->academic_session_id);

        // And so does the detail page, which reads the same stored id.
        $detail = $this->get(route('hifz.show', $record->id));

        $detail->assertOk();
        $this->assertSame('Nazra', $detail->viewData('enrollment')->academicClass->name);
        $this->assertSame('Hifz-A', $detail->viewData('enrollment')->section->name);
    }

    public function test_the_history_keeps_records_from_every_placement(): void
    {
        $student = $this->student('Ahtesham Shakeel');

        $nazra = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);
        $this->record($nazra, ['record_date' => self::MONDAY]);

        $promoted = $student->promote([
            'academic_track' => 'Madrassa',
            'academic_session_id' => $this->nextSession->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'promotion_date' => '2027-04-01',
        ]);

        // 2027-08-02 is a Monday.
        $this->record($promoted->loadMissing('student'), ['record_date' => '2027-08-02']);

        $history = $this->get(route('students.hifz', $student->id));

        // Being promoted does not start the history over.
        $this->assertCount(2, $history->viewData('records'));
        $this->assertSame(2, $history->viewData('summary')['placements']);
    }

    /* ---------------------------------------------------------------- */
    /* 25-28: Hifz + School, duplicates and security */
    /* ---------------------------------------------------------------- */

    public function test_a_school_enrollment_never_appears_anywhere_in_the_module(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->record($madrassa, ['record_date' => self::MONDAY]);

        // Roster.
        $roster = $this->loadRoster()->viewData('roster');
        $this->assertSame([$madrassa->id], $roster->pluck('id')->all());

        // Student history.
        $history = $this->get(route('students.hifz', $student->id));
        $this->assertSame(
            [$madrassa->id],
            $history->viewData('records')->pluck('student_academic_enrollment_id')->unique()->values()->all()
        );

        // The create form refuses it outright.
        $this->get(route('hifz.create', ['student_academic_enrollment_id' => $school->id]))
            ->assertRedirect(route('hifz.index'))
            ->assertSessionHas('error');

        // And so does the save.
        $this->post(route('hifz.store'), $this->hifzPayload($school, ['record_date' => self::TUESDAY]))
            ->assertSessionHasErrors('student_academic_enrollment_id');

        $this->assertDatabaseCount('madrassa_daily_records', 1);
    }

    public function test_a_duplicate_daily_record_is_still_blocked(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $record = $this->record($enrollment, ['record_date' => self::MONDAY]);

        // The interface never offers a second one: the roster sends the
        // administrator to the record that exists.
        $this->loadRoster()->assertDontSee('Add Daily Record');

        $this->get(route('hifz.create', [
            'student_academic_enrollment_id' => $enrollment->id,
            'record_date' => self::MONDAY,
        ]))->assertRedirect(route('hifz.edit', $record->id));

        // The server refuses one that is posted anyway.
        $this->post(route('hifz.store'), $this->hifzPayload($enrollment, ['record_date' => self::MONDAY]))
            ->assertSessionHasErrors('record_date');

        // And the unique index refuses one written straight to the table.
        $this->expectException(QueryException::class);
        $this->record($enrollment, ['record_date' => self::MONDAY]);
    }

    public function test_an_invalid_student_and_enrollment_combination_is_rejected(): void
    {
        $mine = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $theirs = $this->madrassaEnrollment($this->student('Zaid Khan'));

        $this->post(route('hifz.store'), $this->hifzPayload($theirs, [
            'student_id' => $mine->student_id,
        ]))->assertSessionHasErrors('student_academic_enrollment_id');

        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_a_non_active_enrollment_cannot_be_opened_from_a_manipulated_link(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Zaid Khan'), [
            'status' => 'Completed',
            'end_date' => '2026-06-30',
        ]);

        $this->get(route('hifz.create', [
            'student_academic_enrollment_id' => $enrollment->id,
            'record_date' => self::MONDAY,
        ]))->assertRedirect(route('hifz.index'))->assertSessionHas('error');

        $this->post(route('hifz.store'), $this->hifzPayload($enrollment))
            ->assertSessionHasErrors('student_academic_enrollment_id');
    }

    /* ---------------------------------------------------------------- */
    /* 29: performance */
    /* ---------------------------------------------------------------- */

    public function test_the_roster_query_count_does_not_grow_with_the_class(): void
    {
        $teacher = $this->teacher();

        $counts = [];

        foreach ([5, 15, 30] as $target) {
            $this->fillClassTo($target, $teacher->id);
            $counts[$target] = $this->countRosterQueries();
        }

        // 5, then 15, then 30 students on one roster, at the same cost.
        // Equality is the assertion that matters: it is what proves nothing
        // is being fetched per student.
        $this->assertSame($counts[5], $counts[15]);
        $this->assertSame($counts[5], $counts[30]);

        // And the fixed cost stays a page's worth of queries rather than
        // creeping up as the module grows.
        $this->assertLessThan(30, $counts[30]);
    }

    /**
     * Add students to the roster's class until it holds a given number.
     *
     * Half of them get a record for the day, so the count covers the rows
     * that render a teacher and a work summary as well as the empty ones.
     */
    private function fillClassTo(int $target, int $teacherId): void
    {
        $existing = $this->loadRoster()->viewData('roster')->count();

        for ($index = $existing; $index < $target; $index++) {
            $enrollment = $this->madrassaEnrollment($this->student('Student '.$index.' '.uniqid()));

            if ($index % 2 === 0) {
                $this->record($enrollment, ['record_date' => self::MONDAY, 'teacher_id' => $teacherId]);
            }
        }
    }

    /**
     * Count the queries one render of the roster costs.
     *
     * A warm-up request first: the permission and role lookups are cached
     * per process, so measuring the first request of a run would count
     * them once and never again.
     */
    private function countRosterQueries(): int
    {
        $this->loadRoster()->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->loadRoster()->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_the_student_history_query_count_does_not_grow_with_the_records(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);
        $teacher = $this->teacher();

        $date = Carbon::parse(self::MONDAY);
        $add = function (int $count) use (&$date, $enrollment, $teacher) {
            for ($index = 0; $index < $count; $index++) {
                while (! MadrassaDailyRecord::isRecordableDay($date)) {
                    $date->addDay();
                }

                $this->record($enrollment, [
                    'record_date' => $date->format('Y-m-d'),
                    'teacher_id' => $teacher->id,
                ]);
                $date->addDay();
            }
        };

        $add(3);
        $withThree = $this->countHistoryQueries($student->id);

        $add(9);
        $withTwelve = $this->countHistoryQueries($student->id);

        $this->assertSame($withThree, $withTwelve);
    }

    private function countHistoryQueries(int $studentId): int
    {
        $this->get(route('students.hifz', $studentId))->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('students.hifz', $studentId))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_the_roster_does_not_read_attendance(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $this->record($enrollment, ['record_date' => self::MONDAY]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->loadRoster()->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        DB::disableQueryLog();

        // Whether a day's work was written down is answered by
        // madrassa_daily_records alone. Attendance is a different question
        // in a different module.
        $this->assertStringNotContainsString('student_attendances', $queries);
        $this->assertStringContainsString('madrassa_daily_records', $queries);
    }

    /* ---------------------------------------------------------------- */
    /* 30: authorisation */
    /* ---------------------------------------------------------------- */

    public function test_a_guest_cannot_reach_the_roster_or_the_history(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $record = $this->record($this->madrassaEnrollment($student));

        auth()->logout();

        $this->get(route('hifz.index'))->assertRedirect(route('login'));
        $this->get(route('students.hifz', $student->id))->assertRedirect(route('login'));
        $this->get(route('hifz.show', $record->id))->assertRedirect(route('login'));
        $this->get(route('hifz.edit', $record->id))->assertRedirect(route('login'));
    }
}
