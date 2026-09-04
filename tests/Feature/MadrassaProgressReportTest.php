<?php

namespace Tests\Feature;

use App\Models\MadrassaDailyRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * Covers the Hifz & Quran reports and the individual progress pages.
 *
 * These pages only report what the daily records already say. The thing
 * that must keep being true is that nothing is invented: quantities are
 * text, they are shown as written, and no figure on either page is a sum,
 * a conversion or a percentage of the Quran.
 *
 * The other constants carry over from the entry side. Only the Madrassa
 * track is ever read; Hifz and Dars-e-Nizami are reported separately; and a
 * record keeps the placement it was written under after a promotion.
 */
class MadrassaProgressReportTest extends TestCase
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
     * Run the report.
     *
     * @param  array<string, mixed>  $filters
     */
    private function report(array $filters = [])
    {
        return $this->get(route('hifz.reports', $filters));
    }

    /**
     * The student names the report listed, in order.
     *
     * @return array<int, string>
     */
    private function reportedNames($response): array
    {
        return $response->viewData('students')
            ->getCollection()
            ->map(fn ($row) => $row->full_name)
            ->values()
            ->all();
    }

    /**
     * One report row, by student.
     */
    private function rowFor($response, int $studentId): ?object
    {
        return $response->viewData('students')
            ->getCollection()
            ->firstWhere('student_id', $studentId);
    }

    /* ---------------------------------------------------------------- */
    /* 1-2: access */
    /* ---------------------------------------------------------------- */

    public function test_a_guest_cannot_reach_the_reports_or_a_progress_page(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->record($this->madrassaEnrollment($student));

        auth()->logout();

        $this->get(route('hifz.reports'))->assertRedirect(route('login'));
        $this->get(route('students.hifz.progress', $student->id))->assertRedirect(route('login'));
    }

    public function test_an_authenticated_admin_can_reach_the_reports(): void
    {
        $response = $this->report();

        $response->assertOk();
        $response->assertSee('Reports');
        // The module is the Hifz module, so an unasked-for report is a Hifz
        // report rather than a blank page.
        $this->assertSame(MadrassaDailyRecord::TYPE_HIFZ, $response->viewData('recordType'));
    }

    public function test_an_unrecognised_program_falls_back_to_hifz(): void
    {
        $response = $this->report(['record_type' => 'Something Else']);

        $response->assertOk();
        $this->assertSame(MadrassaDailyRecord::TYPE_HIFZ, $response->viewData('recordType'));
    }

    /* ---------------------------------------------------------------- */
    /* 3-8: who and what each report covers */
    /* ---------------------------------------------------------------- */

    public function test_hifz_records_appear_in_the_hifz_report(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->record($this->madrassaEnrollment($student));

        $response = $this->report(['record_type' => MadrassaDailyRecord::TYPE_HIFZ]);

        $this->assertSame(['Ahtesham Shakeel'], $this->reportedNames($response));
        $this->assertSame(1, (int) $this->rowFor($response, $student->id)->recorded_days);
    }

    public function test_dars_e_nizami_records_appear_in_the_dars_e_nizami_report(): void
    {
        $student = $this->student('Bilal Ahmad', 'Dars-e-Nizami');
        $this->record($this->darsEnrollment($student));

        $response = $this->report(['record_type' => MadrassaDailyRecord::TYPE_DARS_E_NIZAMI]);

        $this->assertSame(['Bilal Ahmad'], $this->reportedNames($response));
    }

    public function test_hifz_records_never_appear_in_the_dars_e_nizami_report(): void
    {
        $this->record($this->madrassaEnrollment($this->student('Ahtesham Shakeel')));

        $response = $this->report(['record_type' => MadrassaDailyRecord::TYPE_DARS_E_NIZAMI]);

        $this->assertSame([], $this->reportedNames($response));
        $this->assertSame(0, $response->viewData('groupSummary')['recorded_days']);
    }

    public function test_dars_e_nizami_records_never_appear_in_the_hifz_report(): void
    {
        $this->record($this->darsEnrollment($this->student('Bilal Ahmad', 'Dars-e-Nizami')));

        $response = $this->report(['record_type' => MadrassaDailyRecord::TYPE_HIFZ]);

        $this->assertSame([], $this->reportedNames($response));
        $this->assertSame(0, $response->viewData('groupSummary')['recorded_days']);
    }

    public function test_the_two_programs_are_never_mixed_into_one_figure(): void
    {
        $this->record($this->madrassaEnrollment($this->student('Ahtesham Shakeel')));
        $this->record($this->madrassaEnrollment($this->student('Zaid Khan')));
        $this->record($this->darsEnrollment($this->student('Bilal Ahmad', 'Dars-e-Nizami')));

        $hifz = $this->report(['record_type' => MadrassaDailyRecord::TYPE_HIFZ])->viewData('groupSummary');
        $dars = $this->report(['record_type' => MadrassaDailyRecord::TYPE_DARS_E_NIZAMI])->viewData('groupSummary');

        $this->assertSame(2, $hifz['students_with_records']);
        $this->assertSame(1, $dars['students_with_records']);
        // Each report counts only its own programme's students.
        $this->assertSame(2, $hifz['total_students']);
        $this->assertSame(1, $dars['total_students']);
    }

    public function test_a_school_only_student_never_appears_in_a_report(): void
    {
        $this->record($this->madrassaEnrollment($this->student('Ahtesham Shakeel')));
        $this->schoolEnrollment($this->student('Usman Tariq', 'School'));

        $hifz = $this->report(['record_type' => MadrassaDailyRecord::TYPE_HIFZ]);
        $dars = $this->report(['record_type' => MadrassaDailyRecord::TYPE_DARS_E_NIZAMI]);

        $this->assertSame(['Ahtesham Shakeel'], $this->reportedNames($hifz));
        $this->assertSame([], $this->reportedNames($dars));

        // Not among the students being chased for missing records either.
        $missing = $hifz->viewData('missing');
        $this->assertSame(
            [],
            $missing['listed']->pluck('student.full_name')->intersect(['Usman Tariq'])->values()->all()
        );
        // And their count is not in the denominator.
        $this->assertSame(1, $hifz->viewData('groupSummary')['total_students']);
    }

    public function test_a_hifz_plus_school_student_is_counted_through_the_madrassa_enrollment(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->record($madrassa, ['record_date' => self::MONDAY]);

        $response = $this->report(['record_type' => MadrassaDailyRecord::TYPE_HIFZ]);

        // Once, not twice.
        $this->assertSame(['Hamza Iqbal'], $this->reportedNames($response));
        $this->assertSame(1, (int) $this->rowFor($response, $student->id)->recorded_days);

        $latest = $response->viewData('latestRecords')->get($student->id);

        $this->assertSame($madrassa->id, $latest->student_academic_enrollment_id);
        $this->assertNotSame($school->id, $latest->student_academic_enrollment_id);
        $this->assertSame('Madrassa', $latest->studentAcademicEnrollment->academic_track);
        // The school class is never what the row names.
        $this->assertSame($this->hifzClass->name, $latest->studentAcademicEnrollment->academicClass->name);
    }

    /* ---------------------------------------------------------------- */
    /* 9-14: filters */
    /* ---------------------------------------------------------------- */

    public function test_the_date_range_filter_works(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->record($enrollment, ['record_date' => self::MONDAY]);
        $this->record($enrollment, ['record_date' => self::WEDNESDAY]);
        $this->record($enrollment, ['record_date' => self::FRIDAY]);

        $all = $this->report();
        $this->assertSame(3, (int) $this->rowFor($all, $student->id)->recorded_days);

        $ranged = $this->report(['date_from' => self::TUESDAY, 'date_to' => self::THURSDAY]);
        $row = $this->rowFor($ranged, $student->id);

        $this->assertSame(1, (int) $row->recorded_days);
        $this->assertSame(self::WEDNESDAY, Carbon::parse($row->last_recorded_date)->format('Y-m-d'));

        // Nothing in the range at all.
        $this->assertSame([], $this->reportedNames($this->report([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
        ])));
    }

    public function test_the_session_filter_works(): void
    {
        $inThisSession = $this->student('Ahtesham Shakeel');
        $this->record($this->madrassaEnrollment($inThisSession));

        $inNextSession = $this->student('Zaid Khan');
        $next = $this->madrassaEnrollment($inNextSession, [
            'academic_session_id' => $this->nextSession->id,
            'start_date' => '2027-04-01',
        ]);
        // 2027-08-02 is a Monday.
        $this->record($next, ['record_date' => '2027-08-02']);

        $this->assertSame(
            ['Ahtesham Shakeel'],
            $this->reportedNames($this->report(['academic_session_id' => $this->session->id]))
        );
        $this->assertSame(
            ['Zaid Khan'],
            $this->reportedNames($this->report(['academic_session_id' => $this->nextSession->id]))
        );
    }

    public function test_the_department_class_and_section_filters_work(): void
    {
        $inHifzA = $this->student('Ahtesham Shakeel');
        $this->record($this->madrassaEnrollment($inHifzA, ['section_id' => $this->hifzA->id]));

        $inHifzB = $this->student('Zaid Khan');
        $this->record($this->madrassaEnrollment($inHifzB, ['section_id' => $this->hifzB->id]));

        $inNazra = $this->student('Owais Raza');
        $this->record($this->madrassaEnrollment($inNazra, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]));

        // Department.
        $this->assertCount(3, $this->reportedNames($this->report(['department_id' => $this->hifz->id])));
        $this->assertSame([], $this->reportedNames($this->report(['department_id' => $this->darsENizami->id])));

        // Class.
        $this->assertSame(
            ['Owais Raza'],
            $this->reportedNames($this->report(['academic_class_id' => $this->nazra->id]))
        );

        // Section.
        $this->assertSame(
            ['Ahtesham Shakeel'],
            $this->reportedNames($this->report(['section_id' => $this->hifzA->id]))
        );
        $this->assertSame(
            ['Zaid Khan'],
            $this->reportedNames($this->report(['section_id' => $this->hifzB->id]))
        );
    }

    public function test_the_filters_combine_with_and_logic(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->record($this->madrassaEnrollment($student));

        // The student matches each filter on its own but not both together,
        // so the report is empty rather than one filter quietly winning.
        $this->assertSame([], $this->reportedNames($this->report([
            'department_id' => $this->darsENizami->id,
            'academic_class_id' => $this->hifzClass->id,
        ])));

        $this->assertSame(['Ahtesham Shakeel'], $this->reportedNames($this->report([
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
        ])));

        // And a search that matches, combined with a class that does not.
        $this->assertSame([], $this->reportedNames($this->report([
            'search' => 'Ahtesham',
            'academic_class_id' => $this->nazra->id,
        ])));
    }

    public function test_the_search_finds_a_student_by_name_registration_and_roll_number(): void
    {
        $wanted = $this->student('Ahtesham Shakeel');
        $this->record($this->madrassaEnrollment($wanted));
        $this->record($this->madrassaEnrollment($this->student('Zaid Khan')));

        foreach (['Ahtesham', $wanted->registration_number, $wanted->roll_number] as $term) {
            $this->assertSame(
                ['Ahtesham Shakeel'],
                $this->reportedNames($this->report(['search' => $term])),
                "search for {$term}"
            );
        }
    }

    /* ---------------------------------------------------------------- */
    /* 15: historical placement */
    /* ---------------------------------------------------------------- */

    public function test_the_report_shows_the_placement_a_record_was_made_under(): void
    {
        $student = $this->student('Ahtesham Shakeel');

        $nazra = $this->madrassaEnrollment($student, [
            'academic_class_id' => $this->nazra->id,
            'section_id' => $this->hifzA->id,
        ]);
        $this->record($nazra, ['record_date' => self::MONDAY]);

        $student->promote([
            'academic_track' => 'Madrassa',
            'academic_session_id' => $this->nextSession->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzB->id,
            'promotion_date' => '2027-04-01',
        ]);

        $response = $this->report(['date_from' => self::MONDAY, 'date_to' => self::FRIDAY]);
        $latest = $response->viewData('latestRecords')->get($student->id);

        // The row is built from the record's own enrollment, not from where
        // the student sits now.
        $this->assertSame($nazra->id, $latest->student_academic_enrollment_id);
        $this->assertSame('Nazra', $latest->studentAcademicEnrollment->academicClass->name);
        $this->assertSame('Hifz-A', $latest->studentAcademicEnrollment->section->name);

        // Filtering by the old class still finds the old records.
        $this->assertSame(
            ['Ahtesham Shakeel'],
            $this->reportedNames($this->report(['academic_class_id' => $this->nazra->id]))
        );
    }

    public function test_records_after_a_promotion_show_the_new_placement(): void
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

        $this->record($promoted->loadMissing('student'), ['record_date' => '2027-08-02']);

        // The whole history: the latest record is the promoted one.
        $latest = $this->report()->viewData('latestRecords')->get($student->id);

        $this->assertSame('Hifz', $latest->studentAcademicEnrollment->academicClass->name);
        $this->assertSame('Hifz-B', $latest->studentAcademicEnrollment->section->name);

        // The old range still reads the old placement.
        $old = $this->report(['date_to' => self::FRIDAY])->viewData('latestRecords')->get($student->id);

        $this->assertSame('Nazra', $old->studentAcademicEnrollment->academicClass->name);
    }

    /* ---------------------------------------------------------------- */
    /* 16-19: the individual progress page */
    /* ---------------------------------------------------------------- */

    public function test_the_progress_page_shows_the_students_records(): void
    {
        $mine = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($mine);

        $this->record($enrollment, ['record_date' => self::MONDAY]);
        $this->record($enrollment, ['record_date' => self::WEDNESDAY]);

        $theirs = $this->student('Zaid Khan');
        $this->record($this->madrassaEnrollment($theirs), ['record_date' => self::MONDAY]);

        $response = $this->get(route('students.hifz.progress', $mine->id));

        $response->assertOk();
        $response->assertSee('Ahtesham Shakeel');
        $this->assertSame(MadrassaDailyRecord::TYPE_HIFZ, $response->viewData('recordType'));

        $records = $response->viewData('records');

        $this->assertCount(2, $records);
        // Newest first.
        $this->assertSame(self::WEDNESDAY, $records->first()->record_date->format('Y-m-d'));
    }

    public function test_the_progress_summary_reports_the_first_and_latest_record(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $teacher = $this->teacher('Active', 'Qari Bilal');

        $this->record($enrollment, ['record_date' => self::MONDAY]);
        $this->record($enrollment, [
            'record_date' => self::WEDNESDAY,
            'sabaq' => 'Surah Aal-e-Imran',
            'sabaq_quantity' => 'half page',
            'sabqi' => 'Para 4',
            'sabqi_quantity' => '2 pages',
            'manzil' => 'Para 1 to 3',
            'manzil_quantity' => '1 para',
            'next_sabaq' => 'Surah Aal-e-Imran, ayah 10',
            'teacher_id' => $teacher->id,
        ]);

        $response = $this->get(route('students.hifz.progress', $student->id));

        $overall = $response->viewData('overall');

        $this->assertSame(2, $overall['total']);
        $this->assertSame(self::MONDAY, $overall['first_date']->format('Y-m-d'));
        $this->assertSame(self::WEDNESDAY, $overall['last_date']->format('Y-m-d'));

        $latest = $response->viewData('latest');

        $this->assertSame(self::WEDNESDAY, $latest->record_date->format('Y-m-d'));
        $this->assertSame('half page', $latest->sabaq_quantity);
        $this->assertSame('1 para', $latest->manzil_quantity);
        $this->assertSame('Qari Bilal', $latest->teacher->full_name);
    }

    public function test_the_latest_summary_ignores_the_period_filter(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->record($enrollment, ['record_date' => self::MONDAY]);
        $this->record($enrollment, ['record_date' => self::FRIDAY, 'sabaq_quantity' => '2 pages']);

        // A period that only covers the earlier day.
        $response = $this->get(route('students.hifz.progress', [
            'student' => $student->id,
            'date_from' => self::MONDAY,
            'date_to' => self::TUESDAY,
        ]));

        // The table is narrowed...
        $this->assertCount(1, $response->viewData('records'));
        $this->assertSame(1, $response->viewData('period')['recorded_days']);

        // ...but "most recent" is a fact about the student, not the range.
        $this->assertSame(self::FRIDAY, $response->viewData('latest')->record_date->format('Y-m-d'));
        $this->assertSame(2, $response->viewData('overall')['total']);
    }

    public function test_the_period_summary_counts_days_with_each_kind_of_work(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // Three days: all three have a Sabaq, two have a Sabqi, one has a
        // Manzil, and none names tomorrow's Sabaq.
        $this->record($enrollment, [
            'record_date' => self::MONDAY,
            'sabaq' => 'Surah Al-Baqarah', 'sabaq_quantity' => '1 page',
            'sabqi' => 'Para 3', 'manzil' => 'Para 1',
        ]);
        $this->record($enrollment, [
            'record_date' => self::TUESDAY,
            'sabaq' => 'Surah Al-Baqarah', 'sabqi_quantity' => '3 pages',
        ]);
        $this->record($enrollment, [
            'record_date' => self::WEDNESDAY,
            // Only the quantity was written down, which still counts as a
            // day with a Sabaq.
            'sabaq' => null, 'sabaq_quantity' => 'half page',
        ]);

        $period = $this->get(route('students.hifz.progress', $student->id))->viewData('period');

        $this->assertSame(3, $period['recorded_days']);
        $this->assertSame(3, $period['days_by_label']['Sabaq']);
        $this->assertSame(2, $period['days_by_label']['Sabqi']);
        $this->assertSame(1, $period['days_by_label']['Manzil']);
        $this->assertSame(0, $period['days_by_label']['Tomorrow']);
    }

    public function test_the_period_summary_narrows_with_the_date_range(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $this->record($enrollment, ['record_date' => self::MONDAY, 'manzil' => 'Para 1']);
        $this->record($enrollment, ['record_date' => self::FRIDAY, 'manzil' => null]);

        $period = $this->get(route('students.hifz.progress', [
            'student' => $student->id,
            'date_from' => self::MONDAY,
            'date_to' => self::TUESDAY,
        ]))->viewData('period');

        $this->assertSame(1, $period['recorded_days']);
        $this->assertSame(1, $period['days_by_label']['Manzil']);
    }

    public function test_the_period_summary_names_the_teachers_involved(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $first = $this->teacher('Active', 'Qari Bilal');
        $second = $this->teacher('Active', 'Qari Usman');

        $this->record($enrollment, ['record_date' => self::MONDAY, 'teacher_id' => $first->id]);
        $this->record($enrollment, ['record_date' => self::TUESDAY, 'teacher_id' => $second->id]);
        $this->record($enrollment, ['record_date' => self::WEDNESDAY, 'teacher_id' => $first->id]);
        // A day nobody was recorded for does not invent a teacher.
        $this->record($enrollment, ['record_date' => self::THURSDAY]);

        $response = $this->get(route('students.hifz.progress', $student->id));

        $this->assertSame(2, $response->viewData('period')['teachers']);
        $this->assertSame(
            ['Qari Bilal', 'Qari Usman'],
            $response->viewData('periodTeachers')->pluck('full_name')->all()
        );
    }

    public function test_the_progress_history_is_paginated(): void
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

        $first = $this->get(route('students.hifz.progress', $student->id));

        $first->assertOk();
        $first->assertSee('Showing 1 to 20 of 25 records');
        $this->assertCount(20, $first->viewData('records')->items());

        $this->assertCount(
            5,
            $this->get(route('students.hifz.progress', ['student' => $student->id, 'page' => 2]))
                ->viewData('records')->items()
        );
    }

    public function test_a_dars_e_nizami_progress_page_shows_no_hifz_columns(): void
    {
        $student = $this->student('Bilal Ahmad', 'Dars-e-Nizami');
        $this->record($this->darsEnrollment($student), [
            'subject_book' => 'Nahw Mir',
            'todays_lesson' => 'Lesson 12',
        ]);

        $response = $this->get(route('students.hifz.progress', $student->id));

        $response->assertOk();
        $this->assertSame(MadrassaDailyRecord::TYPE_DARS_E_NIZAMI, $response->viewData('recordType'));

        // The columns come from the record type, so the Hifz ones are not
        // rendered at all.
        $this->assertSame(
            MadrassaDailyRecord::workFieldsFor(MadrassaDailyRecord::TYPE_DARS_E_NIZAMI),
            $response->viewData('workFields')
        );
        $this->assertNotContains('sabaq', $response->viewData('workFields'));

        $period = $response->viewData('period');

        $this->assertArrayHasKey('Subject', $period['days_by_label']);
        $this->assertArrayNotHasKey('Sabaq', $period['days_by_label']);
    }

    /* ---------------------------------------------------------------- */
    /* 20: students without records */
    /* ---------------------------------------------------------------- */

    public function test_students_without_records_are_counted_and_named(): void
    {
        $recorded = $this->student('Ahtesham Shakeel');
        $this->record($this->madrassaEnrollment($recorded), ['record_date' => self::MONDAY]);

        $missingOne = $this->student('Zaid Khan');
        $this->madrassaEnrollment($missingOne);

        $missingTwo = $this->student('Owais Raza');
        $this->madrassaEnrollment($missingTwo);

        $response = $this->report(['date_from' => self::MONDAY, 'date_to' => self::FRIDAY]);

        $summary = $response->viewData('groupSummary');

        $this->assertSame(3, $summary['total_students']);
        $this->assertSame(1, $summary['students_with_records']);
        $this->assertSame(2, $summary['students_without_records']);

        $missing = $response->viewData('missing');

        $this->assertSame(2, $missing['count']);
        $this->assertFalse($missing['truncated']);
        $this->assertEqualsCanonicalizing(
            ['Zaid Khan', 'Owais Raza'],
            $missing['listed']->pluck('student.full_name')->all()
        );
        $response->assertSee('Students Without Records');
    }

    public function test_a_student_recorded_outside_the_period_counts_as_missing(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->record($this->madrassaEnrollment($student), ['record_date' => self::MONDAY]);

        $response = $this->report(['date_from' => self::WEDNESDAY, 'date_to' => self::FRIDAY]);

        $this->assertSame(0, $response->viewData('groupSummary')['students_with_records']);
        $this->assertSame(1, $response->viewData('groupSummary')['students_without_records']);
        $this->assertSame(
            ['Ahtesham Shakeel'],
            $response->viewData('missing')['listed']->pluck('student.full_name')->all()
        );
    }

    /* ---------------------------------------------------------------- */
    /* 21: nothing is calculated */
    /* ---------------------------------------------------------------- */

    public function test_quran_quantities_are_reported_exactly_as_recorded(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        // Two days whose quantities would "add up" to 1.5 pages if anything
        // here were doing arithmetic.
        $this->record($enrollment, ['record_date' => self::MONDAY, 'sabaq_quantity' => '1 page']);
        $this->record($enrollment, ['record_date' => self::TUESDAY, 'sabaq_quantity' => '1/2 page']);

        $report = $this->report();
        $latest = $report->viewData('latestRecords')->get($student->id);

        // The report repeats the latest value verbatim. The negative
        // assertions name the units too - a bare "1.5" also occurs inside
        // the SVG path data of the page's icons.
        $this->assertSame('1/2 page', $latest->sabaq_quantity);
        $report->assertSee('1/2 page');
        $report->assertDontSee('1.5 page');
        $report->assertDontSee('1.5 pages');

        // And so does the progress page, which counts days instead.
        $progress = $this->get(route('students.hifz.progress', $student->id));

        $progress->assertSee('1/2 page');
        $progress->assertSee('1 page');
        $progress->assertDontSee('1.5 page');
        $progress->assertDontSee('1.5 pages');
        $this->assertSame(2, $progress->viewData('period')['days_by_label']['Sabaq']);

        // Nothing on either page has written anything back.
        $this->assertSame('1 page', MadrassaDailyRecord::where('record_date', self::MONDAY)->firstOrFail()->sabaq_quantity);
        $this->assertSame('1/2 page', MadrassaDailyRecord::where('record_date', self::TUESDAY)->firstOrFail()->sabaq_quantity);
        $this->assertDatabaseCount('madrassa_daily_records', 2);
    }

    public function test_free_text_quantities_survive_the_report_unchanged(): void
    {
        $student = $this->student('Ahtesham Shakeel');

        $this->record($this->madrassaEnrollment($student), [
            'record_date' => self::MONDAY,
            'sabaq_quantity' => 'half page',
            'sabqi_quantity' => '1/2 para',
            'manzil_quantity' => '2 paras',
        ]);

        $latest = $this->report()->viewData('latestRecords')->get($student->id);

        $this->assertSame('half page', $latest->sabaq_quantity);
        $this->assertSame('1/2 para', $latest->sabqi_quantity);
        $this->assertSame('2 paras', $latest->manzil_quantity);
    }

    /* ---------------------------------------------------------------- */
    /* 22: the teacher */
    /* ---------------------------------------------------------------- */

    public function test_the_report_names_the_teacher_from_the_latest_record(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $enrollment = $this->madrassaEnrollment($student);

        $earlier = $this->teacher('Active', 'Qari Bilal');
        $later = $this->teacher('Active', 'Qari Usman');

        $this->record($enrollment, ['record_date' => self::MONDAY, 'teacher_id' => $earlier->id]);
        $this->record($enrollment, ['record_date' => self::WEDNESDAY, 'teacher_id' => $later->id]);

        $response = $this->report();

        $this->assertSame('Qari Usman', $response->viewData('latestRecords')->get($student->id)->teacher->full_name);
        $response->assertSee('Qari Usman');
    }

    public function test_a_record_with_no_teacher_is_reported_as_not_recorded(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->record($this->madrassaEnrollment($student), ['record_date' => self::MONDAY]);

        $response = $this->report();

        $this->assertNull($response->viewData('latestRecords')->get($student->id)->teacher);
        $response->assertSee('Not recorded');
    }

    /* ---------------------------------------------------------------- */
    /* 23: mismatched access */
    /* ---------------------------------------------------------------- */

    public function test_a_school_only_student_has_no_progress_page(): void
    {
        $student = $this->student('Usman Tariq', 'School');
        $this->schoolEnrollment($student);

        $this->get(route('students.hifz.progress', $student->id))->assertNotFound();
    }

    public function test_a_student_with_no_enrollment_at_all_has_no_progress_page(): void
    {
        $student = $this->student('Nobody Yet', 'School');

        $this->get(route('students.hifz.progress', $student->id))->assertNotFound();
    }

    public function test_an_unknown_student_has_no_progress_page(): void
    {
        $this->get(route('students.hifz.progress', 999999))->assertNotFound();
    }

    public function test_the_progress_page_cannot_be_switched_to_the_other_program(): void
    {
        $student = $this->student('Ahtesham Shakeel', 'Hifz');
        $this->record($this->madrassaEnrollment($student));

        // The query string asks for the other programme; the page is built
        // from the student's own records regardless.
        $response = $this->get(route('students.hifz.progress', [
            'student' => $student->id,
            'record_type' => MadrassaDailyRecord::TYPE_DARS_E_NIZAMI,
        ]));

        $response->assertOk();
        $this->assertSame(MadrassaDailyRecord::TYPE_HIFZ, $response->viewData('recordType'));
        $this->assertCount(1, $response->viewData('records'));
    }

    public function test_the_progress_page_never_reaches_the_school_enrollment(): void
    {
        $student = $this->student('Hamza Iqbal', 'Hifz + School');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->record($madrassa, ['record_date' => self::MONDAY]);

        $response = $this->get(route('students.hifz.progress', $student->id));

        $this->assertSame(
            [$madrassa->id],
            $response->viewData('records')->pluck('student_academic_enrollment_id')->unique()->values()->all()
        );
        $this->assertNotContains($school->id, $response->viewData('records')->pluck('student_academic_enrollment_id')->all());
        // The header names the madrassa placement, not the school one.
        $this->assertSame($madrassa->id, $response->viewData('currentEnrollment')->id);
    }

    /* ---------------------------------------------------------------- */
    /* The profile links */
    /* ---------------------------------------------------------------- */

    public function test_the_profile_offers_both_the_records_and_the_progress_link(): void
    {
        $student = $this->student('Ahtesham Shakeel');
        $this->record($this->madrassaEnrollment($student));

        $profile = $this->get(route('students.show', $student->id));

        $profile->assertOk();
        $profile->assertSee(route('students.hifz', $student->id), false);
        $profile->assertSee(route('students.hifz.progress', $student->id), false);
        $profile->assertSee('View Hifz Progress');
    }

    public function test_a_school_only_profile_offers_neither_link(): void
    {
        $student = $this->student('Usman Tariq', 'School');
        $this->schoolEnrollment($student);

        $profile = $this->get(route('students.show', $student->id));

        $profile->assertOk();
        $profile->assertDontSee(route('students.hifz.progress', $student->id), false);
        $profile->assertDontSee('View Hifz Progress');
    }

    public function test_the_roster_links_to_the_reports(): void
    {
        $this->get(route('hifz.index'))
            ->assertOk()
            ->assertSee(route('hifz.reports'), false)
            ->assertSee('Reports &amp; Progress', false);
    }

    /* ---------------------------------------------------------------- */
    /* 24: performance */
    /* ---------------------------------------------------------------- */

    public function test_the_report_query_count_does_not_grow_with_the_students(): void
    {
        $counts = [];

        foreach ([5, 20, 50] as $target) {
            $this->fillClassTo($target);
            $counts[$target] = $this->countQueries(fn () => $this->report());
        }

        // The whole point: aggregating in SQL is what keeps these equal.
        $this->assertSame($counts[5], $counts[20]);
        $this->assertSame($counts[5], $counts[50]);
        $this->assertLessThan(30, $counts[50]);
    }

    public function test_the_progress_query_count_does_not_grow_with_the_records(): void
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
        $withThree = $this->countQueries(fn () => $this->get(route('students.hifz.progress', $student->id)));

        $add(15);
        $withEighteen = $this->countQueries(fn () => $this->get(route('students.hifz.progress', $student->id)));

        $this->assertSame($withThree, $withEighteen);
    }

    /**
     * Add madrassa students until the report covers a given number.
     *
     * Most of them get records, so the count covers the rows that render a
     * latest record and a teacher as well as the empty ones.
     */
    private function fillClassTo(int $target): void
    {
        $existing = $this->report()->viewData('groupSummary')['total_students'];

        for ($index = $existing; $index < $target; $index++) {
            $enrollment = $this->madrassaEnrollment($this->student('Student '.$index.' '.uniqid()));

            if ($index % 4 !== 0) {
                $this->record($enrollment, ['record_date' => self::MONDAY]);
                $this->record($enrollment, ['record_date' => self::TUESDAY]);
            }
        }
    }

    /**
     * Count the queries one render costs.
     *
     * A warm-up request first: the permission and role lookups are cached
     * per process, so measuring the first request of a run would count them
     * once and never again.
     */
    private function countQueries(callable $request): int
    {
        $request()->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $request()->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_the_report_does_not_read_attendance(): void
    {
        $this->record($this->madrassaEnrollment($this->student('Ahtesham Shakeel')));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->report()->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        DB::disableQueryLog();

        $this->assertStringNotContainsString('student_attendances', $queries);
        $this->assertStringContainsString('madrassa_daily_records', $queries);
    }
}
