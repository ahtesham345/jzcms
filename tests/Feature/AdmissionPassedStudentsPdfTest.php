<?php

namespace Tests\Feature;

use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use App\Support\ResultReportLanguage;
use Database\Seeders\AdmissionDepartmentClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Covers the admission test passed students notice.
 *
 * The document is a notice board sheet, so what matters is who is on it.
 * These tests are mostly about exclusion: a failed candidate, a pending one,
 * a scheduled one and one with no result at all must not appear, and no
 * filter, query string or status may put them there.
 *
 * The counterpart matters just as much: an applicant who passed but has not
 * been approved into a student yet is exactly who this notice exists for, so
 * their absence would be the bug.
 *
 * The PDF bytes are not parsed. What is asserted is that a real inline PDF
 * comes back from the right route, and - by running the controller's own
 * scopes - which names are actually on the page.
 */
class AdmissionPassedStudentsPdfTest extends TestCase
{
    use RefreshDatabase;

    private AcademicSession $session;

    private AcademicSession $otherSession;

    private AcademicSession $previousSession;

    private Department $hifz;

    private Department $school;

    private AcademicClass $nazra;

    private AcademicClass $hifzClass;

    private AcademicClass $primary;

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

        $this->previousSession = AcademicSession::create([
            'name' => '2025-2026', 'start_date' => '2025-04-01', 'end_date' => '2026-03-31',
            'status' => true,
        ]);

        $this->hifz = Department::where('name', 'Hifz')->firstOrFail();
        $this->school = Department::where('name', 'School')->firstOrFail();

        $this->nazra = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Nazra')->firstOrFail();
        $this->hifzClass = AcademicClass::where('department_id', $this->hifz->id)->where('name', 'Hifz')->firstOrFail();
        $this->primary = AcademicClass::where('department_id', $this->school->id)->where('name', 'Primary Section')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function application(array $overrides = []): AdmissionApplication
    {
        return AdmissionApplication::createWithApplicationNumber(array_merge([
            'student_name' => 'Ahtesham Shakeel',
            'father_name' => 'Shakeel Ahmed',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'student_type' => 'Hifz',
            'madrassa_class_id' => $this->nazra->id,
            'status' => 'Pending',
        ], $overrides));
    }

    /**
     * A candidate who sat the test and passed it, but has not been admitted.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function passed(string $name, array $overrides = []): AdmissionApplication
    {
        return $this->application(array_merge([
            'student_name' => $name,
            'status' => 'Passed',
            'test_date' => '2026-08-10',
            'test_time' => '09:00',
            'test_marks' => 80,
            'test_result' => 'Passed',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function notice(array $query = [])
    {
        return $this->get(route('admissions.passed-students.pdf', $query));
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

    /**
     * Get the applicants the notice lists, in the order it lists them.
     *
     * The controller's own query, through the same two scopes, so this
     * reports what the document contains without parsing PDF bytes.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    private function listed(array $filters = []): array
    {
        return AdmissionApplication::query()
            ->passedTest()
            // The notice always scopes to one session, so this does too. An
            // explicit session in the filters wins, exactly as it does in the
            // controller.
            ->filter($filters + ['academic_session_id' => $this->session->id])
            ->orderBy('application_number')
            ->pluck('student_name')
            ->all();
    }

    /* ---------------------------------------------------------------- */
    /* The document */
    /* ---------------------------------------------------------------- */

    public function test_the_notice_is_an_inline_pdf(): void
    {
        $this->passed('Bilal Hussain');

        $this->assertInlinePdf($this->notice());
    }

    public function test_the_notice_renders_in_urdu_too(): void
    {
        $this->passed('Bilal Hussain');

        $this->assertInlinePdf($this->notice(['language' => ResultReportLanguage::URDU]));
    }

    public function test_the_admissions_page_offers_the_notice_in_both_languages(): void
    {
        $content = $this->get(route('admissions.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Print Passed Students', $content);

        foreach (ResultReportLanguage::LANGUAGES as $code) {
            $this->assertStringContainsString(
                route('admissions.passed-students.pdf', ['language' => $code]),
                $content
            );
        }
    }

    public function test_guests_cannot_reach_the_notice(): void
    {
        $this->passed('Bilal Hussain');

        // The same protection the rest of the admission module has.
        auth()->logout();

        $this->notice()->assertRedirect(route('login'));
    }

    /* ---------------------------------------------------------------- */
    /* Who is on it */
    /* ---------------------------------------------------------------- */

    public function test_only_applicants_with_a_passed_test_result_are_listed(): void
    {
        $this->passed('Bilal Hussain');

        // Everything that must stay off the notice.
        $this->application(['student_name' => 'Pending Candidate', 'status' => 'Pending']);
        $this->application(['student_name' => 'Scheduled Candidate', 'status' => 'Test Scheduled', 'test_date' => '2026-08-10']);
        $this->application(['student_name' => 'Sat But Unmarked', 'status' => 'Test Completed']);
        $this->application([
            'student_name' => 'Failed Candidate',
            'status' => 'Failed',
            'test_result' => 'Failed',
            'test_marks' => 20,
        ]);

        $this->assertSame(['Bilal Hussain'], $this->listed());
        $this->assertInlinePdf($this->notice());
    }

    public function test_a_passed_applicant_without_a_student_record_is_listed(): void
    {
        // The whole point of the notice: passing the test is not admission,
        // so nobody here has been approved into a student yet.
        $application = $this->passed('Bilal Hussain');

        $this->assertNull($application->student_id);
        $this->assertSame(['Bilal Hussain'], $this->listed());
    }

    public function test_an_already_approved_passed_applicant_stays_on_the_notice(): void
    {
        // Approval moves the status on to Approved but leaves the recorded
        // test result alone, and it is the result the notice reads.
        $this->passed('Bilal Hussain', ['status' => 'Approved']);

        $this->assertSame(['Bilal Hussain'], $this->listed());
    }

    public function test_the_status_filter_cannot_widen_the_notice_past_passed_candidates(): void
    {
        $this->passed('Bilal Hussain');
        $this->application(['student_name' => 'Pending Candidate', 'status' => 'Pending']);

        // The status filter is dropped at the notice's boundary, so asking
        // for Pending neither adds the pending applicant nor removes the
        // passed one: the sheet is decided by the test result alone.
        $data = $this->noticeData(['status' => 'Pending']);

        $this->assertSame(1, $data['total']);
        $this->assertSame(['Bilal Hussain'], $data['applications']->pluck('student_name')->all());

        $this->assertInlinePdf($this->notice(['status' => 'Pending']));
    }

    public function test_a_failed_test_result_cannot_be_requested_onto_the_notice(): void
    {
        $this->passed('Bilal Hussain');
        $this->application([
            'student_name' => 'Failed Candidate',
            'status' => 'Failed',
            'test_result' => 'Failed',
        ]);

        $this->assertSame([], $this->listed(['test_result' => 'Failed']));
    }

    /* ---------------------------------------------------------------- */
    /* Filters */
    /* ---------------------------------------------------------------- */

    public function test_the_department_filter_narrows_the_notice(): void
    {
        $this->passed('Hifz Candidate');
        $this->passed('School Candidate', [
            'student_type' => 'School',
            'madrassa_class_id' => null,
            'school_class_id' => $this->primary->id,
        ]);

        $this->assertSame(['Hifz Candidate'], $this->listed(['department_id' => $this->hifz->id]));
        $this->assertSame(['School Candidate'], $this->listed(['department_id' => $this->school->id]));
    }

    public function test_the_class_filter_narrows_the_notice(): void
    {
        $this->passed('Nazra Candidate');
        $this->passed('Hifz Candidate', ['madrassa_class_id' => $this->hifzClass->id]);

        $this->assertSame(['Nazra Candidate'], $this->listed(['academic_class_id' => $this->nazra->id]));
        $this->assertInlinePdf($this->notice(['academic_class_id' => $this->nazra->id]));
    }

    public function test_a_dual_track_applicant_matches_either_side(): void
    {
        // Hifz + School: one application, two placements, and either
        // department or either class must find it.
        $this->passed('Dual Track Candidate', [
            'student_type' => 'Hifz + School',
            'madrassa_class_id' => $this->nazra->id,
            'school_class_id' => $this->primary->id,
        ]);

        $this->assertSame(['Dual Track Candidate'], $this->listed(['department_id' => $this->hifz->id]));
        $this->assertSame(['Dual Track Candidate'], $this->listed(['department_id' => $this->school->id]));
        $this->assertSame(['Dual Track Candidate'], $this->listed(['academic_class_id' => $this->primary->id]));
    }

    public function test_the_student_type_gender_and_search_filters_narrow_the_notice(): void
    {
        $this->passed('Bilal Hussain');
        $this->passed('Ayesha Bibi', ['gender' => 'Female']);
        $this->passed('School Candidate', [
            'student_type' => 'School',
            'madrassa_class_id' => null,
            'school_class_id' => $this->primary->id,
        ]);

        $this->assertSame(['Ayesha Bibi'], $this->listed(['gender' => 'Female']));
        $this->assertSame(['School Candidate'], $this->listed(['student_type' => 'School']));
        $this->assertSame(['Bilal Hussain'], $this->listed(['search' => 'Bilal']));
    }

    public function test_an_unknown_department_class_or_session_is_refused(): void
    {
        $this->passed('Bilal Hussain');

        $this->notice(['department_id' => 9999])->assertSessionHasErrors('department_id');
        $this->notice(['academic_class_id' => 9999])->assertSessionHasErrors('academic_class_id');
        $this->notice(['academic_session_id' => 9999])->assertSessionHasErrors('academic_session_id');
    }

    /* ---------------------------------------------------------------- */
    /* The heading */
    /* ---------------------------------------------------------------- */

    public function test_the_notice_names_the_requested_session_and_falls_back_to_the_current_one(): void
    {
        $this->passed('Bilal Hussain');

        // An application carries no session of its own, so the session is a
        // caption on the sheet: the requested one, else the current one.
        $this->assertInlinePdf($this->notice(['academic_session_id' => $this->otherSession->id]));
        $this->assertInlinePdf($this->notice());
    }

    /* ---------------------------------------------------------------- */
    /* Nobody has passed */
    /* ---------------------------------------------------------------- */

    public function test_the_notice_still_generates_when_nobody_has_passed(): void
    {
        $this->application(['student_name' => 'Pending Candidate']);

        $this->assertSame([], $this->listed());
        $this->assertInlinePdf($this->notice());
    }

    public function test_the_no_passed_students_message_is_translated(): void
    {
        // The message an empty notice prints, in both languages, so an empty
        // Urdu sheet is not a blank one.
        $this->assertSame(
            'No passed students found.',
            ResultReportLanguage::translator(ResultReportLanguage::ENGLISH)('no_passed_students')
        );

        $this->assertNotSame(
            'No passed students found.',
            ResultReportLanguage::translator(ResultReportLanguage::URDU)('no_passed_students')
        );
    }

    /* ---------------------------------------------------------------- */
    /* The listing it is printed from */
    /* ---------------------------------------------------------------- */

    public function test_the_admissions_listing_still_filters_as_it_did(): void
    {
        $this->passed('Bilal Hussain');
        $this->application(['student_name' => 'Pending Candidate', 'status' => 'Pending']);

        $content = $this->get(route('admissions.index', ['status' => 'Passed']))->assertOk()->getContent();

        $this->assertStringContainsString('Bilal Hussain', $content);
        $this->assertStringNotContainsString('Pending Candidate', $content);
    }

    public function test_the_admissions_listing_filters_by_department_and_class(): void
    {
        $this->passed('Hifz Candidate');
        $this->passed('School Candidate', [
            'student_type' => 'School',
            'madrassa_class_id' => null,
            'school_class_id' => $this->primary->id,
        ]);

        $content = $this->get(route('admissions.index', ['department_id' => $this->school->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('School Candidate', $content);
        $this->assertStringNotContainsString('Hifz Candidate', $content);
    }

    /**
     * Bulk insert passed applicants, for the tests that need a lot of them.
     *
     * Inserted directly rather than through createWithApplicationNumber: the
     * numbering is not what is under test here, and allocating five hundred
     * of them one transaction at a time is slow enough to matter.
     */
    private function manyPassed(int $count): void
    {
        $rows = [];

        foreach (range(1, $count) as $i) {
            $rows[] = [
                'application_number' => 'APP-2026-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'student_name' => 'Candidate '.$i,
                'father_name' => 'Father '.$i,
                'gender' => 'Male',
                'father_mobile' => '03001234567',
                'student_type' => 'Hifz',
                'academic_session_id' => $this->session->id,
                'madrassa_class_id' => $this->nazra->id,
                'status' => 'Passed',
                'test_result' => 'Passed',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        AdmissionApplication::insert($rows);
    }

    /**
     * Get the data the controller actually handed the notice.
     *
     * Both renderers build the document through View::make(), so a composer
     * on that view sees exactly what the controller passed - which is how a
     * response whose body is PDF bytes can still be asserted on.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function noticeData(array $query = []): array
    {
        $captured = [];

        View::composer('admissions.pdf.passed-students', function ($view) use (&$captured) {
            $captured = $view->getData();
        });

        $this->notice($query)->assertOk();

        return $captured;
    }

    /* ---------------------------------------------------------------- */
    /* The total */
    /* ---------------------------------------------------------------- */

    public function test_the_total_is_the_true_filtered_total_when_the_display_cap_is_reached(): void
    {
        $this->manyPassed(512);

        $data = $this->noticeData();

        // Five hundred rows on the paper...
        $this->assertCount(500, $data['applications']);
        $this->assertTrue($data['capped']);

        // ...and the true number of matching applicants beside them, which
        // is the bug this covers: the notice used to print 500 here.
        $this->assertSame(512, $data['total']);

        $html = View::make('admissions.pdf.passed-students', $data)->render();

        $this->assertStringContainsString('Total Passed: 512', $html);
        $this->assertStringContainsString('Showing 500 of 512 passed applicants', $html);
        $this->assertStringNotContainsString('Total Passed: 500', $html);
    }

    public function test_the_capped_total_still_respects_the_filters(): void
    {
        $this->manyPassed(512);

        // A second cohort the filter must exclude from the count as well as
        // from the rows: a total computed before the filters would report
        // 612 here.
        $this->passed('School Candidate', [
            'student_type' => 'School',
            'madrassa_class_id' => null,
            'school_class_id' => $this->primary->id,
        ]);

        $data = $this->noticeData(['department_id' => $this->hifz->id]);

        $this->assertSame(512, $data['total']);
        $this->assertCount(500, $data['applications']);
    }

    public function test_an_uncapped_notice_prints_its_total_and_no_capped_note(): void
    {
        $this->passed('Bilal Hussain');
        $this->passed('Ayesha Bibi', ['gender' => 'Female']);
        $this->application(['student_name' => 'Pending Candidate']);

        $data = $this->noticeData();

        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['applications']);
        $this->assertFalse($data['capped']);

        $html = View::make('admissions.pdf.passed-students', $data)->render();

        $this->assertStringContainsString('Total Passed: 2', $html);
        $this->assertStringNotContainsString('Showing', $html);
    }

    public function test_an_empty_notice_reports_a_total_of_zero(): void
    {
        $this->application(['student_name' => 'Pending Candidate']);

        $data = $this->noticeData();

        $this->assertSame(0, $data['total']);
        $this->assertFalse($data['capped']);

        $html = View::make('admissions.pdf.passed-students', $data)->render();

        $this->assertStringContainsString('No passed students found.', $html);
    }

    /* ---------------------------------------------------------------- */
    /* The academic session */
    /* ---------------------------------------------------------------- */

    /**
     * Move an application into another session.
     *
     * Applications are always stamped with the current session on creation,
     * which is the behaviour under test elsewhere. A fixture that needs to
     * sit in a different year is moved afterwards rather than created into
     * one, because there is deliberately no way to create it into one.
     */
    private function inSession(AdmissionApplication $application, ?AcademicSession $session): AdmissionApplication
    {
        $application->academic_session_id = $session?->id;
        $application->save();

        return $application;
    }

    public function test_a_passed_applicant_in_the_current_session_appears(): void
    {
        $application = $this->passed('Bilal Hussain');

        // Stamped by the creation path itself, not by the test.
        $this->assertSame($this->session->id, $application->academic_session_id);
        $this->assertSame(['Bilal Hussain'], $this->listed());

        $data = $this->noticeData();

        $this->assertSame(1, $data['total']);
        $this->assertSame($this->session->id, $data['session']->id);
    }

    public function test_a_passed_applicant_from_another_session_does_not_appear(): void
    {
        $this->passed('This Year');

        // A future intake, and a past one.
        $this->inSession($this->passed('Next Year'), $this->otherSession);
        $this->inSession($this->passed('Last Year'), $this->previousSession);

        $data = $this->noticeData();

        $this->assertSame(1, $data['total']);
        $this->assertSame(
            ['This Year'],
            $data['applications']->pluck('student_name')->all()
        );
    }

    public function test_a_passed_applicant_with_no_session_does_not_appear(): void
    {
        $this->passed('This Year');

        // A legacy application: passed, but nobody knows which year for.
        $legacy = $this->inSession($this->passed('Legacy Candidate'), null);

        $this->assertNull($legacy->academic_session_id);

        $data = $this->noticeData();

        $this->assertSame(1, $data['total']);
        $this->assertSame(['This Year'], $data['applications']->pluck('student_name')->all());
    }

    public function test_an_explicitly_selected_session_prints_that_session(): void
    {
        $this->passed('This Year');
        $this->inSession($this->passed('Last Year'), $this->previousSession);

        $data = $this->noticeData(['academic_session_id' => $this->previousSession->id]);

        $this->assertSame($this->previousSession->id, $data['session']->id);
        $this->assertSame(1, $data['total']);
        $this->assertSame(['Last Year'], $data['applications']->pluck('student_name')->all());
    }

    public function test_the_notice_names_the_session_it_was_scoped_to(): void
    {
        $this->passed('Bilal Hussain');

        $html = View::make('admissions.pdf.passed-students', $this->noticeData())->render();

        // The heading has to agree with the filter, not merely caption it.
        $this->assertStringContainsString('Academic Session: '.$this->session->name, $html);
    }

    public function test_the_session_narrows_alongside_the_department_and_class_filters(): void
    {
        $this->passed('Hifz This Year');
        $this->passed('School This Year', [
            'student_type' => 'School',
            'madrassa_class_id' => null,
            'school_class_id' => $this->primary->id,
        ]);
        $this->inSession($this->passed('Hifz Last Year'), $this->previousSession);

        $data = $this->noticeData(['department_id' => $this->hifz->id]);

        $this->assertSame(1, $data['total']);
        $this->assertSame(['Hifz This Year'], $data['applications']->pluck('student_name')->all());
    }

    public function test_the_true_total_is_counted_after_session_filtering(): void
    {
        $this->manyPassed(512);

        // Another year's intake, which must count towards neither the rows
        // nor the total.
        $this->inSession($this->passed('Last Year'), $this->previousSession);

        $data = $this->noticeData();

        $this->assertSame(512, $data['total']);
        $this->assertCount(500, $data['applications']);
        $this->assertTrue($data['capped']);
    }

    public function test_a_notice_with_no_current_session_lists_nobody(): void
    {
        $this->passed('Bilal Hussain');

        // The institution has no session open at all. The notice must not
        // quietly widen to every year.
        AcademicSession::query()->update(['is_current' => false]);

        $data = $this->noticeData();

        $this->assertNull($data['session']);
        $this->assertSame(0, $data['total']);
        $this->assertCount(0, $data['applications']);

        $html = View::make('admissions.pdf.passed-students', $data)->render();

        $this->assertStringContainsString('No academic session is set', $html);
        $this->assertStringNotContainsString('Bilal Hussain', $html);
    }

    public function test_an_inactive_current_session_is_not_treated_as_current(): void
    {
        $this->passed('Bilal Hussain');

        // Current, but switched off. Current *and* active is the rule.
        $this->session->update(['status' => false]);

        $this->assertNull(AcademicSession::current());
        $this->assertNull($this->noticeData()['session']);
    }

    public function test_both_languages_still_render_a_session_scoped_notice(): void
    {
        $this->passed('Bilal Hussain');
        $this->inSession($this->passed('Last Year'), $this->previousSession);

        $this->assertInlinePdf($this->notice());
        $this->assertInlinePdf($this->notice(['language' => ResultReportLanguage::URDU]));

        $this->assertSame(1, $this->noticeData()['total']);
    }

    /* ---------------------------------------------------------------- */
    /* Admission status: approved and not approved both belong here */
    /* ---------------------------------------------------------------- */

    /**
     * Approve an application through the real approval route.
     *
     * The genuine workflow, not a stand-in: it creates the student, sets the
     * status and links the record exactly as an admin clicking Approve
     * Admission does. Used where the point is that a really approved
     * application reads as Approved on the notice.
     */
    private function approveViaRoute(AdmissionApplication $application): AdmissionApplication
    {
        $this->post(route('admissions.approve', $application), [
            'academic_session_id' => $this->session->id,
            'admission_date' => '2026-04-01',
            'emergency_contact' => '03001234567',
            'resident_type' => 'Local Resident',
        ])->assertRedirect(route('admissions.show', $application->id));

        return $application->refresh();
    }

    /**
     * Mark an application approved without running an approval.
     *
     * A shortcut for the bulk fixtures, where hundreds of real approvals
     * would be slow and none of them is what is being tested. It sets the two
     * things an approval sets and that isApproved() reads.
     */
    private function markApproved(AdmissionApplication $application): AdmissionApplication
    {
        $application->forceFill([
            'student_id' => $this->studentId(),
            'status' => 'Approved',
        ])->save();

        return $application;
    }

    /**
     * Create a bare student, only so an application can point at one.
     */
    private function studentId(): int
    {
        static $sequence = 0;
        $sequence++;

        return Student::create([
            // A numbering range of its own, so these fixtures cannot collide
            // with the registration numbers the real approval route allocates
            // within the same test.
            'registration_number' => 'STD-TEST-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'full_name' => 'Admitted Student '.$sequence,
            'father_name' => 'Father',
            'gender' => 'Male',
            'father_mobile' => '03001234567',
            'emergency_contact' => '03001234567',
            'admission_date' => '2026-04-01',
            'academic_session_id' => $this->session->id,
            'department_id' => $this->hifz->id,
            'academic_class_id' => $this->nazra->id,
            'student_status' => 'Active',
            'student_type' => 'Hifz',
            'resident_type' => 'Local Resident',
        ])->id;
    }

    /**
     * Render the notice the way the controller does and return the HTML.
     *
     * @param  array<string, mixed>  $query
     */
    private function noticeHtml(array $query = []): string
    {
        return View::make('admissions.pdf.passed-students', $this->noticeData($query))->render();
    }

    public function test_a_passed_and_approved_applicant_appears_and_reads_as_approved(): void
    {
        $application = $this->passed('Ali Raza');

        $this->approveViaRoute($application);

        // The real approval really happened.
        $this->assertTrue($application->isApproved());
        $this->assertSame('Approved', $application->status);

        $html = $this->noticeHtml();

        $this->assertStringContainsString('Ali Raza', $html);
        $this->assertStringContainsString('Admission Status', $html);
        $this->assertStringContainsString('Approved', $html);
    }

    public function test_a_passed_but_unapproved_applicant_still_appears_and_reads_as_not_approved(): void
    {
        // The rule this notice exists for: passing the test is not admission,
        // and a candidate still waiting on a seat must not vanish off the
        // board just because nobody has approved them yet.
        $application = $this->passed('Usman Tariq');

        $this->assertFalse($application->isApproved());
        $this->assertNull($application->student_id);

        $data = $this->noticeData();

        $this->assertSame(1, $data['total']);
        $this->assertSame(['Usman Tariq'], $data['applications']->pluck('student_name')->all());

        $html = View::make('admissions.pdf.passed-students', $data)->render();

        $this->assertStringContainsString('Usman Tariq', $html);
        $this->assertStringContainsString('Not Approved', $html);

        // And never as a refusal: they were not turned down.
        $this->assertStringNotContainsString('Rejected', $html);
    }

    public function test_approved_and_unapproved_passed_applicants_are_listed_together(): void
    {
        // The example the feature was described by: more passed candidates
        // than seats, only some of them approved, all of them on the notice.
        $this->approveViaRoute($this->passed('Ali Raza'));
        $this->approveViaRoute($this->passed('Ahmed Khan'));
        $this->passed('Usman Tariq');
        $this->passed('Bilal Hussain');

        $data = $this->noticeData();

        $this->assertSame(4, $data['total']);
        $this->assertEqualsCanonicalizing(
            ['Ali Raza', 'Ahmed Khan', 'Usman Tariq', 'Bilal Hussain'],
            $data['applications']->pluck('student_name')->all()
        );

        $html = View::make('admissions.pdf.passed-students', $data)->render();

        // Both labels on one sheet, which is the whole point of the column.
        $this->assertStringContainsString('Approved', $html);
        $this->assertStringContainsString('Not Approved', $html);
    }

    public function test_a_failed_applicant_never_appears_whatever_their_admission_status(): void
    {
        $this->passed('Usman Tariq');

        $this->application([
            'student_name' => 'Failed Candidate',
            'status' => 'Failed',
            'test_result' => 'Failed',
            'test_marks' => 20,
        ]);

        $html = $this->noticeHtml();

        $this->assertStringContainsString('Usman Tariq', $html);
        $this->assertStringNotContainsString('Failed Candidate', $html);
    }

    public function test_the_department_filter_keeps_approved_and_unapproved_candidates_alike(): void
    {
        $this->approveViaRoute($this->passed('Hifz Approved'));
        $this->passed('Hifz Waiting');

        $this->passed('School Waiting', [
            'student_type' => 'School',
            'madrassa_class_id' => null,
            'school_class_id' => $this->primary->id,
        ]);

        $data = $this->noticeData(['department_id' => $this->hifz->id]);

        // The filter narrows by department and by nothing else: it must not
        // quietly drop the candidate who has not been approved.
        $this->assertSame(2, $data['total']);
        $this->assertEqualsCanonicalizing(
            ['Hifz Approved', 'Hifz Waiting'],
            $data['applications']->pluck('student_name')->all()
        );
    }

    public function test_the_class_filter_keeps_approved_and_unapproved_candidates_alike(): void
    {
        $this->approveViaRoute($this->passed('Nazra Approved'));
        $this->passed('Nazra Waiting');
        $this->passed('Hifz Class Waiting', ['madrassa_class_id' => $this->hifzClass->id]);

        $data = $this->noticeData(['academic_class_id' => $this->nazra->id]);

        $this->assertSame(2, $data['total']);
        $this->assertEqualsCanonicalizing(
            ['Nazra Approved', 'Nazra Waiting'],
            $data['applications']->pluck('student_name')->all()
        );
    }

    public function test_the_session_filter_still_excludes_other_years_whatever_the_admission_status(): void
    {
        $this->approveViaRoute($this->passed('Approved This Year'));
        $this->passed('Waiting This Year');

        // Last year's intake, one of each, neither of which belongs here.
        $this->inSession($this->markApproved($this->passed('Approved Last Year')), $this->previousSession);
        $this->inSession($this->passed('Waiting Last Year'), $this->previousSession);

        $data = $this->noticeData();

        $this->assertSame($this->session->id, $data['session']->id);
        $this->assertSame(2, $data['total']);
        $this->assertEqualsCanonicalizing(
            ['Approved This Year', 'Waiting This Year'],
            $data['applications']->pluck('student_name')->all()
        );
    }

    public function test_the_true_total_counts_approved_and_unapproved_candidates_together(): void
    {
        // Over the display cap, and mixed: the ones that do not fit are as
        // much a part of the total as the 500 that do.
        $this->manyPassed(512);

        $approved = AdmissionApplication::orderBy('id')->limit(6)->get();

        foreach ($approved as $application) {
            $this->markApproved($application);
        }

        $data = $this->noticeData();

        $this->assertSame(512, $data['total']);
        $this->assertCount(500, $data['applications']);
        $this->assertTrue($data['capped']);

        $html = View::make('admissions.pdf.passed-students', $data)->render();

        $this->assertStringContainsString('Total Passed: 512', $html);
        $this->assertStringContainsString('Showing 500 of 512 passed applicants', $html);
        $this->assertStringContainsString('Approved', $html);
        $this->assertStringContainsString('Not Approved', $html);
    }

    public function test_the_admission_status_column_renders_in_urdu(): void
    {
        $this->approveViaRoute($this->passed('Ali Raza'));
        $this->passed('Usman Tariq');

        $this->assertInlinePdf($this->notice(['language' => ResultReportLanguage::URDU]));

        $urdu = ResultReportLanguage::translator(ResultReportLanguage::URDU);

        // Translated rather than falling through to the English word.
        $this->assertNotSame('Admission Status', $urdu('admission_status'));
        $this->assertNotSame('Approved', $urdu('approved'));
        $this->assertNotSame('Not Approved', $urdu('not_approved'));
    }

    public function test_the_admission_status_is_decided_by_whether_a_student_exists(): void
    {
        $waiting = $this->passed('Usman Tariq');
        $this->assertSame('not_approved', $waiting->admissionStatusKey());

        $approved = $this->approveViaRoute($this->passed('Ali Raza'));
        $this->assertSame('approved', $approved->admissionStatusKey());
    }

    /* ---------------------------------------------------------------- */
    /* The notice ignores the listing's admission status filter */
    /* ---------------------------------------------------------------- */

    public function test_the_notice_ignores_the_admission_status_filter(): void
    {
        // The trap this covers: approving a candidate moves their status on
        // to Approved, so printing from a listing narrowed to Passed used to
        // drop everybody who had actually been given a seat.
        $approved = $this->approveViaRoute($this->passed('Ali Raza'));
        $waiting = $this->passed('Usman Tariq');

        $this->assertSame('Approved', $approved->status);
        $this->assertSame('Passed', $waiting->status);

        // Printed from a page filtered to Passed. Both must still be here.
        $data = $this->noticeData(['status' => 'Passed']);

        $this->assertSame(2, $data['total']);
        $this->assertEqualsCanonicalizing(
            ['Ali Raza', 'Usman Tariq'],
            $data['applications']->pluck('student_name')->all()
        );

        // And from a page filtered to Approved, which would otherwise have
        // dropped the candidate still waiting on a seat.
        $data = $this->noticeData(['status' => 'Approved']);

        $this->assertSame(2, $data['total']);
        $this->assertEqualsCanonicalizing(
            ['Ali Raza', 'Usman Tariq'],
            $data['applications']->pluck('student_name')->all()
        );
    }

    public function test_no_admission_status_filter_can_empty_the_notice(): void
    {
        $this->approveViaRoute($this->passed('Ali Raza'));
        $this->passed('Usman Tariq');

        // Every status the workflow knows about, including the ones neither
        // of these applications holds. None of them may change the sheet.
        foreach (AdmissionApplication::STATUSES as $status) {
            $data = $this->noticeData(['status' => $status]);

            $this->assertSame(2, $data['total'], "The {$status} filter changed the notice.");
            $this->assertEqualsCanonicalizing(
                ['Ali Raza', 'Usman Tariq'],
                $data['applications']->pluck('student_name')->all(),
                "The {$status} filter changed who is on the notice."
            );
        }
    }

    public function test_ignoring_the_status_filter_does_not_let_a_failed_candidate_through(): void
    {
        $this->passed('Usman Tariq');

        // Failed, and carrying a status that a dropped filter must not be
        // able to admit: the test result is what decides, and it still does.
        $this->application([
            'student_name' => 'Failed Candidate',
            'status' => 'Failed',
            'test_result' => 'Failed',
        ]);

        $data = $this->noticeData(['status' => 'Failed']);

        $this->assertSame(1, $data['total']);
        $this->assertSame(['Usman Tariq'], $data['applications']->pluck('student_name')->all());
    }

    public function test_dropping_the_status_filter_leaves_the_other_pdf_filters_alone(): void
    {
        $this->approveViaRoute($this->passed('Hifz Approved'));
        $this->passed('Hifz Waiting');
        $this->passed('School Waiting', [
            'student_type' => 'School',
            'madrassa_class_id' => null,
            'school_class_id' => $this->primary->id,
        ]);
        $this->inSession($this->passed('Hifz Last Year'), $this->previousSession);

        // Status is ignored; session, department and class are not.
        $data = $this->noticeData([
            'status' => 'Passed',
            'department_id' => $this->hifz->id,
        ]);

        $this->assertSame($this->session->id, $data['session']->id);
        $this->assertSame(2, $data['total']);
        $this->assertEqualsCanonicalizing(
            ['Hifz Approved', 'Hifz Waiting'],
            $data['applications']->pluck('student_name')->all()
        );
    }

    public function test_the_listing_still_filters_by_status_while_the_notice_does_not(): void
    {
        $approved = $this->approveViaRoute($this->passed('Ali Raza'));
        $waiting = $this->passed('Usman Tariq');

        // The listing keeps its own behaviour: a page filtered to Passed
        // shows the candidate still waiting and not the one already approved.
        //
        // Asserted on application numbers rather than names, because the
        // approval leaves a flash message naming the student it created and
        // that banner is still on the page after the redirect.
        $content = $this->get(route('admissions.index', ['status' => 'Passed']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($waiting->application_number, $content);
        $this->assertStringNotContainsString($approved->application_number, $content);

        // The notice, from that same filtered page, shows both. The two
        // diverge deliberately: this assertion is the whole point of the
        // change and would fail if the filter ever flowed through again.
        $data = $this->noticeData(['status' => 'Passed']);

        $this->assertEqualsCanonicalizing(
            ['Ali Raza', 'Usman Tariq'],
            $data['applications']->pluck('student_name')->all()
        );
    }
}
