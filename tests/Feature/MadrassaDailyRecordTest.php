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
 * Covers the Hifz & Quran daily academic record.
 *
 * The module records what a madrassa student did on a day, which is a
 * different question from whether they were present. The cases that matter
 * most are the dual-track ones: a Hifz + School student holds a madrassa
 * and a school enrollment at the same time, and only the madrassa one may
 * ever carry a daily record.
 *
 * The academic structure these tests are written against is built by
 * BuildsMadrassaFixtures, shared with the roster tests so both describe one
 * madrassa rather than two that happen to look alike.
 */
class MadrassaDailyRecordTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMadrassa();
    }

    /* ---------------------------------------------------------------- */
    /* 1-7: creating a record for each kind of madrassa student */
    /* ---------------------------------------------------------------- */

    public function test_madrassa_student_can_create_a_daily_record(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $response = $this->post(route('hifz.store'), $this->hifzPayload($enrollment));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('hifz.index'));

        $this->assertDatabaseHas('madrassa_daily_records', [
            'student_academic_enrollment_id' => $enrollment->id,
            'record_date' => self::MONDAY,
            'record_type' => MadrassaDailyRecord::TYPE_HIFZ,
        ]);
    }

    public function test_school_only_student_cannot_create_a_daily_record(): void
    {
        $student = $this->student('Usman Tariq', 'School');
        $enrollment = $this->schoolEnrollment($student);

        $response = $this->post(route('hifz.store'), $this->hifzPayload($enrollment));

        $response->assertSessionHasErrors('student_academic_enrollment_id');
        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_hifz_student_can_record_hifz_daily_work(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->post(route('hifz.store'), $this->hifzPayload($enrollment))
            ->assertSessionHasNoErrors();

        $record = MadrassaDailyRecord::firstOrFail();

        $this->assertSame(MadrassaDailyRecord::TYPE_HIFZ, $record->record_type);
        $this->assertSame('1 page', $record->sabaq_quantity);
        $this->assertSame('3 pages', $record->sabqi_quantity);
        $this->assertSame('1 para', $record->manzil_quantity);
        $this->assertSame('Surah Al-Baqarah, ayah 20 onwards', $record->next_sabaq);
        $this->assertSame('Good performance', $record->remarks);

        // The Dars-e-Nizami columns stay empty on a Hifz record.
        $this->assertNull($record->subject_book);
        $this->assertNull($record->todays_lesson);
    }

    public function test_hifz_plus_school_student_records_against_the_madrassa_enrollment(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->post(route('hifz.store'), $this->hifzPayload($madrassa))
            ->assertSessionHasNoErrors();

        $record = MadrassaDailyRecord::firstOrFail();

        $this->assertSame($madrassa->id, $record->student_academic_enrollment_id);
        $this->assertNotSame($school->id, $record->student_academic_enrollment_id);
        $this->assertSame('Madrassa', $record->studentAcademicEnrollment->academic_track);
    }

    public function test_a_school_enrollment_cannot_be_used_for_a_daily_record(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $response = $this->post(route('hifz.store'), $this->hifzPayload($school));

        $response->assertSessionHasErrors('student_academic_enrollment_id');
        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_dars_e_nizami_student_can_create_a_dars_e_nizami_record(): void
    {
        $enrollment = $this->darsEnrollment();

        $this->post(route('hifz.store'), $this->darsPayload($enrollment))
            ->assertSessionHasNoErrors();

        $record = MadrassaDailyRecord::firstOrFail();

        $this->assertSame(MadrassaDailyRecord::TYPE_DARS_E_NIZAMI, $record->record_type);
        $this->assertSame('Nahw Mir', $record->subject_book);
        $this->assertSame('Lesson 12', $record->todays_lesson);
        $this->assertSame('Lessons 9 to 11', $record->revision);
        $this->assertSame('Lesson 13', $record->next_lesson);
    }

    public function test_dars_e_nizami_is_not_forced_to_provide_hifz_fields(): void
    {
        $enrollment = $this->darsEnrollment();

        // Nothing Hifz-shaped is submitted at all, and only one of the
        // Dars-e-Nizami fields is filled in.
        $response = $this->post(route('hifz.store'), [
            'student_id' => $enrollment->student_id,
            'student_academic_enrollment_id' => $enrollment->id,
            'record_date' => self::MONDAY,
            'todays_lesson' => 'Lesson 12',
        ]);

        $response->assertSessionHasNoErrors();

        $record = MadrassaDailyRecord::firstOrFail();

        $this->assertNull($record->sabaq);
        $this->assertNull($record->sabaq_quantity);
        $this->assertNull($record->manzil);
    }

    public function test_hifz_fields_are_rejected_on_a_dars_e_nizami_record(): void
    {
        $enrollment = $this->darsEnrollment();

        $response = $this->post(route('hifz.store'), $this->darsPayload($enrollment, [
            'sabaq' => 'Surah Al-Baqarah',
            'sabaq_quantity' => '1 page',
        ]));

        $response->assertSessionHasErrors(['sabaq', 'sabaq_quantity']);
        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_dars_e_nizami_fields_are_rejected_on_a_hifz_record(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $response = $this->post(route('hifz.store'), $this->hifzPayload($enrollment, [
            'subject_book' => 'Nahw Mir',
        ]));

        $response->assertSessionHasErrors('subject_book');
        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_a_record_with_no_daily_work_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $response = $this->post(route('hifz.store'), [
            'student_id' => $enrollment->student_id,
            'student_academic_enrollment_id' => $enrollment->id,
            'record_date' => self::MONDAY,
            'remarks' => 'Nothing in particular',
        ]);

        $response->assertSessionHasErrors('sabaq');
        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_the_record_type_comes_from_the_student_not_the_request(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // A hand-edited request claiming the other programme. The type is
        // resolved from the enrollment's student, so the claim is ignored.
        $this->post(route('hifz.store'), $this->hifzPayload($enrollment, [
            'record_type' => MadrassaDailyRecord::TYPE_DARS_E_NIZAMI,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            MadrassaDailyRecord::TYPE_HIFZ,
            MadrassaDailyRecord::firstOrFail()->record_type
        );
    }

    /* ---------------------------------------------------------------- */
    /* 8: one record per student per day */
    /* ---------------------------------------------------------------- */

    public function test_a_duplicate_record_for_the_same_student_and_date_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $this->record($enrollment);

        $response = $this->post(route('hifz.store'), $this->hifzPayload($enrollment));

        $response->assertSessionHasErrors('record_date');
        $this->assertDatabaseCount('madrassa_daily_records', 1);
    }

    public function test_the_database_refuses_a_duplicate_record(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $this->record($enrollment);

        $this->expectException(QueryException::class);

        $this->record($enrollment);
    }

    public function test_the_same_date_is_allowed_for_two_different_students(): void
    {
        $first = $this->madrassaEnrollment();
        $second = $this->madrassaEnrollment($this->student('Zaid Khan'));

        $this->post(route('hifz.store'), $this->hifzPayload($first))->assertSessionHasNoErrors();
        $this->post(route('hifz.store'), $this->hifzPayload($second))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('madrassa_daily_records', 2);
    }

    public function test_opening_a_record_for_a_day_already_recorded_goes_to_the_edit_page(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $record = $this->record($enrollment);

        $this->get(route('hifz.create', [
            'student_academic_enrollment_id' => $enrollment->id,
            'record_date' => self::MONDAY,
        ]))->assertRedirect(route('hifz.edit', $record->id));
    }

    /* ---------------------------------------------------------------- */
    /* 9: editing */
    /* ---------------------------------------------------------------- */

    public function test_an_existing_record_can_be_edited(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $record = $this->record($enrollment);

        $this->get(route('hifz.edit', $record->id))->assertOk();

        $response = $this->put(route('hifz.update', $record->id), $this->hifzPayload($enrollment, [
            'sabaq_quantity' => 'half page',
            'remarks' => 'Needs more revision',
        ]));

        $response->assertSessionHasNoErrors();

        $record->refresh();

        $this->assertSame('half page', $record->sabaq_quantity);
        $this->assertSame('Needs more revision', $record->remarks);
        $this->assertDatabaseCount('madrassa_daily_records', 1);
    }

    public function test_editing_does_not_conflict_with_the_records_own_date(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $record = $this->record($enrollment, ['record_date' => self::TUESDAY]);

        $this->put(route('hifz.update', $record->id), $this->hifzPayload($enrollment, [
            'record_date' => self::TUESDAY,
        ]))->assertSessionHasNoErrors();
    }

    public function test_editing_onto_a_date_another_record_already_holds_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $this->record($enrollment, ['record_date' => self::MONDAY]);
        $second = $this->record($enrollment, ['record_date' => self::TUESDAY]);

        $this->put(route('hifz.update', $second->id), $this->hifzPayload($enrollment, [
            'record_date' => self::MONDAY,
        ]))->assertSessionHasErrors('record_date');
    }

    public function test_a_record_can_still_be_corrected_after_its_enrollment_is_completed(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $record = $this->record($enrollment);

        // The placement has ended, but the day it covers still happened.
        $enrollment->update(['status' => 'Completed', 'end_date' => '2026-12-31']);

        $this->put(route('hifz.update', $record->id), $this->hifzPayload($enrollment, [
            'remarks' => 'Corrected afterwards',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Corrected afterwards', $record->fresh()->remarks);
    }

    /* ---------------------------------------------------------------- */
    /* 10-13: the enrollment a record may be written against */
    /* ---------------------------------------------------------------- */

    public function test_a_record_naming_an_enrollment_that_does_not_exist_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $response = $this->post(route('hifz.store'), $this->hifzPayload($enrollment, [
            'student_academic_enrollment_id' => $enrollment->id + 999,
        ]));

        $response->assertSessionHasErrors('student_academic_enrollment_id');
        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_the_enrollment_must_belong_to_the_selected_student(): void
    {
        $mine = $this->madrassaEnrollment();
        $theirs = $this->madrassaEnrollment($this->student('Zaid Khan'));

        // My student id, somebody else's enrollment.
        $response = $this->post(route('hifz.store'), $this->hifzPayload($theirs, [
            'student_id' => $mine->student_id,
        ]));

        $response->assertSessionHasErrors('student_academic_enrollment_id');
        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_the_enrollment_must_be_on_the_madrassa_track(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->post(route('hifz.store'), $this->hifzPayload($school))
            ->assertSessionHasErrors('student_academic_enrollment_id');
    }

    public function test_a_non_active_enrollment_cannot_be_used_for_a_new_record(): void
    {
        $enrollment = $this->madrassaEnrollment(null, [
            'status' => 'Completed',
            'end_date' => '2026-12-31',
        ]);

        $response = $this->post(route('hifz.store'), $this->hifzPayload($enrollment));

        $response->assertSessionHasErrors('student_academic_enrollment_id');
        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_the_create_form_refuses_an_enrollment_it_cannot_record_against(): void
    {
        $student = $this->student('Usman Tariq', 'School');
        $school = $this->schoolEnrollment($student);

        $this->get(route('hifz.create', ['student_academic_enrollment_id' => $school->id]))
            ->assertRedirect(route('hifz.index'))
            ->assertSessionHas('error');
    }

    public function test_the_create_form_opens_for_a_madrassa_enrollment(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->get(route('hifz.create', [
            'student_academic_enrollment_id' => $enrollment->id,
            'record_date' => self::MONDAY,
        ]))->assertOk()->assertSee('Sabaq');
    }

    /* ---------------------------------------------------------------- */
    /* 14-17: the date */
    /* ---------------------------------------------------------------- */

    public function test_a_date_before_the_enrollment_started_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment(null, ['start_date' => '2026-08-04']);

        $response = $this->post(route('hifz.store'), $this->hifzPayload($enrollment, [
            'record_date' => self::MONDAY,
        ]));

        $response->assertSessionHasErrors('record_date');
        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_a_date_after_the_enrollment_ended_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment(null, ['end_date' => '2026-08-04']);

        $response = $this->post(route('hifz.store'), $this->hifzPayload($enrollment, [
            'record_date' => self::WEDNESDAY,
        ]));

        $response->assertSessionHasErrors('record_date');
        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_the_first_and_last_day_of_an_enrollment_are_allowed(): void
    {
        $enrollment = $this->madrassaEnrollment(null, [
            'start_date' => self::MONDAY,
            'end_date' => self::WEDNESDAY,
        ]);

        $this->post(route('hifz.store'), $this->hifzPayload($enrollment, ['record_date' => self::MONDAY]))
            ->assertSessionHasNoErrors();
        $this->post(route('hifz.store'), $this->hifzPayload($enrollment, ['record_date' => self::WEDNESDAY]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('madrassa_daily_records', 2);
    }

    public function test_a_saturday_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $response = $this->post(route('hifz.store'), $this->hifzPayload($enrollment, [
            'record_date' => self::SATURDAY,
        ]));

        $response->assertSessionHasErrors('record_date');
        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_a_sunday_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $response = $this->post(route('hifz.store'), $this->hifzPayload($enrollment, [
            'record_date' => self::SUNDAY,
        ]));

        $response->assertSessionHasErrors('record_date');
        $this->assertDatabaseCount('madrassa_daily_records', 0);
    }

    public function test_a_date_that_is_not_a_date_is_rejected(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->post(route('hifz.store'), $this->hifzPayload($enrollment, ['record_date' => 'not-a-date']))
            ->assertSessionHasErrors('record_date');
    }

    /* ---------------------------------------------------------------- */
    /* 18-19: historical accuracy */
    /* ---------------------------------------------------------------- */

    public function test_a_record_keeps_its_placement_after_the_student_is_promoted(): void
    {
        $student = $this->student('Ahtesham Shakeel');

        // Recorded while the student was in Nazra / Section A.
        $nazra = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->hifzA->id,
        ]);
        $record = $this->record($nazra);

        // Promoted into Hifz / Section B in the next session.
        $student->promote([
            'academic_track' => 'Madrassa',
            'academic_session_id' => $this->nextSession->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'promotion_date' => '2027-04-01',
        ]);

        $record->refresh()->load('studentAcademicEnrollment.academicClass', 'studentAcademicEnrollment.section');

        $this->assertSame($nazra->id, $record->student_academic_enrollment_id);
        $this->assertSame($this->nazra->id, $record->studentAcademicEnrollment->academic_class_id);
        $this->assertSame('Nazra', $record->studentAcademicEnrollment->academicClass->name);
        $this->assertSame('Hifz-A', $record->studentAcademicEnrollment->section->name);
        $this->assertSame($this->session->id, $record->studentAcademicEnrollment->academic_session_id);

        // And the listing draws the row from the old placement, not from
        // where the student sits now. Read off the view data rather than
        // the markup: the filter selects legitimately name every class and
        // section in the institution.
        $listed = $this->get(route('hifz.index'))->assertOk()->viewData('records');

        $this->assertCount(1, $listed);
        $this->assertSame('Nazra', $listed->first()->studentAcademicEnrollment->academicClass->name);
        $this->assertSame('Hifz-A', $listed->first()->studentAcademicEnrollment->section->name);
    }

    public function test_hifz_plus_school_records_never_reach_the_school_enrollment(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->record($madrassa);

        $this->assertDatabaseHas('madrassa_daily_records', [
            'student_academic_enrollment_id' => $madrassa->id,
        ]);
        $this->assertDatabaseMissing('madrassa_daily_records', [
            'student_academic_enrollment_id' => $school->id,
        ]);

        // The student's own history holds one record, on the madrassa side.
        $listed = $this->get(route('hifz.index', ['student_id' => $student->id]))
            ->assertOk()
            ->viewData('records');

        $this->assertCount(1, $listed);
        $this->assertSame($madrassa->id, $listed->first()->student_academic_enrollment_id);
        $this->assertSame('Madrassa', $listed->first()->studentAcademicEnrollment->academic_track);
    }

    /* ---------------------------------------------------------------- */
    /* 20-23: the listing */
    /* ---------------------------------------------------------------- */

    public function test_the_index_page_loads(): void
    {
        $this->get(route('hifz.index'))->assertOk()->assertSee('Daily Records');
    }

    public function test_search_finds_a_student_by_name_registration_and_roll_number(): void
    {
        $wanted = $this->student('Ahtesham Shakeel');
        $other = $this->student('Zaid Khan');

        $this->record($this->madrassaEnrollment($wanted));
        $this->record($this->madrassaEnrollment($other));

        foreach (['Ahtesham', $wanted->registration_number, $wanted->roll_number] as $term) {
            $this->get(route('hifz.index', ['search' => $term]))
                ->assertOk()
                ->assertSee('Ahtesham Shakeel')
                ->assertDontSee('Zaid Khan');
        }
    }

    public function test_filters_combine_with_and_logic(): void
    {
        $inNazra = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'), [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);
        $inHifz = $this->madrassaEnrollment($this->student('Zaid Khan'));

        $this->record($inNazra);
        $this->record($inHifz);

        // Both filters agree: the Nazra record only.
        $this->get(route('hifz.index', [
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
        ]))->assertOk()->assertSee('Ahtesham Shakeel')->assertDontSee('Zaid Khan');

        // The filters disagree: a class from another department, so nothing
        // matches rather than one filter quietly winning.
        $this->get(route('hifz.index', [
            'department_id' => $this->school->id,
            'academic_class_id' => $this->nazra->id,
        ]))->assertOk()->assertSee('No daily records found.');
    }

    public function test_the_date_and_record_type_filters_narrow_the_listing(): void
    {
        $hifz = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $dars = $this->darsEnrollment($this->student('Bilal Ahmad', 'Dars-e-Nizami'));

        $this->record($hifz, ['record_date' => self::MONDAY]);
        $this->record($dars, ['record_date' => self::TUESDAY]);

        $this->get(route('hifz.index', ['record_type' => MadrassaDailyRecord::TYPE_HIFZ]))
            ->assertOk()->assertSee('Ahtesham Shakeel')->assertDontSee('Bilal Ahmad');

        $this->get(route('hifz.index', ['record_date' => self::TUESDAY]))
            ->assertOk()->assertSee('Bilal Ahmad')->assertDontSee('Ahtesham Shakeel');
    }

    public function test_the_listing_is_paginated(): void
    {
        $enrollment = $this->madrassaEnrollment();

        // Twenty-five working days from the Monday, so one page fills and
        // the next one starts.
        $date = Carbon::parse(self::MONDAY);

        for ($created = 0; $created < 25; $created++) {
            while (! MadrassaDailyRecord::isRecordableDay($date)) {
                $date->addDay();
            }

            $this->record($enrollment, ['record_date' => $date->format('Y-m-d')]);
            $date->addDay();
        }

        $response = $this->get(route('hifz.index'));

        $response->assertOk();
        $response->assertSee('Showing 1 to 20 of 25 daily records');
        $this->assertCount(20, $response->viewData('records')->items());

        $this->assertCount(5, $this->get(route('hifz.index', ['page' => 2]))->viewData('records')->items());
    }

    public function test_the_listing_does_not_grow_a_query_per_record(): void
    {
        $this->makeRecordsForDistinctStudents(3);
        $withThree = $this->countIndexQueries();

        $this->makeRecordsForDistinctStudents(6);
        $withNine = $this->countIndexQueries();

        // Eager loading is what makes these equal: without it each record
        // would fetch its own enrollment, student, class, section and
        // teacher.
        $this->assertSame($withThree, $withNine);

        // And the fixed cost is a handful of queries, not a page of them.
        $this->assertLessThan(20, $withNine);
    }

    /**
     * Count the queries one render of the listing costs.
     *
     * A warm-up request first: the permission and role lookups are cached
     * per process, so measuring the first request of a run would count
     * them once and never again.
     */
    private function countIndexQueries(): int
    {
        $this->get(route('hifz.index'))->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('hifz.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * Give a number of distinct students one record each.
     */
    private function makeRecordsForDistinctStudents(int $count): void
    {
        $teacher = $this->teacher();

        for ($index = 0; $index < $count; $index++) {
            $this->record(
                $this->madrassaEnrollment($this->student('Student '.uniqid())),
                ['teacher_id' => $teacher->id]
            );
        }
    }

    /* ---------------------------------------------------------------- */
    /* The roster */
    /* ---------------------------------------------------------------- */

    public function test_the_roster_lists_madrassa_students_for_a_date_and_class(): void
    {
        $madrassa = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $this->schoolEnrollment($this->student('Usman Tariq', 'School'));

        $response = $this->get(route('hifz.index', [
            'record_date' => self::MONDAY,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $madrassa->academic_class_id,
        ]));

        $response->assertOk();
        $response->assertSee('Daily Roster');
        $response->assertSee('Ahtesham Shakeel');
        // A school-only student is not part of this module at all.
        $response->assertDontSee('Usman Tariq');
    }

    public function test_the_roster_refuses_a_weekend(): void
    {
        $madrassa = $this->madrassaEnrollment();

        $this->get(route('hifz.index', [
            'record_date' => self::SATURDAY,
            'academic_class_id' => $madrassa->academic_class_id,
        ]))->assertOk()->assertSee('Saturday is an off day');
    }

    public function test_the_roster_leaves_out_students_whose_enrollment_has_ended(): void
    {
        $current = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $this->madrassaEnrollment($this->student('Zaid Khan'), [
            'status' => 'Left',
            'end_date' => '2026-06-30',
        ]);

        $this->get(route('hifz.index', [
            'record_date' => self::MONDAY,
            'academic_class_id' => $current->academic_class_id,
        ]))->assertOk()->assertSee('Ahtesham Shakeel')->assertDontSee('Zaid Khan');
    }

    /* ---------------------------------------------------------------- */
    /* 24-25: security */
    /* ---------------------------------------------------------------- */

    public function test_a_guest_cannot_reach_any_part_of_the_module(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $record = $this->record($enrollment);

        auth()->logout();

        $this->get(route('hifz.index'))->assertRedirect(route('login'));
        $this->get(route('hifz.create'))->assertRedirect(route('login'));
        $this->get(route('hifz.show', $record->id))->assertRedirect(route('login'));
        $this->get(route('hifz.edit', $record->id))->assertRedirect(route('login'));
        $this->post(route('hifz.store'), $this->hifzPayload($enrollment, ['record_date' => self::TUESDAY]))
            ->assertRedirect(route('login'));
        $this->put(route('hifz.update', $record->id), $this->hifzPayload($enrollment))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('madrassa_daily_records', 1);
    }

    public function test_a_record_cannot_be_moved_to_another_students_enrollment(): void
    {
        $mine = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $theirs = $this->madrassaEnrollment($this->student('Zaid Khan'));

        $record = $this->record($mine);

        $response = $this->put(route('hifz.update', $record->id), $this->hifzPayload($theirs, [
            'remarks' => 'Moved',
        ]));

        $response->assertSessionHasErrors('student_academic_enrollment_id');

        $record->refresh();

        $this->assertSame($mine->id, $record->student_academic_enrollment_id);
        $this->assertNotSame('Moved', $record->remarks);
    }

    public function test_a_record_cannot_be_moved_to_the_students_own_school_enrollment(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $record = $this->record($madrassa);

        $this->put(route('hifz.update', $record->id), $this->hifzPayload($madrassa, [
            'student_academic_enrollment_id' => $school->id,
        ]))->assertSessionHasErrors('student_academic_enrollment_id');

        $this->assertSame($madrassa->id, $record->fresh()->student_academic_enrollment_id);
    }

    public function test_a_manipulated_student_id_on_an_edit_is_rejected(): void
    {
        $mine = $this->madrassaEnrollment($this->student('Ahtesham Shakeel'));
        $other = $this->student('Zaid Khan');

        $record = $this->record($mine);

        $this->put(route('hifz.update', $record->id), $this->hifzPayload($mine, [
            'student_id' => $other->id,
            'remarks' => 'Reassigned',
        ]))->assertSessionHasErrors('student_academic_enrollment_id');

        $this->assertNotSame('Reassigned', $record->fresh()->remarks);
    }

    /* ---------------------------------------------------------------- */
    /* The teacher */
    /* ---------------------------------------------------------------- */

    public function test_a_teacher_can_be_recorded_and_is_optional(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $teacher = $this->teacher();

        $this->post(route('hifz.store'), $this->hifzPayload($enrollment, ['teacher_id' => $teacher->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($teacher->id, MadrassaDailyRecord::firstOrFail()->teacher_id);

        // Omitted entirely on the next day, which is equally valid.
        $this->post(route('hifz.store'), $this->hifzPayload($enrollment, ['record_date' => self::TUESDAY]))
            ->assertSessionHasNoErrors();

        $this->assertNull(
            MadrassaDailyRecord::where('record_date', self::TUESDAY)->firstOrFail()->teacher_id
        );
    }

    public function test_an_inactive_teacher_cannot_be_chosen(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $teacher = $this->teacher('Inactive');

        $this->post(route('hifz.store'), $this->hifzPayload($enrollment, ['teacher_id' => $teacher->id]))
            ->assertSessionHasErrors('teacher_id');
    }

    public function test_a_record_keeps_a_teacher_who_has_since_left(): void
    {
        $enrollment = $this->madrassaEnrollment();
        $teacher = $this->teacher();
        $record = $this->record($enrollment, ['teacher_id' => $teacher->id]);

        $teacher->update(['teacher_status' => 'Inactive']);

        // Re-saving the record is not choosing a teacher, so the lesson is
        // not forced onto somebody else.
        $this->put(route('hifz.update', $record->id), $this->hifzPayload($enrollment, [
            'teacher_id' => $teacher->id,
            'remarks' => 'Unchanged teacher',
        ]))->assertSessionHasNoErrors();

        $this->assertSame($teacher->id, $record->fresh()->teacher_id);
    }

    /* ---------------------------------------------------------------- */
    /* The student profile */
    /* ---------------------------------------------------------------- */

    public function test_the_student_profile_offers_the_module_to_a_madrassa_student(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->madrassaEnrollment($student);

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertSee('Hifz &amp; Quran / Daily Academic Record', false);
    }

    public function test_the_profile_link_opens_the_form_without_a_date_preset(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // The link on the profile names an enrollment but no day: the
        // administrator picks the date on the form.
        $this->get(route('hifz.create', ['student_academic_enrollment_id' => $enrollment->id]))
            ->assertOk()
            ->assertSee('Ahtesham Shakeel');
    }

    public function test_the_student_profile_hides_the_module_from_a_school_only_student(): void
    {
        $student = $this->student('Usman Tariq', 'School');
        $this->schoolEnrollment($student);

        $this->get(route('students.show', $student->id))
            ->assertOk()
            ->assertDontSee('Hifz &amp; Quran / Daily Academic Record', false);
    }

    public function test_the_record_detail_page_shows_the_whole_day(): void
    {
        $enrollment = $this->madrassaEnrollment();

        $this->post(route('hifz.store'), $this->hifzPayload($enrollment))->assertSessionHasNoErrors();

        $this->get(route('hifz.show', MadrassaDailyRecord::firstOrFail()->id))
            ->assertOk()
            ->assertSee('1 page')
            ->assertSee('1 para')
            ->assertSee('Good performance');
    }
}
