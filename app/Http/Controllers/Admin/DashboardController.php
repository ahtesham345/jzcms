<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use Illuminate\Support\Carbon;

/**
 * The figures on the administrator's dashboard.
 *
 * Nothing here is a new measurement. Each card reads the table the module
 * that owns it already writes to, through that module's own definitions:
 * "current student" is student_status Active, the same test
 * Student::canBePromoted() applies, and the attendance percentage is
 * StudentAttendance's, denominator included. A second definition of either
 * living on the dashboard would drift from the pages it summarises.
 *
 * The counts are aggregates, never collections: the dashboard asks the
 * database how many, and no row is loaded to be counted in PHP.
 *
 * The pending fees card has no source. There is no fee table, model or
 * controller in the project - the fee routes in routes/accounts.php,
 * routes/admin.php and routes/parent.php are all commented-out placeholders
 * - so the card reports that rather than being handed an invented figure.
 */
class DashboardController extends Controller
{
    /**
     * The student status a current student holds.
     *
     * The students table records Active, Passed and Left. Only Active is a
     * student still attending, which is what the listing filters on and
     * what canBePromoted() tests.
     */
    private const ACTIVE_STUDENT = 'Active';

    /**
     * The teacher status a serving teacher holds.
     */
    private const ACTIVE_TEACHER = 'Active';

    /**
     * Display the dashboard.
     */
    public function index()
    {
        // One "now" for the whole page, in the application's timezone, so
        // the month the cards count and the day the register is read for
        // cannot straddle midnight between one query and the next.
        $now = Carbon::now();

        return view('admin.dashboard', [
            'studentStats' => $this->studentStats($now),
            'teacherStats' => $this->teacherStats($now),
            'attendanceStats' => $this->attendanceStats($now),
        ]);
    }

    /**
     * Count the students on the roll, and this month's admissions.
     *
     * Admissions are counted by admission_date rather than by created_at:
     * the first is when the student joined the institution, the second is
     * when somebody typed them in, and a backlog entered in October is not
     * an October admission.
     *
     * @return array{total: int, joined_this_month: int}
     */
    private function studentStats(Carbon $now): array
    {
        [$monthStart, $nextMonth] = $this->currentMonth($now);

        return [
            'total' => Student::query()
                ->where('student_status', self::ACTIVE_STUDENT)
                ->count(),

            'joined_this_month' => Student::query()
                ->where('student_status', self::ACTIVE_STUDENT)
                ->where('admission_date', '>=', $monthStart)
                ->where('admission_date', '<', $nextMonth)
                ->count(),
        ];
    }

    /**
     * Count the serving teachers, and this month's joiners.
     *
     * @return array{total: int, joined_this_month: int}
     */
    private function teacherStats(Carbon $now): array
    {
        [$monthStart, $nextMonth] = $this->currentMonth($now);

        return [
            'total' => Teacher::query()
                ->where('teacher_status', self::ACTIVE_TEACHER)
                ->count(),

            'joined_this_month' => Teacher::query()
                ->where('teacher_status', self::ACTIVE_TEACHER)
                ->where('joining_date', '>=', $monthStart)
                ->where('joining_date', '<', $nextMonth)
                ->count(),
        ];
    }

    /**
     * Summarise today's student attendance register.
     *
     * One grouped query over the day's rows, and the percentage is
     * StudentAttendance's own: the denominator is what has actually been
     * recorded, so a morning that nobody has transcribed yet reads N/A
     * rather than 0%. The same figure the attendance reports show.
     *
     * Sunday is the weekly off day, so there is no register to summarise.
     * The card says so instead of reporting an empty one as a bad day.
     *
     * @return array{percentage: string, present: int, is_teaching_day: bool}
     */
    private function attendanceStats(Carbon $now): array
    {
        $counts = StudentAttendance::query()
            ->where('attendance_date', StudentAttendance::normalizeDate($now))
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $present = (int) $counts->get(StudentAttendance::STATUS_PRESENT, 0);
        $absent = (int) $counts->get(StudentAttendance::STATUS_ABSENT, 0);

        return [
            'percentage' => StudentAttendance::formatPercentage($present, $absent),
            'present' => $present,
            'is_teaching_day' => StudentAttendance::isAttendanceDay($now),
        ];
    }

    /**
     * The month a date falls in, as a half-open range of Y-m-d bounds.
     *
     * Half-open - from the 1st, up to but not including the 1st of the
     * next month - rather than first-and-last-day. A date cast is stored
     * as a timestamp at midnight, so an inclusive '31 August' bound
     * sorts before '31 August 00:00:00' and would drop the last day of
     * every month. This form is right whether the column holds a bare
     * date or a midnight timestamp, and still reads the index.
     *
     * @return array{0: string, 1: string}
     */
    private function currentMonth(Carbon $now): array
    {
        return [
            $now->copy()->startOfMonth()->format('Y-m-d'),
            $now->copy()->startOfMonth()->addMonth()->format('Y-m-d'),
        ];
    }
}
