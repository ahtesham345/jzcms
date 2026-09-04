<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\StudentPrayerAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A whole academic session of prayer attendance, at a glance.
 *
 * The monthly report answers "how did August go"; this answers "how has the
 * year gone". Read only, like every prayer report: it counts rows and never
 * writes one.
 *
 * What makes a session harder than a month is Expected. A month's expected
 * count is the same for everyone on the sheet, but over a year students
 * join and leave, so each student's expected prayers are the working days
 * of their own madrassa placement inside the session, times five. A student
 * who joined in November is not marked down for September.
 *
 * The prayer rows are aggregated in SQL. Only the enrollment periods are
 * handled in PHP, because clamping a placement to a session and then to
 * each month is interval arithmetic rather than something to ask the
 * database for once per student per month.
 *
 * Madrassa only. A school-only student cannot appear, a Hifz + School
 * student is counted once through their madrassa placement, and every
 * figure is reached through student_academic_enrollment_id - which is also
 * what keeps a promoted student's old records reported under the class they
 * were in at the time.
 */
class PrayerAttendanceSessionSummaryController extends Controller
{
    /**
     * How many students to show per page.
     */
    private const PER_PAGE = 25;

    /**
     * Display the session summary.
     */
    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $session = $filters['academic_session_id']
            ? AcademicSession::find($filters['academic_session_id'])
            : null;

        $data = [
            'filters' => $filters,
            'session' => $session,
            'prayers' => StudentPrayerAttendance::PRAYERS,
            'prayerInitials' => StudentPrayerAttendance::PRAYER_INITIALS,
            'students' => null,
            'group' => null,
            'monthly' => [],
            'heading' => $this->groupHeading($filters),
            ...$this->filterOptions(),
        ];

        // The session is the report. Without one there is no period to
        // count expected prayers over, so nothing is drawn rather than a
        // year being guessed at.
        if ($session === null) {
            return view('prayer-attendance.session-summary', $data);
        }

        [$sessionStart, $sessionEnd] = $this->sessionBounds($session);

        // The day the summary counts through. A session still running is
        // counted only as far as today: a day nobody has reached cannot
        // have been prayed, and it cannot have been recorded either. Both
        // sides of every figure stop here, so Recorded can never run past
        // Expected and a register entered ahead of time does not start
        // showing up in the year's totals early.
        $countedEnd = $this->countedEnd($sessionEnd);

        // Every filtered placement, with only the columns the expected
        // arithmetic needs. One query, however many students.
        $periods = $this->enrollmentPeriods($filters);

        // Expected prayers per placement and per student, worked out from
        // the actual enrollment dates.
        $expected = $this->expectedPrayers($periods, $sessionStart, $countedEnd);

        $students = $this->studentReportQuery($filters, $sessionStart, $countedEnd)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('prayer-attendance.session-summary', [
            ...$data,
            'students' => $students,
            // Keyed by enrollment, so a table row reads its own expected
            // count without another query.
            'expectedByEnrollment' => $expected['by_enrollment'],
            'group' => $this->groupSummary($filters, $sessionStart, $countedEnd, $expected),
            'monthly' => $this->monthlyBreakdown($filters, $periods, $sessionStart, $sessionEnd, $countedEnd),
            'sessionStart' => $sessionStart,
            'sessionEnd' => $sessionEnd,
            // Shown only when it differs from the session end, so the page
            // can say why a running session reports less than a whole year.
            'countedEnd' => $countedEnd,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Expected prayers */
    /* ------------------------------------------------------------------ */

    /**
     * Get the filtered madrassa placements and their dates.
     *
     * Only the columns the expected arithmetic needs, so this stays one
     * small query even across a whole institution.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    private function enrollmentPeriods(array $filters)
    {
        return $this->baseEnrollmentQuery($filters)
            ->select('sae.id', 'sae.student_id', 'sae.start_date', 'sae.end_date')
            ->get();
    }

    /**
     * Work out each student's expected prayers for the session.
     *
     * A placement is clamped to the session first: the days before a
     * student joined and after they left are not days they could have
     * prayed here. What is left is counted in working days - weekends are
     * never opportunities - and multiplied by the five daily prayers.
     *
     * The per-student figure unions the placements rather than adding them,
     * so a calendar day covered twice still offers five prayers and not
     * ten. The database's unique index already allows only one madrassa
     * placement per student per session, so this is a guard rather than a
     * common case, but the arithmetic should not depend on that index.
     *
     * @param  Collection<int, object>  $periods
     * @return array{by_enrollment: array<int, int>, by_student: array<int, int>, total: int, working_days_total: int}
     */
    private function expectedPrayers($periods, string $sessionStart, string $sessionEnd): array
    {
        $byEnrollment = [];
        $studentPeriods = [];

        foreach ($periods as $period) {
            $window = StudentPrayerAttendance::overlappingPeriod(
                $period->start_date,
                $period->end_date,
                $sessionStart,
                $sessionEnd
            );

            if ($window === null) {
                $byEnrollment[(int) $period->id] = 0;

                continue;
            }

            $byEnrollment[(int) $period->id] = StudentPrayerAttendance::expectedPrayersForDays(
                StudentPrayerAttendance::workingDaysBetween($window[0], $window[1])
            );

            $studentPeriods[(int) $period->student_id][] = $window;
        }

        $byStudent = [];
        $workingDaysTotal = 0;

        foreach ($studentPeriods as $studentId => $windows) {
            $days = StudentPrayerAttendance::workingDaysAcrossPeriods($windows);

            $workingDaysTotal += $days;
            $byStudent[$studentId] = StudentPrayerAttendance::expectedPrayersForDays($days);
        }

        return [
            'by_enrollment' => $byEnrollment,
            'by_student' => $byStudent,
            'total' => array_sum($byStudent),
            // The denominator each single prayer expects: one a day.
            'working_days_total' => $workingDaysTotal,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* The student-wise report */
    /* ------------------------------------------------------------------ */

    /**
     * Build the student-wise session report.
     *
     * Driven by the placements rather than by the prayer rows, so a student
     * the register never mentions still appears - with zeroes rather than
     * not at all. The session dates sit in the join condition and not in a
     * where clause: moved there they would turn the left join back into an
     * inner one and drop exactly those students.
     *
     * @param  array<string, mixed>  $filters
     */
    private function studentReportQuery(array $filters, string $sessionStart, string $countedEnd)
    {
        $query = $this->baseEnrollmentQuery($filters)
            // Bounded at today for a running session, the same as Expected:
            // a record dated ahead of today describes a day nobody has
            // reached, so it is not counted until its date arrives.
            ->leftJoin('student_prayer_attendances as spa', function ($join) use ($sessionStart, $countedEnd) {
                $join->on('spa.student_academic_enrollment_id', '=', 'sae.id')
                    ->whereBetween('spa.attendance_date', [$sessionStart, $countedEnd]);
            })
            ->groupBy(
                'sae.id',
                'sae.student_id',
                'students.full_name',
                'students.registration_number',
                'students.roll_number',
                'academic_classes.name',
                'sections.name'
            )
            ->select([
                'sae.id as enrollment_id',
                'sae.student_id',
                'students.full_name',
                'students.registration_number',
                'students.roll_number',
                // Read through the placement the prayers hang off, so a
                // promoted student's rows still name the class they were in.
                'academic_classes.name as class_name',
                'sections.name as section_name',
                DB::raw('count(spa.id) as recorded'),
                DB::raw($this->statusSum(StudentPrayerAttendance::STATUS_PRESENT).' as present'),
                DB::raw($this->statusSum(StudentPrayerAttendance::STATUS_ABSENT).' as absent'),
            ])
            ->orderBy('students.roll_number')
            ->orderBy('students.registration_number')
            ->orderBy('students.full_name');

        foreach ($this->prayerStatusColumns() as $alias => $expression) {
            $query->addSelect(DB::raw("{$expression} as {$alias}"));
        }

        return $query;
    }

    /**
     * The placements every figure is derived from.
     *
     * The track condition is the report's boundary: a school enrollment is
     * not in this set at all.
     *
     * @param  array<string, mixed>  $filters
     */
    private function baseEnrollmentQuery(array $filters)
    {
        return DB::table('student_academic_enrollments as sae')
            ->join('students', 'students.id', '=', 'sae.student_id')
            ->join('academic_classes', 'academic_classes.id', '=', 'sae.academic_class_id')
            // Left, because a class may be run without sections.
            ->leftJoin('sections', 'sections.id', '=', 'sae.section_id')
            ->where('sae.academic_track', StudentPrayerAttendance::ACADEMIC_TRACK)
            ->where('sae.academic_session_id', $filters['academic_session_id'])
            ->when($filters['department_id'], fn ($query, $id) => $query->where('sae.department_id', $id))
            ->when($filters['academic_class_id'], fn ($query, $id) => $query->where('sae.academic_class_id', $id))
            ->when($filters['section_id'], fn ($query, $id) => $query->where('sae.section_id', $id))
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($match) use ($search) {
                    $match->where('students.full_name', 'like', "%{$search}%")
                        ->orWhere('students.registration_number', 'like', "%{$search}%")
                        ->orWhere('students.roll_number', 'like', "%{$search}%");
                });
            });
    }

    /**
     * Build the SQL that counts rows of one status.
     */
    private function statusSum(string $status): string
    {
        return "sum(case when spa.status = '{$status}' then 1 else 0 end)";
    }

    /**
     * Build the ten per-prayer columns, keyed by their column alias.
     *
     * The prayer and status names come from the model's own constants,
     * never from a request.
     *
     * @return array<string, string>
     */
    private function prayerStatusColumns(): array
    {
        $columns = [];

        foreach (StudentPrayerAttendance::PRAYERS as $prayer) {
            foreach (StudentPrayerAttendance::STATUSES as $status) {
                $columns[$this->prayerColumn($prayer, $status)] =
                    "sum(case when spa.prayer = '{$prayer}' and spa.status = '{$status}' then 1 else 0 end)";
            }
        }

        return $columns;
    }

    /**
     * Name the column one prayer's status count is returned under.
     */
    private function prayerColumn(string $prayer, string $status): string
    {
        return strtolower($prayer).'_'.strtolower($status);
    }

    /* ------------------------------------------------------------------ */
    /* The group summary */
    /* ------------------------------------------------------------------ */

    /**
     * Summarise the whole session for the filtered group.
     *
     * The per-student aggregate is rolled up once as a subquery, so this
     * costs one query however many students there are.
     *
     * The percentage is computed from the group's own totals and never by
     * averaging the students' percentages: a student with two recorded
     * prayers must not weigh the same as one with a thousand.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function groupSummary(array $filters, string $sessionStart, string $countedEnd, array $expected): array
    {
        $columns = [
            DB::raw('count(distinct student_id) as students'),
            DB::raw('sum(case when recorded > 0 then 1 else 0 end) as students_with_records'),
            DB::raw('coalesce(sum(recorded), 0) as recorded'),
            DB::raw('coalesce(sum(present), 0) as present'),
            DB::raw('coalesce(sum(absent), 0) as absent'),
        ];

        foreach (array_keys($this->prayerStatusColumns()) as $alias) {
            $columns[] = DB::raw("coalesce(sum({$alias}), 0) as {$alias}");
        }

        $rolled = DB::query()
            ->fromSub($this->studentReportQuery($filters, $sessionStart, $countedEnd)->reorder(), 'per_student')
            ->select($columns)
            ->first();

        $students = (int) ($rolled->students ?? 0);
        $withRecords = (int) ($rolled->students_with_records ?? 0);
        $present = (int) ($rolled->present ?? 0);
        $absent = (int) ($rolled->absent ?? 0);
        $recorded = (int) ($rolled->recorded ?? 0);
        $total = $expected['total'];

        return [
            'students' => $students,
            'students_with_records' => $withRecords,
            'students_without_records' => max(0, $students - $withRecords),
            'expected' => $total,
            'recorded' => $recorded,
            'unrecorded' => max(0, $total - $recorded),
            'present' => $present,
            'absent' => $absent,
            'percentage' => StudentPrayerAttendance::formatPercentage($present, $absent),
            'by_prayer' => $this->prayerBreakdown($rolled, $expected['working_days_total']),
        ];
    }

    /**
     * Break the session's totals down across the five prayers.
     *
     * Each prayer happens once a working day, so its expected count is the
     * students' working enrollment days - a fifth of the session's total,
     * and built from the same enrollment-aware arithmetic.
     *
     * @return array<string, array<string, mixed>>
     */
    private function prayerBreakdown(?object $rolled, int $workingDaysTotal): array
    {
        $breakdown = [];

        foreach (StudentPrayerAttendance::PRAYERS as $prayer) {
            $present = (int) ($rolled->{$this->prayerColumn($prayer, StudentPrayerAttendance::STATUS_PRESENT)} ?? 0);
            $absent = (int) ($rolled->{$this->prayerColumn($prayer, StudentPrayerAttendance::STATUS_ABSENT)} ?? 0);
            $recorded = $present + $absent;

            $breakdown[$prayer] = [
                'expected' => $workingDaysTotal,
                'recorded' => $recorded,
                'unrecorded' => max(0, $workingDaysTotal - $recorded),
                'present' => $present,
                'absent' => $absent,
                'percentage' => StudentPrayerAttendance::formatPercentage($present, $absent),
            ];
        }

        return $breakdown;
    }

    /* ------------------------------------------------------------------ */
    /* The monthly breakdown */
    /* ------------------------------------------------------------------ */

    /**
     * Break the session down into the months it runs over.
     *
     * Expected is worked out per month from each student's own enrollment
     * window, so a student who joined in November contributes nothing to
     * September and their real working days to November.
     *
     * The recorded side is one grouped query for the whole session rather
     * than one per month. The month is taken as the first seven characters
     * of the date, which reads the same on both drivers the project runs
     * on: MySQL renders a DATE as Y-m-d for a string function, and SQLite
     * stores it that way already.
     *
     * @param  array<string, mixed>  $filters
     * @param  Collection<int, object>  $periods
     * @return array<int, array<string, mixed>>
     */
    private function monthlyBreakdown(
        array $filters,
        $periods,
        string $sessionStart,
        string $sessionEnd,
        string $countedEnd
    ): array {
        // Bounded at today for a running session, so a month still to come
        // reports nothing recorded as well as nothing expected. The month
        // list below still runs to the session end: the session covers
        // those months even before they arrive.
        $recorded = $this->baseEnrollmentQuery($filters)
            ->join('student_prayer_attendances as spa', 'spa.student_academic_enrollment_id', '=', 'sae.id')
            ->whereBetween('spa.attendance_date', [$sessionStart, $countedEnd])
            ->groupBy(DB::raw('substr(spa.attendance_date, 1, 7)'))
            ->select([
                DB::raw('substr(spa.attendance_date, 1, 7) as period'),
                DB::raw($this->statusSum(StudentPrayerAttendance::STATUS_PRESENT).' as present'),
                DB::raw($this->statusSum(StudentPrayerAttendance::STATUS_ABSENT).' as absent'),
            ])
            ->get()
            ->keyBy('period');

        $months = [];
        $cursor = Carbon::parse($sessionStart)->startOfMonth();
        $last = Carbon::parse($sessionEnd)->startOfMonth();

        while ($cursor->lessThanOrEqualTo($last)) {
            $monthStart = $cursor->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $cursor->copy()->endOfMonth()->format('Y-m-d');
            $key = $cursor->format('Y-m');

            // Capped at today, so a month still to come expects nothing and
            // therefore reports nothing as unrecorded. The month itself is
            // still listed: the session runs over it.
            $expected = StudentPrayerAttendance::expectedPrayersForDays(
                $this->workingDaysInMonth($periods, $monthStart, $monthEnd, $sessionStart, $countedEnd)
            );

            $row = $recorded->get($key);
            $present = (int) ($row->present ?? 0);
            $absent = (int) ($row->absent ?? 0);

            $months[] = [
                'label' => $cursor->format('F Y'),
                'expected' => $expected,
                'recorded' => $present + $absent,
                'unrecorded' => max(0, $expected - ($present + $absent)),
                'present' => $present,
                'absent' => $absent,
                'percentage' => StudentPrayerAttendance::formatPercentage($present, $absent),
            ];

            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * Count the working enrollment days one month holds, across students.
     *
     * Each student's placements are clamped to the session and then to the
     * month, and unioned per student so a day covered twice still counts
     * once.
     *
     * @param  Collection<int, object>  $periods
     */
    private function workingDaysInMonth(
        $periods,
        string $monthStart,
        string $monthEnd,
        string $sessionStart,
        string $sessionEnd
    ): int {
        $byStudent = [];

        foreach ($periods as $period) {
            $window = StudentPrayerAttendance::overlappingPeriod(
                $period->start_date,
                $period->end_date,
                $sessionStart,
                $sessionEnd
            );

            if ($window === null) {
                continue;
            }

            $inMonth = StudentPrayerAttendance::overlappingPeriod(
                $window[0],
                $window[1],
                $monthStart,
                $monthEnd
            );

            if ($inMonth !== null) {
                $byStudent[(int) $period->student_id][] = $inMonth;
            }
        }

        $days = 0;

        foreach ($byStudent as $windows) {
            $days += StudentPrayerAttendance::workingDaysAcrossPeriods($windows);
        }

        return $days;
    }

    /* ------------------------------------------------------------------ */
    /* Filters */
    /* ------------------------------------------------------------------ */

    /**
     * Get the dates the session is counted between.
     *
     * A session with no end date is counted to today: prayers cannot be
     * expected for days that have not happened.
     *
     * @return array{0: string, 1: string}
     */
    private function sessionBounds(AcademicSession $session): array
    {
        $start = Carbon::parse($session->start_date)->format('Y-m-d');

        $end = $session->end_date === null
            ? Carbon::now()->format('Y-m-d')
            : Carbon::parse($session->end_date)->format('Y-m-d');

        return [$start, max($start, $end)];
    }

    /**
     * Get the last day the summary counts through.
     *
     * A session still running is counted only as far as today. Days that
     * have not happened yet offered nobody a prayer, so counting them as
     * expected would report the remainder of the year as a register the
     * office has failed to transcribe.
     *
     * The same bound applies to the prayer rows. A record dated ahead of
     * today describes a day that has not happened, so it is left out of
     * Recorded, Present and Absent until its date arrives - which is also
     * what keeps Recorded from ever exceeding Expected.
     *
     * A session that has already ended keeps its own end date: there is no
     * future left in it to exclude, so a completed session reports exactly
     * as it did before.
     *
     * Only this report is bounded. The entry sheet still writes any date it
     * validates, and a student's prayer history still shows every record on
     * file, whatever it is dated.
     */
    private function countedEnd(string $sessionEnd): string
    {
        return min($sessionEnd, Carbon::now()->format('Y-m-d'));
    }

    /**
     * Read the report filters off the request.
     *
     * All of them are AND conditions: a class from another department
     * matches nothing rather than one filter quietly winning.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'academic_session_id' => $this->cleaned($request->input('academic_session_id')),
            'department_id' => $this->cleaned($request->input('department_id')),
            'academic_class_id' => $this->cleaned($request->input('academic_class_id')),
            'section_id' => $this->cleaned($request->input('section_id')),
            'search' => $this->cleaned($request->input('search')),
        ];
    }

    /**
     * Reduce a blank filter to null.
     */
    private function cleaned(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Resolve the names the report heading is built from.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function groupHeading(array $filters): array
    {
        return [
            'department' => $filters['department_id'] ? Department::find($filters['department_id']) : null,
            'academicClass' => $filters['academic_class_id'] ? AcademicClass::find($filters['academic_class_id']) : null,
            'section' => $filters['section_id'] ? Section::find($filters['section_id']) : null,
        ];
    }

    /**
     * Get the options the filter selects are built from.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'academicSessions' => AcademicSession::where('status', true)->orderByDesc('start_date')->get(),
            'departments' => Department::where('status', true)->orderBy('name')->get(),
            'classesByDepartment' => AcademicClass::where('status', true)
                ->orderBy('name')
                ->get(['id', 'name', 'department_id'])
                ->groupBy('department_id')
                ->map(fn ($classes) => $classes->map->only(['id', 'name'])->values()),
            'sectionsByClass' => Section::where('status', true)
                ->orderBy('name')
                ->get(['id', 'name', 'academic_class_id'])
                ->groupBy('academic_class_id')
                ->map(fn ($sections) => $sections->map->only(['id', 'name'])->values()),
        ];
    }
}
