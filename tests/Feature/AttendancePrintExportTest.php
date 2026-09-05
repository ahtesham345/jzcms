<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentAttendance;
use App\Models\User;
use Database\Seeders\AdmissionDepartmentClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers printing and exporting attendance.
 *
 * A presentation layer over what Chunks 1 to 3 already hold. Two things are
 * asserted throughout: the printed and exported figures are the ones the
 * screen shows, and nothing about the attendance rows changes because
 * somebody looked at them on paper.
 */
class AttendancePrintExportTest extends TestCase
{
    use RefreshDatabase;

    /** August 2026: the 1st is a Saturday (worked), the 2nd a Sunday (off). */
    private const MONDAY = '2026-08-03';

    private const TUESDAY = '2026-08-04';

    private const WEDNESDAY = '2026-08-05';

    private const SATURDAY = '2026-08-01';

    private const SUNDAY = '2026-08-02';

    private AcademicSession $session;

    private AcademicSession $otherSession;

    private Department $hifz;

    private Department $school;

    private AcademicClass $hifzClass;

    private AcademicClass $nazra;

    private AcademicClass $primary;

    private Section $hifzA;

    private Section $hifzB;

    private Section $primaryB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->seed(AdmissionDepartmentClassSeeder::class);

        $this->session = AcademicSession::create([
            'name' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'status' => true,
        ]);
        $this->otherSession = AcademicSession::create([
            'name' => '2027-2028', 'start_date' => '2027-04-01', 'end_date' => '2028-03-31',
            'status' => true,
        ]);

        $this->hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->school = Department::where('name', 'School')->firstOrFail();

        $this->hifzClass = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Hifz')->firstOrFail();
        $this->nazra = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Nazra')->firstOrFail();
        $this->primary = AcademicClass::where('department_id', $this->school->id)->where('name', 'Primary Section')->firstOrFail();

        $this->hifzA = $this->section('Hifz-A', $this->hifzClass);
        $this->hifzB = $this->section('Hifz-B', $this->hifzClass);
        $this->primaryB = $this->section('Primary-B', $this->primary);
    }

    /* ---------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------- */

    private function section(string $name, AcademicClass $class): Section
    {
        return Section::create([
            'name' => $name,
            'code' => strtoupper(str_replace('-', '', $name)),
            'academic_class_id' => $class->id,
            'status' => true,
        ]);
    }

    private function student(string $name = 'Ahmed Ali'): Student
    {
        static $sequence = 0;
        $sequence++;

        return Student::create([
            'registration_number' => 'STD-2026-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'roll_number' => str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'full_name' => $name,
            'father_name' => 'Muhammad Ali',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03007654321',
            'admission_date' => '2026-04-01',
            'academic_session_id' => $this->session->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'student_status' => 'Active',
            'student_type' => 'Hifz',
            'resident_type' => 'Local Resident',
        ]);
    }

    private function madrassaEnrollment(?Student $student = null, array $overrides = []): StudentAcademicEnrollment
    {
        $student ??= $this->student();

        return $student->academicEnrollments()->create(array_merge([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'start_date' => '2026-04-01',
            'status' => 'Active',
        ], $overrides));
    }

    private function schoolEnrollment(?Student $student = null, array $overrides = []): StudentAcademicEnrollment
    {
        $student ??= $this->student();

        return $student->academicEnrollments()->create(array_merge([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->primaryB->id,
            'start_date' => '2026-04-01',
            'status' => 'Active',
        ], $overrides));
    }

    private function record(
        StudentAcademicEnrollment $enrollment,
        string $date,
        string $period = 'Morning',
        string $status = 'Present',
        ?string $reason = null
    ): StudentAttendance {
        return StudentAttendance::create([
            'student_academic_enrollment_id' => $enrollment->id,
            'attendance_date' => $date,
            'attendance_period' => $period,
            'status' => $status,
            'absence_reason' => $reason,
        ]);
    }

    /**
     * The group filters naming the madrassa sheet.
     *
     * @return array<string, mixed>
     */
    private function madrassaGroup(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'Madrassa',
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'month' => 8,
            'year' => 2026,
        ], $overrides);
    }

    /**
     * The group filters naming the school sheet.
     *
     * @return array<string, mixed>
     */
    private function schoolGroup(array $overrides = []): array
    {
        return array_merge([
            'academic_session_id' => $this->session->id,
            'academic_track' => 'School',
            'department_id' => $this->school->id,
            'academic_class_id' => $this->primary->id,
            'section_id' => $this->primaryB->id,
            'month' => 8,
            'year' => 2026,
        ], $overrides);
    }

    private function printSheet(array $filters = [])
    {
        return $this->get(route('attendance.print', array_merge($this->madrassaGroup(), $filters)));
    }

    private function printReport(array $filters = [])
    {
        return $this->get(route('attendance.reports.print', array_merge([
            'month' => 8, 'year' => 2026, 'academic_track' => 'Madrassa',
        ], $filters)));
    }

    /**
     * Export the report and read the CSV back as rows.
     *
     * @return array<int, array<int, string>>
     */
    private function exportRows(array $filters = []): array
    {
        $response = $this->get(route('attendance.reports.export', array_merge([
            'month' => 8, 'year' => 2026, 'academic_track' => 'Madrassa',
        ], $filters)));

        $response->assertOk();

        $csv = $response->streamedContent();

        // The byte order mark is for Excel, not for the parser.
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);

        $rows = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * The exported student names, header row excluded.
     *
     * @return array<int, string>
     */
    private function exportedNames(array $filters = []): array
    {
        return array_column(array_slice($this->exportRows($filters), 1), 0);
    }

    /* ---------------------------------------------------------------- */
    /* Security */
    /* ---------------------------------------------------------------- */

    public function test_a_guest_cannot_reach_any_print_or_export_route(): void
    {
        $student = $this->student();
        $this->madrassaEnrollment($student);

        auth()->logout();

        $this->get(route('attendance.print', $this->madrassaGroup()))->assertRedirect(route('login'));
        $this->get(route('students.attendance.print', ['student' => $student->id]))->assertRedirect(route('login'));
        $this->get(route('attendance.reports.print'))->assertRedirect(route('login'));
        $this->get(route('attendance.reports.export'))->assertRedirect(route('login'));
    }

    public function test_an_unknown_student_cannot_be_printed(): void
    {
        $this->get(route('students.attendance.print', ['student' => 999999]))->assertNotFound();
    }

    /* ---------------------------------------------------------------- */
    /* Printable monthly sheet */
    /* ---------------------------------------------------------------- */

    public function test_the_school_monthly_sheet_prints(): void
    {
        $enrollment = $this->schoolEnrollment($this->student('Ahmed Ali'));

        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        $this->record($enrollment, self::TUESDAY, 'Morning', 'Absent', 'Sick');

        $response = $this->get(route('attendance.print', $this->schoolGroup()))->assertOk();

        $response->assertSee('Ahmed Ali')
            ->assertSee($enrollment->student->registration_number)
            ->assertSee('August 2026')
            // School has one register, and the page says which.
            ->assertSee('Morning');

        $this->assertSame('Morning', $response->viewData('filters')['attendance_period']);

        // Present prints as P, absent as A, and the twenty-six teaching days
        // of August leave twenty-four cells blank because nobody entered them.
        $marks = $this->printedMarks($response->getContent());

        $this->assertSame(26, count($marks));
        $this->assertSame(1, count(array_filter($marks, fn ($mark) => $mark === 'P')));
        $this->assertSame(1, count(array_filter($marks, fn ($mark) => $mark === 'A')));
        $this->assertSame(24, count(array_filter($marks, fn ($mark) => $mark === '')));

        $response->assertSee('P = Present');
    }

    /**
     * Read the marks out of a printed register, in column order.
     *
     * @return array<int, string>
     */
    private function printedMarks(string $html): array
    {
        preg_match_all('#font-semibold">\s*([PA]?)\s*</td>#', $html, $matches);

        return $matches[1];
    }

    public function test_each_madrassa_period_prints_its_own_register(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        $this->record($enrollment, self::MONDAY, 'Afternoon', 'Absent', 'Sick');
        $this->record($enrollment, self::MONDAY, 'Evening', 'Present');

        foreach (['Morning', 'Afternoon', 'Evening'] as $period) {
            $response = $this->printSheet(['attendance_period' => $period])->assertOk();

            $this->assertSame($period, $response->viewData('filters')['attendance_period']);

            // Only that period's marks reach the page.
            $cells = $response->viewData('cells');
            $key = StudentAttendance::cellKey($enrollment->id, self::MONDAY);

            $this->assertSame(
                $period === 'Afternoon' ? 'Absent' : 'Present',
                $cells[$key]['status'],
                "The {$period} register printed the wrong mark."
            );
        }
    }

    public function test_a_school_sheet_cannot_be_printed_for_an_evening_period(): void
    {
        $this->schoolEnrollment($this->student('Ahmed Ali'));

        $response = $this->get(route('attendance.print', $this->schoolGroup(['attendance_period' => 'Evening'])))->assertOk();

        // Pinned back to the only register school keeps.
        $this->assertSame('Morning', $response->viewData('filters')['attendance_period']);
        $response->assertDontSee('<th class="border border-gray-400 px-1 py-1 text-left">Evening</th>', false);
    }

    public function test_sunday_columns_print_as_off_and_saturdays_do_not(): void
    {
        $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $response = $this->printSheet()->assertOk();

        // August 2026 holds five Sundays; its Saturdays are worked.
        $days = collect($response->viewData('days'))->keyBy('date');
        $this->assertTrue($days[self::SUNDAY]['is_off_day']);
        $this->assertFalse($days[self::SATURDAY]['is_off_day']);

        // One OFF per Sunday in the single student row.
        $this->assertSame(5, substr_count($response->getContent(), '>OFF</td>'));
    }

    public function test_the_printed_sheet_renders_the_right_number_of_day_columns(): void
    {
        $this->madrassaEnrollment($this->student('Ahmed Ali'));

        foreach ([[2, 2027, 28], [2, 2028, 29], [4, 2026, 30], [8, 2026, 31]] as [$month, $year, $expected]) {
            $days = $this->printSheet(['month' => $month, 'year' => $year])->assertOk()->viewData('days');

            $this->assertCount($expected, $days, "{$month}/{$year} printed the wrong number of columns.");
        }
    }

    public function test_the_printed_sheet_respects_the_selected_group(): void
    {
        $inGroup = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $otherSection = $this->madrassaEnrollment($this->student('Bilal Khan'), ['section_id' => $this->hifzB->id]);
        $otherClass = $this->madrassaEnrollment($this->student('Usman Tariq'), [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        $sheet = $this->printSheet()->assertOk()->viewData('sheet');

        $this->assertSame([$inGroup->id], $sheet->pluck('id')->all());
        $this->assertNotContains($otherSection->id, $sheet->pluck('id')->all());
        $this->assertNotContains($otherClass->id, $sheet->pluck('id')->all());
    }

    public function test_printing_without_a_group_returns_to_the_entry_sheet(): void
    {
        $this->get(route('attendance.print', ['month' => 8, 'year' => 2026]))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_the_printed_sheet_carries_no_application_chrome(): void
    {
        $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $html = $this->printSheet()->assertOk()->getContent();

        // No sidebar, no navbar, no filter form on the printable page.
        $this->assertStringNotContainsString('Master Data', $html);
        $this->assertStringNotContainsString('Select Attendance Sheet', $html);
        $this->assertStringNotContainsString('Mark remaining Present', $html);
        $this->assertStringNotContainsString('<form', $html);

        // The screen-only toolbar is hidden when the page is printed.
        $this->assertStringContainsString('print:hidden', $html);
    }

    /* ---------------------------------------------------------------- */
    /* Printable student history */
    /* ---------------------------------------------------------------- */

    public function test_the_student_history_prints_with_its_filters(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        $this->record($enrollment, self::TUESDAY, 'Morning', 'Absent', 'Family issue');
        $this->record($enrollment, self::MONDAY, 'Evening', 'Present');
        // Another month, which the filters exclude.
        $this->record($enrollment, '2026-09-07', 'Morning', 'Present');

        $response = $this->get(route('students.attendance.print', [
            'student' => $enrollment->student_id,
            'academic_track' => 'Madrassa',
            'academic_session_id' => $this->session->id,
            'attendance_period' => 'Morning',
            'month' => 8,
            'year' => 2026,
        ]))->assertOk();

        $records = $response->viewData('records');

        // The morning register of August only.
        $this->assertCount(2, $records);
        $this->assertSame(['Morning', 'Morning'], $records->pluck('attendance_period')->all());

        $response->assertSee('Ahmed Ali')
            ->assertSee($enrollment->student->registration_number)
            ->assertSee('August 2026')
            ->assertSee('Family issue')
            ->assertSee('Hifz');
    }

    public function test_the_printed_history_is_not_paginated(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        // More than the twenty-five the screen pages at.
        foreach (StudentAttendance::monthDays(2026, 8) as $day) {
            if ($day['is_off_day']) {
                continue;
            }

            foreach (['Morning', 'Afternoon'] as $period) {
                $this->record($enrollment, $day['date'], $period, 'Present');
            }
        }

        $records = $this->get(route('students.attendance.print', [
            'student' => $enrollment->student_id,
            'academic_track' => 'Madrassa',
            'attendance_period' => 'all',
            'month' => 8,
            'year' => 2026,
        ]))->assertOk()->viewData('records');

        // Twenty-six teaching days across two registers.
        $this->assertCount(52, $records);
    }

    public function test_a_present_record_prints_no_absence_reason(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $record = $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        DB::table('student_attendances')->where('id', $record->id)->update(['absence_reason' => 'Stale reason']);

        $this->get(route('students.attendance.print', [
            'student' => $enrollment->student_id,
            'academic_track' => 'Madrassa',
            'month' => 8,
            'year' => 2026,
        ]))->assertOk()->assertDontSee('Stale reason');
    }

    /* ---------------------------------------------------------------- */
    /* Printable report */
    /* ---------------------------------------------------------------- */

    public function test_the_report_prints_with_its_summaries(): void
    {
        $first = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $second = $this->madrassaEnrollment($this->student('Hassan Raza'));

        $this->record($first, self::MONDAY, 'Morning', 'Present');
        $this->record($first, self::TUESDAY, 'Morning', 'Present');
        $this->record($first, self::WEDNESDAY, 'Morning', 'Absent', 'Sick');
        $this->record($second, self::MONDAY, 'Morning', 'Present');

        $response = $this->printReport()->assertOk();

        $summary = $response->viewData('summary');
        $this->assertSame(['students' => 2, 'present' => 3, 'absent' => 1, 'recorded' => 4], collect($summary)->only(['students', 'present', 'absent', 'recorded'])->all());
        $this->assertSame(75.0, $summary['percentage']);

        $response->assertSee('Total Recorded')
            ->assertSee('Student Summary')
            ->assertSee('Class Summary')
            ->assertSee('Period Summary')
            ->assertSee('Ahmed Ali')
            ->assertSee('Hassan Raza')
            ->assertSee('75.00%');

        // The class summary and the period summary come through unchanged.
        $this->assertSame(2, $response->viewData('groupSummary')[0]['students']);
        $this->assertSame('Morning', $response->viewData('periodSummary')[0]['period']);
    }

    public function test_the_printed_report_covers_every_matching_student_not_one_page(): void
    {
        collect(range(1, 30))->each(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        // Thirty students, which the screen shows across two pages.
        $this->assertCount(30, $this->printReport()->assertOk()->viewData('students'));
    }

    public function test_the_printed_report_respects_its_filters(): void
    {
        $inGroup = $this->madrassaEnrollment($this->student('Ahmed Ali'));
        $this->madrassaEnrollment($this->student('Bilal Khan'), ['section_id' => $this->hifzB->id]);
        $this->schoolEnrollment($this->student('School Student'));

        $students = $this->printReport([
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
        ])->assertOk()->viewData('students');

        $this->assertSame([$inGroup->id], $students->pluck('id')->all());
    }

    /* ---------------------------------------------------------------- */
    /* CSV export */
    /* ---------------------------------------------------------------- */

    public function test_the_export_has_the_expected_header_and_filename(): void
    {
        $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $response = $this->get(route('attendance.reports.export', [
            'month' => 8, 'year' => 2026, 'academic_track' => 'Madrassa',
        ]))->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('attendance-report-madrassa-2026-08.csv', $response->headers->get('content-disposition'));

        $this->assertSame([
            'Student Name',
            'Registration Number',
            'Roll Number',
            'Academic Session',
            'Department',
            'Class',
            'Section',
            'Track',
            'Present',
            'Absent',
            'Recorded',
            'Attendance Percentage',
        ], $this->exportRows()[0]);
    }

    public function test_the_export_contains_every_matching_student_not_only_the_first_page(): void
    {
        collect(range(1, 30))->each(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        // Thirty students plus the header row, where the screen pages at 25.
        $this->assertCount(31, $this->exportRows());
    }

    public function test_the_export_carries_the_right_figures(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        $this->record($enrollment, self::TUESDAY, 'Morning', 'Present');
        $this->record($enrollment, self::WEDNESDAY, 'Morning', 'Absent', 'Sick');

        $row = $this->exportRows()[1];

        $this->assertSame('Ahmed Ali', $row[0]);
        $this->assertSame($enrollment->student->registration_number, $row[1]);
        $this->assertSame($enrollment->student->roll_number, $row[2]);
        $this->assertSame('2026-2027', $row[3]);
        $this->assertSame('Hifz', $row[4]);
        $this->assertSame('Hifz', $row[5]);
        $this->assertSame('Hifz-A', $row[6]);
        $this->assertSame('Madrassa', $row[7]);
        $this->assertSame('2', $row[8]);
        $this->assertSame('1', $row[9]);
        $this->assertSame('3', $row[10]);
        $this->assertSame('66.67', $row[11]);
    }

    public function test_a_student_with_no_attendance_is_exported_with_zeros_and_not_available(): void
    {
        $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $row = $this->exportRows()[1];

        $this->assertSame('Ahmed Ali', $row[0]);
        $this->assertSame('0', $row[8]);
        $this->assertSame('0', $row[9]);
        $this->assertSame('0', $row[10]);
        // Nothing recorded is not nought percent attendance.
        $this->assertSame('N/A', $row[11]);
    }

    public function test_the_export_respects_the_search(): void
    {
        $this->madrassaEnrollment($this->student('Ali Raza'));
        $this->madrassaEnrollment($this->student('Ahmed Khan'));

        $this->assertSame(['Ali Raza'], $this->exportedNames(['search' => 'Ali Raza']));
    }

    public function test_the_export_respects_the_session_department_class_and_section(): void
    {
        $student = $this->student('Ahmed Ali');
        $thisYear = $this->madrassaEnrollment($student, ['status' => 'Completed']);
        $this->madrassaEnrollment($student, [
            'academic_session_id' => $this->otherSession->id,
            'start_date' => '2027-04-01',
        ]);

        $this->madrassaEnrollment($this->student('Bilal Khan'), ['section_id' => $this->hifzB->id]);
        $this->madrassaEnrollment($this->student('Usman Tariq'), [
            'academic_class_id' => $this->nazra->id,
            'section_id' => null,
        ]);

        // Session narrows to the enrollment that belongs to it.
        $this->assertSame(
            ['Ahmed Ali', 'Bilal Khan', 'Usman Tariq'],
            collect($this->exportedNames(['academic_session_id' => $this->session->id]))->sort()->values()->all()
        );

        // Department, class and section each narrow further.
        $this->assertCount(3, $this->exportedNames(['department_id' => $this->hifz->id, 'academic_session_id' => $this->session->id]));
        $this->assertCount(2, $this->exportedNames(['academic_class_id' => $this->hifzClass->id, 'academic_session_id' => $this->session->id]));
        $this->assertSame(['Ahmed Ali'], $this->exportedNames([
            'academic_class_id' => $this->hifzClass->id,
            'section_id' => $this->hifzA->id,
            'academic_session_id' => $this->session->id,
        ]));

        $this->assertNotNull($thisYear);
    }

    public function test_the_export_respects_the_track(): void
    {
        $this->madrassaEnrollment($this->student('Madrassa Student'));
        $this->schoolEnrollment($this->student('School Student'));

        $this->assertSame(['Madrassa Student'], $this->exportedNames(['academic_track' => 'Madrassa']));
        $this->assertSame(['School Student'], $this->exportedNames(['academic_track' => 'School']));
    }

    public function test_the_export_respects_the_month_and_year(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        $this->record($enrollment, '2026-09-07', 'Morning', 'Present');
        $this->record($enrollment, '2026-09-08', 'Morning', 'Present');

        $this->assertSame('1', $this->exportRows(['month' => 8, 'year' => 2026])[1][10]);
        $this->assertSame('2', $this->exportRows(['month' => 9, 'year' => 2026])[1][10]);
        $this->assertSame('0', $this->exportRows(['month' => 8, 'year' => 2027])[1][10]);
    }

    public function test_the_export_respects_the_period(): void
    {
        $enrollment = $this->madrassaEnrollment($this->student('Ahmed Ali'));

        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        $this->record($enrollment, self::MONDAY, 'Afternoon', 'Absent', 'Sick');
        $this->record($enrollment, self::MONDAY, 'Evening', 'Present');

        $this->assertSame('1', $this->exportRows(['attendance_period' => 'Morning'])[1][10]);
        $this->assertSame('1', $this->exportRows(['attendance_period' => 'Afternoon'])[1][10]);
        $this->assertSame('1', $this->exportRows(['attendance_period' => 'Evening'])[1][10]);

        // All periods counts the three registers separately.
        $allPeriods = $this->exportRows(['attendance_period' => 'all'])[1];
        $this->assertSame('2', $allPeriods[8]);
        $this->assertSame('1', $allPeriods[9]);
        $this->assertSame('3', $allPeriods[10]);
        $this->assertSame('66.67', $allPeriods[11]);
    }

    public function test_a_school_export_counts_the_morning_register_only(): void
    {
        $enrollment = $this->schoolEnrollment($this->student('Ahmed Ali'));

        $this->record($enrollment, self::MONDAY, 'Morning', 'Present');
        // Rows a school student should never have, written past the sheet
        // that would refuse them.
        $this->record($enrollment, self::MONDAY, 'Afternoon', 'Present');
        $this->record($enrollment, self::MONDAY, 'Evening', 'Absent', 'Sick');

        $row = $this->exportRows(['academic_track' => 'School', 'attendance_period' => 'Evening'])[1];

        $this->assertSame('1', $row[8]);
        $this->assertSame('0', $row[9]);
        $this->assertSame('1', $row[10]);
    }

    /* ---------------------------------------------------------------- */
    /* Dual track */
    /* ---------------------------------------------------------------- */

    public function test_a_dual_track_student_is_never_merged_in_print_or_export(): void
    {
        $student = $this->student('Ahmed Ali');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->record($school, self::MONDAY, 'Morning', 'Present');
        $this->record($school, self::TUESDAY, 'Morning', 'Present');

        $this->record($madrassa, self::MONDAY, 'Morning', 'Absent', 'Sick');
        $this->record($madrassa, self::MONDAY, 'Afternoon', 'Present');
        $this->record($madrassa, self::MONDAY, 'Evening', 'Present');

        // The export keeps each track to its own enrollment.
        $schoolRow = $this->exportRows(['academic_track' => 'School'])[1];
        $this->assertSame(['2', '0', '2'], [$schoolRow[8], $schoolRow[9], $schoolRow[10]]);
        $this->assertSame('School', $schoolRow[7]);

        $madrassaRow = $this->exportRows(['academic_track' => 'Madrassa', 'attendance_period' => 'all'])[1];
        $this->assertSame(['2', '1', '3'], [$madrassaRow[8], $madrassaRow[9], $madrassaRow[10]]);
        $this->assertSame('Madrassa', $madrassaRow[7]);

        // So does the printed report.
        $this->assertSame(
            [$school->id],
            $this->printReport(['academic_track' => 'School'])->viewData('students')->pluck('id')->all()
        );
        $this->assertSame(
            [$madrassa->id],
            $this->printReport(['academic_track' => 'Madrassa'])->viewData('students')->pluck('id')->all()
        );

        // And the printed history, which prints one track at a time.
        $schoolHistory = $this->get(route('students.attendance.print', [
            'student' => $student->id, 'academic_track' => 'School', 'month' => 8, 'year' => 2026,
        ]))->assertOk()->viewData('records');

        $this->assertSame([$school->id], $schoolHistory->pluck('student_academic_enrollment_id')->unique()->values()->all());
    }

    /* ---------------------------------------------------------------- */
    /* Safety and performance */
    /* ---------------------------------------------------------------- */

    public function test_printing_and_exporting_never_change_the_attendance_records(): void
    {
        $student = $this->student('Ahmed Ali');
        $madrassa = $this->madrassaEnrollment($student);
        $school = $this->schoolEnrollment($student);

        $this->record($madrassa, self::MONDAY, 'Morning', 'Present');
        $this->record($madrassa, self::TUESDAY, 'Afternoon', 'Absent', 'Sick');
        $this->record($school, self::MONDAY, 'Morning', 'Present');

        $before = DB::table('student_attendances')->orderBy('id')->get()->toArray();

        $this->printSheet()->assertOk();
        $this->printSheet(['attendance_period' => 'Evening'])->assertOk();
        $this->get(route('attendance.print', $this->schoolGroup()))->assertOk();
        $this->get(route('students.attendance.print', ['student' => $student->id, 'month' => 8, 'year' => 2026]))->assertOk();
        $this->printReport()->assertOk();
        $this->exportRows();
        $this->exportRows(['academic_track' => 'School']);

        $this->assertDatabaseCount('student_attendances', 3);
        $this->assertEquals($before, DB::table('student_attendances')->orderBy('id')->get()->toArray());
    }

    public function test_the_export_does_not_run_a_query_per_student(): void
    {
        collect(range(1, 3))->each(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        $small = $this->queriesToExport();

        collect(range(4, 30))->each(function ($index) {
            $enrollment = $this->madrassaEnrollment($this->student("Student {$index}"));

            foreach ([self::MONDAY, self::TUESDAY, self::WEDNESDAY] as $date) {
                foreach (['Morning', 'Afternoon', 'Evening'] as $period) {
                    $this->record($enrollment, $date, $period, 'Present');
                }
            }
        });

        $large = $this->queriesToExport();

        $this->assertCount(31, $this->exportRows(['attendance_period' => 'all']));

        // Ten times the students and hundreds of attendance rows, on the
        // same number of queries.
        $this->assertLessThanOrEqual($small, $large);
        $this->assertLessThan(25, $large);
    }

    public function test_the_printed_sheet_does_not_run_a_query_per_student(): void
    {
        collect(range(1, 3))->each(fn ($index) => $this->madrassaEnrollment($this->student("Student {$index}")));

        $small = $this->queriesToPrintSheet();

        collect(range(4, 30))->each(function ($index) {
            $enrollment = $this->madrassaEnrollment($this->student("Student {$index}"));

            foreach (StudentAttendance::monthDays(2026, 8) as $day) {
                if (! $day['is_off_day']) {
                    $this->record($enrollment, $day['date'], 'Morning', 'Present');
                }
            }
        });

        $large = $this->queriesToPrintSheet();

        $this->assertLessThanOrEqual($small, $large);
        $this->assertLessThan(25, $large);
    }

    /**
     * Count the queries one export costs.
     *
     * The rows are written while the response streams, so the stream is
     * drained inside the logged region rather than after it.
     */
    private function queriesToExport(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('attendance.reports.export', [
            'month' => 8, 'year' => 2026, 'academic_track' => 'Madrassa', 'attendance_period' => 'all',
        ]))->assertOk()->streamedContent();

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    }

    /**
     * Count the queries one printed sheet costs.
     */
    private function queriesToPrintSheet(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->printSheet()->assertOk();

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    }
}
