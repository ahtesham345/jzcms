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
use Illuminate\Support\Facades\DB;

/**
 * Prayer attendance reports.
 *
 * Totals over what the monthly sheet has recorded, for one month at a time.
 * Read only: nothing here writes a prayer, and a month with nothing entered
 * reports as unrecorded rather than as a month of absences.
 *
 * The distinction the whole report turns on is Recorded against Expected. A
 * working day offers five prayers; a row exists only once somebody has
 * transcribed one from the paper register. A prayer with no row is
 * Unrecorded - office work outstanding - and never an absence. Weekends
 * offer nothing at all, so they are excluded before Expected is even
 * counted.
 *
 * Percentages are therefore always Present over Recorded, never Present
 * over Expected. A class whose register is half entered reads as a high
 * percentage over few records, not as a disaster.
 *
 * Madrassa only. The rows are drawn from Madrassa enrollments, so a
 * school-only student cannot appear and a Hifz + School student appears
 * once, through their madrassa placement. Every figure about a student is
 * reached through student_academic_enrollment_id, which is also what keeps
 * a historical month reporting the placement the student held then.
 */
class PrayerAttendanceReportController extends Controller
{
    /**
     * How many students to show per page.
     */
    private const PER_PAGE = 25;

    /**
     * Display the report.
     */
    public function index(Request $request)
    {
        $filters = $this->filters($request);

        [$start, $end] = $this->monthBounds($filters);

        $students = $this->studentReportQuery($filters, $start, $end)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $group = $this->groupSummary($filters, $start, $end);

        return view('prayer-attendance.reports', [
            'filters' => $filters,
            'students' => $students,
            'group' => $group,
            'prayers' => StudentPrayerAttendance::PRAYERS,
            'prayerInitials' => StudentPrayerAttendance::PRAYER_INITIALS,
            'monthLabel' => Carbon::create($filters['year'], $filters['month'], 1)->format('F Y'),
            'workingDays' => StudentPrayerAttendance::prayerDaysInMonth($filters['year'], $filters['month']),
            'expectedPerStudent' => StudentPrayerAttendance::expectedPrayersInMonth($filters['year'], $filters['month']),
            'heading' => $this->groupHeading($filters),
            'months' => $this->selectableMonths(),
            'years' => $this->selectableYears(),
            ...$this->filterOptions(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* The student-wise report */
    /* ------------------------------------------------------------------ */

    /**
     * Build the student-wise report.
     *
     * Driven by the enrollments rather than by the prayer rows, which is
     * what makes a student with nothing recorded still appear - with zeroes
     * rather than not at all. The prayer table is joined on the left, so a
     * student the register never mentions still produces a row.
     *
     * The month is part of the join condition and not a where clause. Moved
     * to the where clause it would quietly turn the left join back into an
     * inner one and drop exactly the students the report exists to show.
     *
     * Everything is aggregated in SQL. Reading a class's month of prayers
     * into PHP to count them would be thousands of rows to print twenty-five
     * lines.
     *
     * @param  array<string, mixed>  $filters
     */
    private function studentReportQuery(array $filters, string $start, string $end)
    {
        $query = $this->baseEnrollmentQuery($filters, $start, $end)
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
                // Read through the enrollment the prayers hang off, so a
                // report on a past month names the class the student was in
                // then rather than where they sit today.
                'academic_classes.name as class_name',
                'sections.name as section_name',
                DB::raw('count(spa.id) as recorded'),
                DB::raw($this->statusSum(StudentPrayerAttendance::STATUS_PRESENT).' as present'),
                DB::raw($this->statusSum(StudentPrayerAttendance::STATUS_ABSENT).' as absent'),
            ])
            // Roll number first, as the paper register is kept, then the two
            // columns that are always present so the order is total.
            ->orderBy('students.roll_number')
            ->orderBy('students.registration_number')
            ->orderBy('students.full_name');

        // Ten more columns: present and absent for each of the five prayers.
        foreach ($this->prayerStatusColumns() as $alias => $expression) {
            $query->addSelect(DB::raw("{$expression} as {$alias}"));
        }

        return $query;
    }

    /**
     * The enrollments and joined prayer rows every figure is derived from.
     *
     * The track condition is the report's boundary: a school enrollment is
     * not in this set, so a Hifz + School student is counted once through
     * their madrassa placement and a school-only student not at all.
     *
     * Enrollments are selected by whether they overlap the month rather
     * than by whether they are still Active. Reporting on a past month must
     * include the placement the student held then, even if they have since
     * been promoted out of it or left.
     *
     * @param  array<string, mixed>  $filters
     */
    private function baseEnrollmentQuery(array $filters, string $start, string $end)
    {
        return DB::table('student_academic_enrollments as sae')
            ->join('students', 'students.id', '=', 'sae.student_id')
            ->join('academic_classes', 'academic_classes.id', '=', 'sae.academic_class_id')
            // Left, because a class may be run without sections.
            ->leftJoin('sections', 'sections.id', '=', 'sae.section_id')
            ->leftJoin('student_prayer_attendances as spa', function ($join) use ($start, $end) {
                $join->on('spa.student_academic_enrollment_id', '=', 'sae.id')
                    ->whereBetween('spa.attendance_date', [$start, $end]);
            })
            ->where('sae.academic_track', StudentPrayerAttendance::ACADEMIC_TRACK)
            // The placement was held at some point during the month.
            ->where('sae.start_date', '<=', $end)
            ->where(function ($query) use ($start) {
                $query->whereNull('sae.end_date')->orWhere('sae.end_date', '>=', $start);
            })
            ->when($filters['academic_session_id'], fn ($query, $id) => $query->where('sae.academic_session_id', $id))
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
     *
     * Only Present and Absent rows exist, so this counts what was actually
     * transcribed. A prayer nobody entered has no row and is therefore in
     * neither total - it is Unrecorded, which is worked out from Expected.
     */
    private function statusSum(string $status): string
    {
        return "sum(case when spa.status = '{$status}' then 1 else 0 end)";
    }

    /**
     * Build the ten per-prayer columns, keyed by their column alias.
     *
     * The prayer and status names come from the model's own constants,
     * never from a request, so there is nothing here a query string could
     * reach.
     *
     * @return array<string, string>
     */
    private function prayerStatusColumns(): array
    {
        $columns = [];

        foreach (StudentPrayerAttendance::PRAYERS as $prayer) {
            foreach (StudentPrayerAttendance::STATUSES as $status) {
                $alias = $this->prayerColumn($prayer, $status);

                $columns[$alias] = "sum(case when spa.prayer = '{$prayer}' and spa.status = '{$status}' then 1 else 0 end)";
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
     * Summarise the whole filtered group, not just the page.
     *
     * The per-student aggregate is reused as a subquery and rolled up once,
     * so the group totals cost one query however many students there are.
     *
     * The totals are computed from the students' own rows, never by
     * averaging their percentages: a student with two recorded prayers must
     * not weigh the same as one with a hundred.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function groupSummary(array $filters, string $start, string $end): array
    {
        $columns = [
            DB::raw('count(*) as enrollment_rows'),
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
            ->fromSub($this->studentReportQuery($filters, $start, $end)->reorder(), 'per_student')
            ->select($columns)
            ->first();

        $students = (int) ($rolled->students ?? 0);
        $withRecords = (int) ($rolled->students_with_records ?? 0);
        $present = (int) ($rolled->present ?? 0);
        $absent = (int) ($rolled->absent ?? 0);
        $recorded = (int) ($rolled->recorded ?? 0);

        $workingDays = StudentPrayerAttendance::prayerDaysInMonth($filters['year'], $filters['month']);

        // Counted per student, not per enrollment row: a student prays five
        // times a day however many placements they held during the month.
        $expected = $students * StudentPrayerAttendance::expectedPrayersInMonth($filters['year'], $filters['month']);

        return [
            'students' => $students,
            'students_with_records' => $withRecords,
            'students_without_records' => max(0, $students - $withRecords),
            'working_days' => $workingDays,
            'expected' => $expected,
            'recorded' => $recorded,
            // Never negative: a month entered more thoroughly than the
            // calendar allows would be a data problem, not a negative gap.
            'unrecorded' => max(0, $expected - $recorded),
            'present' => $present,
            'absent' => $absent,
            'percentage' => StudentPrayerAttendance::formatPercentage($present, $absent),
            'by_prayer' => $this->prayerBreakdown($rolled, $students, $workingDays),
        ];
    }

    /**
     * Break the group's totals down across the five prayers.
     *
     * Each prayer happens once a working day, so its expected count is the
     * students times the working days - a fifth of the month's total.
     *
     * @return array<string, array<string, mixed>>
     */
    private function prayerBreakdown(?object $rolled, int $students, int $workingDays): array
    {
        $breakdown = [];
        $expected = $students * $workingDays;

        foreach (StudentPrayerAttendance::PRAYERS as $prayer) {
            $present = (int) ($rolled->{$this->prayerColumn($prayer, StudentPrayerAttendance::STATUS_PRESENT)} ?? 0);
            $absent = (int) ($rolled->{$this->prayerColumn($prayer, StudentPrayerAttendance::STATUS_ABSENT)} ?? 0);
            $recorded = $present + $absent;

            $breakdown[$prayer] = [
                'expected' => $expected,
                'recorded' => $recorded,
                'unrecorded' => max(0, $expected - $recorded),
                'present' => $present,
                'absent' => $absent,
                'percentage' => StudentPrayerAttendance::formatPercentage($present, $absent),
            ];
        }

        return $breakdown;
    }

    /* ------------------------------------------------------------------ */
    /* Filters */
    /* ------------------------------------------------------------------ */

    /**
     * Read the report filters off the request.
     *
     * Anything absent is null rather than an empty string, so every filter
     * reads as a question about whether a choice was made. All of them are
     * AND conditions: a class from another department matches nothing
     * rather than one filter quietly winning over the other.
     *
     * The month and year always resolve to something. This is a monthly
     * report - Expected prayers cannot be counted without a month.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $today = Carbon::now();

        return [
            'academic_session_id' => $this->cleaned($request->input('academic_session_id')),
            'department_id' => $this->cleaned($request->input('department_id')),
            'academic_class_id' => $this->cleaned($request->input('academic_class_id')),
            'section_id' => $this->cleaned($request->input('section_id')),
            'month' => $this->boundedInt($request->input('month'), 1, 12, (int) $today->month),
            'year' => $this->boundedInt($request->input('year'), 2000, 2100, (int) $today->year),
            'search' => $this->cleaned($request->input('search')),
        ];
    }

    /**
     * Get the first and last day of the reported month.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: string}
     */
    private function monthBounds(array $filters): array
    {
        $start = Carbon::create($filters['year'], $filters['month'], 1)->startOfMonth();

        // The month decides its own length, so February and August need no
        // special case.
        return [$start->format('Y-m-d'), $start->copy()->endOfMonth()->format('Y-m-d')];
    }

    /**
     * Reduce a blank filter to null.
     */
    private function cleaned(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Read an integer filter, falling back when it is missing or absurd.
     */
    private function boundedInt(mixed $value, int $min, int $max, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        $number = (int) $value;

        return $number >= $min && $number <= $max ? $number : $fallback;
    }

    /**
     * Resolve the names the report heading is built from.
     *
     * Each lookup is skipped when its filter was not chosen: handing a null
     * key to find() is still a round trip that can only come back empty.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function groupHeading(array $filters): array
    {
        return [
            'session' => $filters['academic_session_id'] ? AcademicSession::find($filters['academic_session_id']) : null,
            'department' => $filters['department_id'] ? Department::find($filters['department_id']) : null,
            'academicClass' => $filters['academic_class_id'] ? AcademicClass::find($filters['academic_class_id']) : null,
            'section' => $filters['section_id'] ? Section::find($filters['section_id']) : null,
        ];
    }

    /**
     * Get the months the month selector offers, numbered.
     *
     * @return array<int, string>
     */
    private function selectableMonths(): array
    {
        $months = [];

        foreach (range(1, 12) as $month) {
            $months[$month] = Carbon::create(2000, $month, 1)->format('F');
        }

        return $months;
    }

    /**
     * Get the years the year selector offers.
     *
     * @return array<int, int>
     */
    private function selectableYears(): array
    {
        $current = (int) Carbon::now()->year;

        return range($current + 1, $current - 5);
    }

    /**
     * Get the options the filter selects are built from.
     *
     * The same shape the prayer sheet and the academic pages use, so the
     * class and section selects narrow without a round trip.
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
