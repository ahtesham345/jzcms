<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BuildsMadrassaFixtures;
use Tests\TestCase;

/**
 * The figures and the shortcuts on the administrator's dashboard.
 *
 * What is pinned down here is that every number on the page came out of the
 * database, that each one is counted the way the module that owns it counts
 * it, and that the three quick actions reach the pages they name. The cards
 * themselves - their layout, icons and colours - are not this suite's
 * business.
 *
 * "Today" is fixed for the whole suite. The attendance card reads the day's
 * register and the two "this month" figures read the month's, so a run at
 * one minute to midnight would otherwise disagree with a run at one minute
 * past.
 */
class DashboardTest extends TestCase
{
    use BuildsMadrassaFixtures;
    use RefreshDatabase;

    /** A Monday: a teaching day, mid-month, so the month has room either side. */
    private const TODAY = '2026-08-03';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::TODAY.' 09:00:00');

        // Builds the academic structure and signs in as an administrator.
        $this->buildMadrassa();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ---------------------------------------------------------------- */
    /* The page itself */
    /* ---------------------------------------------------------------- */

    public function test_the_dashboard_loads_for_a_signed_in_user(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total Students')
            ->assertSee('Quick Actions');
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        auth()->logout();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /* ---------------------------------------------------------------- */
    /* Total students */
    /* ---------------------------------------------------------------- */

    public function test_the_student_count_comes_from_the_database(): void
    {
        foreach (['Fawad Ahmed', 'Bilal Ahmad', 'Usman Tariq'] as $name) {
            $this->student($name);
        }

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<p class="text-3xl font-bold text-gray-900">3</p>', false);
    }

    public function test_the_student_count_follows_the_projects_own_definition_of_a_current_student(): void
    {
        $this->student('Fawad Ahmed');

        // The other two statuses the students table records. A student who
        // has passed out or left is no longer on the roll, which is the
        // same test the listing filter and canBePromoted() apply.
        $this->student('Bilal Ahmad')->update(['student_status' => 'Passed']);
        $this->student('Usman Tariq')->update(['student_status' => 'Left']);

        $this->assertSame(3, Student::count());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<p class="text-3xl font-bold text-gray-900">1</p>', false);
    }

    public function test_this_months_admissions_are_counted_from_the_admission_date(): void
    {
        // Admitted this month, on the first day of it and on the last.
        $this->student('Fawad Ahmed')->update(['admission_date' => '2026-08-01']);
        $this->student('Bilal Ahmad')->update(['admission_date' => '2026-08-31']);

        // Admitted before it: on the roll, but not one of this month's.
        $this->student('Usman Tariq')->update(['admission_date' => '2026-07-31']);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<span class="font-medium">+2</span> this month', false);
    }

    /* ---------------------------------------------------------------- */
    /* Total teachers */
    /* ---------------------------------------------------------------- */

    public function test_the_teacher_count_comes_from_the_database(): void
    {
        $this->teacher('Active', 'Qari Abdul Rahman');
        $this->teacher('Active', 'Hafiz Bilal');

        // Not serving, so not counted - the same status the teacher
        // listing filters on.
        $this->teacher('Inactive', 'Qari Retired');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<p class="text-3xl font-bold text-gray-900">2</p>', false);
    }

    public function test_teachers_who_joined_this_month_are_counted_from_the_joining_date(): void
    {
        $joined = $this->teacher('Active', 'Hafiz Bilal');
        $joined->update(['joining_date' => '2026-08-10']);

        // The fixture's default joining date is January, so this one is on
        // the staff but is not a new joiner.
        $this->teacher('Active', 'Qari Abdul Rahman');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<span class="font-medium">+1</span> this month', false);
    }

    /* ---------------------------------------------------------------- */
    /* Today's attendance */
    /* ---------------------------------------------------------------- */

    public function test_todays_attendance_is_read_from_the_register(): void
    {
        $this->markToday(present: 3, absent: 1);

        $this->get(route('dashboard'))
            ->assertOk()
            // StudentAttendance's own percentage, to its own two decimals:
            // three of the four recorded marks are present.
            ->assertSee('<p class="text-3xl font-bold text-gray-900">75.00%</p>', false)
            ->assertSee('<span class="font-medium">3</span> present', false);
    }

    public function test_attendance_ignores_a_register_from_another_day(): void
    {
        $this->markToday(present: 2, absent: 0);

        // Saturday's sheet, which the card must not add to today's.
        StudentAttendance::create([
            'student_academic_enrollment_id' => $this->madrassaEnrollment($this->student('Late Entry'))->id,
            'attendance_date' => self::SATURDAY,
            'attendance_period' => StudentAttendance::PERIOD_MORNING,
            'status' => StudentAttendance::STATUS_ABSENT,
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<p class="text-3xl font-bold text-gray-900">100.00%</p>', false)
            ->assertSee('<span class="font-medium">2</span> present', false);
    }

    public function test_an_unmarked_day_reads_na_rather_than_zero_percent(): void
    {
        $this->student('Fawad Ahmed');

        // Nothing has been transcribed yet. A day the office has not
        // reached is not a day everybody was absent, which is exactly what
        // StudentAttendance::attendancePercentage() refuses to say.
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<p class="text-3xl font-bold text-gray-900">N/A</p>', false);
    }

    public function test_the_weekly_off_day_is_reported_rather_than_an_empty_register(): void
    {
        // Sunday: no attendance is taken at all, so there is no register to
        // summarise and an empty one must not read as a bad day.
        Carbon::setTestNow(self::SUNDAY.' 09:00:00');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Weekly off day');
    }

    /* ---------------------------------------------------------------- */
    /* Nothing left hardcoded */
    /* ---------------------------------------------------------------- */

    public function test_the_dashboard_carries_none_of_the_placeholder_figures(): void
    {
        $this->student('Fawad Ahmed');
        $this->teacher();
        $this->markToday(present: 1, absent: 0);

        $response = $this->get(route('dashboard'))->assertOk();

        // The numbers the page shipped with. Matched inside their own
        // markup, so a genuine count that happens to be 89 cannot be
        // mistaken for the placeholder.
        $response->assertDontSee('<p class="text-3xl font-bold text-gray-900">1,234</p>', false);
        $response->assertDontSee('<p class="text-3xl font-bold text-gray-900">89</p>', false);
        $response->assertDontSee('<p class="text-3xl font-bold text-gray-900">94.5%</p>', false);
        $response->assertDontSee('Rs. 45,000');
        $response->assertDontSee('<span class="font-medium">+12</span>', false);
        $response->assertDontSee('<span class="font-medium">1,167</span>', false);
    }

    public function test_the_pending_fees_card_reports_that_there_is_no_fee_module(): void
    {
        // There is no fee table, model or controller in the project - the
        // fee routes are commented-out placeholders - so the card says so
        // rather than being handed an invented figure. This assertion is
        // what will fail when a fee module arrives and the card needs
        // wiring to it.
        $this->assertFalse(
            Schema::hasTable('fees'),
            'A fees table now exists: wire the Pending Fees card to it.'
        );

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pending Fees')
            ->assertSee('Fees module not set up');
    }

    /* ---------------------------------------------------------------- */
    /* The quick actions */
    /* ---------------------------------------------------------------- */

    public function test_the_shortcut_buttons_point_at_the_existing_routes(): void
    {
        $response = $this->get(route('dashboard'))->assertOk();

        $response->assertSee('href="'.route('students.create').'"', false);
        $response->assertSee('href="'.route('attendance.index').'"', false);
        $response->assertSee('href="'.route('results.reports').'"', false);

        // No dead links left among the quick actions. Scoped to that block:
        // the sidebar still carries placeholders for the modules that have
        // not been built, and those are not this page's to fix.
        $html = $response->getContent();
        $quickActions = substr($html, strpos($html, 'Quick Actions'));

        $this->assertStringNotContainsString('href="#"', $quickActions);
    }

    public function test_each_shortcut_reaches_a_page_the_signed_in_user_may_open(): void
    {
        $this->get(route('students.create'))->assertOk();
        $this->get(route('attendance.index'))->assertOk();
        $this->get(route('results.reports'))->assertOk();
    }

    /* ---------------------------------------------------------------- */
    /* The cost of the page */
    /* ---------------------------------------------------------------- */

    public function test_the_statistics_are_counted_by_the_database_not_in_php(): void
    {
        foreach (range(1, 10) as $index) {
            $this->madrassaEnrollment($this->student('Student '.$index));
        }

        $this->markToday(present: 4, absent: 2);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('dashboard'))->assertOk();

        $log = collect(DB::getQueryLog());

        DB::disableQueryLog();
        DB::flushQueryLog();

        // Every figure is an aggregate: nothing selects the rows behind a
        // card, so sixteen students cost what one does.
        $loaded = $log->filter(
            fn ($entry) => str_contains($entry['query'], 'select * from "students"')
            || str_contains($entry['query'], 'select * from "teachers"')
            || str_contains($entry['query'], 'select * from "student_attendances"')
        );

        $this->assertCount(0, $loaded, 'The dashboard loaded rows it only needed to count');

        // Four counts, one grouped read of the day's register, and what the
        // layout itself costs. A ceiling, so a card added later cannot
        // quietly bring an N+1 with it.
        $this->assertLessThanOrEqual(
            10,
            $log->count(),
            'The dashboard is running more queries than its cards can account for'
        );
    }

    /* ---------------------------------------------------------------- */
    /* Helpers */
    /* ---------------------------------------------------------------- */

    /**
     * Mark today's morning register for a number of newly created students.
     */
    private function markToday(int $present, int $absent): void
    {
        $rows = [];

        foreach (range(1, $present + $absent) as $index) {
            $enrollment = $this->madrassaEnrollment($this->student('Marked '.$index));

            $rows[] = [
                'student_academic_enrollment_id' => $enrollment->id,
                'attendance_date' => self::TODAY,
                'status' => $index <= $present
                    ? StudentAttendance::STATUS_PRESENT
                    : StudentAttendance::STATUS_ABSENT,
            ];
        }

        StudentAttendance::recordSheet(StudentAttendance::PERIOD_MORNING, $rows);
    }
}
