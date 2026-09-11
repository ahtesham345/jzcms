<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The monthly attendance report.
 *
 * A reading page over the rows the monthly entry sheet writes. It counts
 * what is on file and nothing else: a day with no record is a day nobody
 * has transcribed yet, so it is never counted as a present, never as an
 * absence, and never brought into a percentage.
 *
 * Everything is aggregated in SQL. A class of forty over a month is well
 * over a thousand attendance rows, and none of them need to reach PHP for
 * the totals to be right.
 */
class AttendanceReportController extends Controller
{
    /**
     * How many students to show per page.
     */
    private const PER_PAGE = 25;

    /**
     * How many students the export reads from the database at a time.
     */
    private const EXPORT_CHUNK = 500;

    /**
     * The value the period filter uses for "every period the track runs".
     */
    private const ALL_PERIODS = 'all';

    /**
     * The sortable columns, mapped to what they order by.
     *
     * Kept to a short list on purpose: this is a report, not a data grid.
     *
     * @var array<string, string>
     */
    private const SORTS = [
        'student' => 'students.full_name',
        'registration' => 'students.registration_number',
        'roll' => 'students.roll_number',
        'present' => 'present_count',
        'absent' => 'absent_count',
        'percentage' => 'percentage_order',
    ];

    /**
     * Display the monthly attendance report.
     */
    public function index(Request $request)
    {
        $filters = $this->filters($request);

        // The periods the report may count. Read from the track rather than
        // from the request, so a school report can never total an evening
        // row even if one somehow exists in the table.
        $periods = $this->countedPeriods($filters);

        [$start, $end] = $this->monthBounds($filters);

        $students = $this->studentRows($filters, $periods, $start, $end);

        return view('attendance.reports', [
            'filters' => $filters,
            'students' => $students,
            // Every matching student, not just the page being looked at.
            'summary' => $this->summary($filters, $periods, $start, $end),
            'groupSummary' => $this->groupSummary($filters, $periods, $start, $end),
            'periodSummary' => $this->periodSummary($filters, $periods, $start, $end),
            'academicTracks' => StudentAcademicEnrollment::attendanceTracks(),
            'availablePeriods' => StudentAttendance::periodsForTrack($filters['academic_track']),
            'allPeriodsValue' => self::ALL_PERIODS,
            'sorts' => array_keys(self::SORTS),
            'months' => $this->selectableMonths(),
            'years' => $this->selectableYears(),
            'monthLabel' => Carbon::create($filters['year'], $filters['month'], 1)->format('F Y'),
            ...$this->filterOptions(),
        ]);
    }

    /**
     * Show the printable version of the report.
     *
     * Every matching student rather than one page of them, and the same
     * summaries the screen shows. Nothing is recalculated differently for
     * paper.
     */
    public function printReport(Request $request)
    {
        $filters = $this->filters($request);
        $periods = $this->countedPeriods($filters);

        [$start, $end] = $this->monthBounds($filters);

        return view('attendance.reports-print', [
            'filters' => $filters,
            'students' => $this->studentQuery($filters, $periods, $start, $end)->get(),
            'summary' => $this->summary($filters, $periods, $start, $end),
            'groupSummary' => $this->groupSummary($filters, $periods, $start, $end),
            'periodSummary' => $this->periodSummary($filters, $periods, $start, $end),
            'availablePeriods' => StudentAttendance::periodsForTrack($filters['academic_track']),
            'allPeriodsValue' => self::ALL_PERIODS,
            'monthLabel' => Carbon::create($filters['year'], $filters['month'], 1)->format('F Y'),
        ]);
    }

    /**
     * Stream the report as a CSV.
     *
     * Every matching student, whatever the page size: an export that stopped
     * at the current page would be a different report from the one asked
     * for. The rows are read in chunks and written straight out, so a large
     * class never has to be held in memory all at once.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $periods = $this->countedPeriods($filters);

        [$start, $end] = $this->monthBounds($filters);

        $query = $this->studentQuery($filters, $periods, $start, $end);

        $filename = sprintf(
            'attendance-report-%s-%s-%s.csv',
            strtolower($filters['academic_track']),
            $filters['year'],
            str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT)
        );

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // A byte order mark, so Excel opens the Urdu and Arabic class
            // names as UTF-8 rather than as mojibake.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
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
            ]);

            // Chunked by id: the query count stays flat however many
            // students match.
            foreach ($query->lazyById(self::EXPORT_CHUNK, 'student_academic_enrollments.id') as $enrollment) {
                $present = (int) $enrollment->present_count;
                $absent = (int) $enrollment->absent_count;

                fputcsv($handle, [
                    $enrollment->student?->full_name,
                    $enrollment->student?->registration_number,
                    $enrollment->student?->roll_number,
                    $enrollment->academicSession?->name,
                    $enrollment->department?->name,
                    $enrollment->academicClass?->name,
                    $enrollment->section?->name ?? 'No section',
                    $enrollment->academic_track,
                    $present,
                    $absent,
                    $present + $absent,
                    // N/A rather than a zero, exactly as the screen shows
                    // it: nothing recorded is not nought percent attendance.
                    StudentAttendance::attendancePercentage($present, $absent) === null
                        ? 'N/A'
                        : number_format(StudentAttendance::attendancePercentage($present, $absent), 2),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Read the report filters off the request.
     *
     * Month and year always resolve to something, because a report has to
     * name a month. The track has no "all" option: school and madrassa run
     * different periods, and one number covering both would mean nothing.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $today = Carbon::now();

        $track = $request->input('academic_track');
        $track = in_array($track, StudentAcademicEnrollment::attendanceTracks(), true)
            ? $track
            : StudentAcademicEnrollment::attendanceTracks()[0];

        $sort = $request->input('sort');
        $direction = strtolower((string) $request->input('direction'));

        return [
            'academic_session_id' => $this->cleaned($request->input('academic_session_id')),
            'academic_track' => $track,
            'department_id' => $this->cleaned($request->input('department_id')),
            'academic_class_id' => $this->cleaned($request->input('academic_class_id')),
            'section_id' => $this->cleaned($request->input('section_id')),
            'month' => $this->boundedInt($request->input('month'), 1, 12, (int) $today->month),
            'year' => $this->boundedInt($request->input('year'), 2000, 2100, (int) $today->year),
            'attendance_period' => $this->period($request, $track),
            'search' => $this->cleaned($request->input('search')),
            'sort' => array_key_exists($sort, self::SORTS) ? $sort : 'student',
            'direction' => $direction === 'desc' ? 'desc' : 'asc',
        ];
    }

    /**
     * Resolve the period filter for a track.
     *
     * School runs one period, so there is nothing to choose and the filter
     * is pinned to it: an Evening left over from a madrassa report must not
     * survive into a school one.
     */
    private function period(Request $request, string $track): string
    {
        $available = StudentAttendance::periodsForTrack($track);

        if (count($available) === 1) {
            return $available[0];
        }

        $period = $request->input('attendance_period');

        return in_array($period, $available, true) ? $period : self::ALL_PERIODS;
    }

    /**
     * The periods this report counts.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    private function countedPeriods(array $filters): array
    {
        $available = StudentAttendance::periodsForTrack($filters['academic_track']);

        return $filters['attendance_period'] === self::ALL_PERIODS
            ? $available
            : [$filters['attendance_period']];
    }

    /**
     * The first and last day of the reported month.
     *
     * The month decides its own length, so February and August need no
     * special case here.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: string}
     */
    private function monthBounds(array $filters): array
    {
        $start = Carbon::create($filters['year'], $filters['month'], 1)->startOfMonth();

        return [$start->format('Y-m-d'), $start->copy()->endOfMonth()->format('Y-m-d')];
    }

    /**
     * Build the enrollment query every part of the report is drawn from.
     *
     * The academic group is matched on the enrollment row, so the filters
     * combine with AND: a class from another department matches nothing
     * rather than one filter quietly overriding the other. That is what
     * enforces the dependent relationships server-side; the narrowing in
     * the browser only decides what is offered.
     *
     * Students are joined for searching and ordering, never for placement:
     * the placement always comes from the enrollment, so a promoted
     * student's old month still reports under the class it happened in.
     *
     * @param  array<string, mixed>  $filters
     */
    private function baseQuery(array $filters)
    {
        return StudentAcademicEnrollment::query()
            ->join('students', 'students.id', '=', 'student_academic_enrollments.student_id')
            ->where('student_academic_enrollments.academic_track', $filters['academic_track'])
            ->when($filters['academic_session_id'], fn ($query, $value) => $query->where('student_academic_enrollments.academic_session_id', $value))
            ->when($filters['department_id'], fn ($query, $value) => $query->where('student_academic_enrollments.department_id', $value))
            ->when($filters['academic_class_id'], fn ($query, $value) => $query->where('student_academic_enrollments.academic_class_id', $value))
            ->when($filters['section_id'], fn ($query, $value) => $query->where('student_academic_enrollments.section_id', $value))
            ->when($filters['search'], function ($query, $search) {
                // Narrows the same set the other filters produce rather
                // than reaching outside it.
                $query->where(function ($student) use ($search) {
                    $student->where('students.full_name', 'like', "%{$search}%")
                        ->orWhere('students.registration_number', 'like', "%{$search}%")
                        ->orWhere('students.roll_number', 'like', "%{$search}%");
                });
            });
    }

    /**
     * Count one student's records, as a subquery on the enrollment row.
     *
     * Correlated rather than grouped: the counts then travel with the
     * paginated row, which keeps the page to a single query and lets the
     * table be sorted by them.
     *
     * @param  array<int, string>  $periods
     */
    private function countSub(string $status, array $periods, string $start, string $end)
    {
        return StudentAttendance::query()
            ->selectRaw('count(*)')
            ->whereColumn('student_attendances.student_academic_enrollment_id', 'student_academic_enrollments.id')
            ->whereBetween('student_attendances.attendance_date', [$start, $end])
            ->whereIn('student_attendances.attendance_period', $periods)
            ->where('student_attendances.status', $status);
    }

    /**
     * Get the paginated student rows.
     *
     * A student appears whether or not any attendance has been entered for
     * them: a row of zeroes is how the report shows whose paper register is
     * still waiting to be transcribed.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $periods
     */
    private function studentRows(array $filters, array $periods, string $start, string $end)
    {
        return $this->studentQuery($filters, $periods, $start, $end)
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Build the ordered student query the report is drawn from.
     *
     * Shared by the page, the printout and the export, so all three count
     * the same rows in the same order. Only the page is paginated: a
     * printout or a CSV that stopped at twenty-five students would be a
     * different report from the one on screen.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $periods
     */
    private function studentQuery(array $filters, array $periods, string $start, string $end)
    {
        $query = $this->baseQuery($filters)
            ->select('student_academic_enrollments.*')
            ->selectSub($this->countSub(StudentAttendance::STATUS_PRESENT, $periods, $start, $end), 'present_count')
            ->selectSub($this->countSub(StudentAttendance::STATUS_ABSENT, $periods, $start, $end), 'absent_count')
            // Everything the row displays, loaded once for the page.
            ->with(['student', 'academicSession', 'department', 'academicClass', 'section']);

        $column = self::SORTS[$filters['sort']];

        if ($column === 'percentage_order') {
            // Nothing recorded sorts last either way rather than as a zero.
            $query->orderByRaw(
                'case when (present_count + absent_count) = 0 then null else present_count * 1.0 / (present_count + absent_count) end '
                .($filters['direction'] === 'desc' ? 'desc' : 'asc')
            );
        } else {
            $query->orderBy($column, $filters['direction']);
        }

        return $query
            // Always total, so paging never repeats or drops a student.
            ->orderBy('students.registration_number')
            ->orderBy('student_academic_enrollments.id');
    }

    /**
     * Total the whole filtered set.
     *
     * Deliberately not built from the paginated rows: page one of a hundred
     * students must still report all hundred.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $periods
     * @return array<string, mixed>
     */
    private function summary(array $filters, array $periods, string $start, string $end): array
    {
        $counts = StudentAttendance::query()
            ->whereIn(
                'student_academic_enrollment_id',
                $this->baseQuery($filters)->select('student_academic_enrollments.id')
            )
            ->whereBetween('attendance_date', [$start, $end])
            ->whereIn('attendance_period', $periods)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $present = (int) $counts->get(StudentAttendance::STATUS_PRESENT, 0);
        $absent = (int) $counts->get(StudentAttendance::STATUS_ABSENT, 0);

        return [
            'students' => $this->baseQuery($filters)->count(),
            'present' => $present,
            'absent' => $absent,
            'recorded' => $present + $absent,
            'percentage' => StudentAttendance::attendancePercentage($present, $absent),
        ];
    }

    /**
     * Total each class and section in the filtered set.
     *
     * A left join rather than a subquery, because a group is summed rather
     * than counted per row. Groups with no attendance at all still appear,
     * with their student count and nothing else.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $periods
     * @return array<int, array<string, mixed>>
     */
    private function groupSummary(array $filters, array $periods, string $start, string $end): array
    {
        $rows = $this->baseQuery($filters)
            ->leftJoin('student_attendances', function ($join) use ($periods, $start, $end) {
                $join->on('student_attendances.student_academic_enrollment_id', '=', 'student_academic_enrollments.id')
                    ->whereBetween('student_attendances.attendance_date', [$start, $end])
                    ->whereIn('student_attendances.attendance_period', $periods);
            })
            ->groupBy('student_academic_enrollments.academic_class_id', 'student_academic_enrollments.section_id')
            ->selectRaw(
                'student_academic_enrollments.academic_class_id as academic_class_id,'
                .' student_academic_enrollments.section_id as section_id,'
                .' count(distinct student_academic_enrollments.id) as students,'
                .' sum(case when student_attendances.status = ? then 1 else 0 end) as present,'
                .' sum(case when student_attendances.status = ? then 1 else 0 end) as absent',
                [StudentAttendance::STATUS_PRESENT, StudentAttendance::STATUS_ABSENT]
            )
            ->get();

        // Named in two lookups rather than one per group.
        $classes = AcademicClass::whereIn('id', $rows->pluck('academic_class_id')->filter())->pluck('name', 'id');
        $sections = Section::whereIn('id', $rows->pluck('section_id')->filter())->pluck('name', 'id');

        return $rows
            ->map(function ($row) use ($classes, $sections) {
                $present = (int) $row->present;
                $absent = (int) $row->absent;

                return [
                    'class' => $classes[$row->academic_class_id] ?? '—',
                    'section' => $row->section_id ? ($sections[$row->section_id] ?? '—') : 'No section',
                    'students' => (int) $row->students,
                    'present' => $present,
                    'absent' => $absent,
                    'recorded' => $present + $absent,
                    'percentage' => StudentAttendance::attendancePercentage($present, $absent),
                ];
            })
            ->sortBy([['class', 'asc'], ['section', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Total each period the report covers.
     *
     * Only the periods being counted get a row, so a school report shows
     * Morning alone rather than two empty rows for periods school does not
     * sit. A madrassa period with nothing entered still appears, at zero,
     * because that absence of data is the point of looking.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $periods
     * @return array<int, array<string, mixed>>
     */
    private function periodSummary(array $filters, array $periods, string $start, string $end): array
    {
        $rows = StudentAttendance::query()
            ->whereIn(
                'student_academic_enrollment_id',
                $this->baseQuery($filters)->select('student_academic_enrollments.id')
            )
            ->whereBetween('attendance_date', [$start, $end])
            ->whereIn('attendance_period', $periods)
            ->groupBy('attendance_period', 'status')
            ->selectRaw('attendance_period, status, count(*) as aggregate')
            ->get();

        return collect($periods)
            ->map(function ($period) use ($rows) {
                $for = $rows->where('attendance_period', $period);

                $present = (int) ($for->firstWhere('status', StudentAttendance::STATUS_PRESENT)->aggregate ?? 0);
                $absent = (int) ($for->firstWhere('status', StudentAttendance::STATUS_ABSENT)->aggregate ?? 0);

                return [
                    'period' => $period,
                    'present' => $present,
                    'absent' => $absent,
                    'recorded' => $present + $absent,
                    'percentage' => StudentAttendance::attendancePercentage($present, $absent),
                ];
            })
            ->all();
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
     * Every session and every department, active or not: a report on a past
     * month has to stay readable after the class that ran it is retired.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'academicSessions' => AcademicSession::orderByDesc('start_date')->get(),
            'departments' => Department::orderBy('name')->get(),
            'classesByDepartment' => AcademicClass::orderBy('name')
                ->get(['id', 'name', 'department_id'])
                ->groupBy('department_id')
                ->map(fn ($classes) => $classes->map->only(['id', 'name'])->values()),
            'sectionsByClass' => Section::orderBy('name')
                ->get(['id', 'name', 'academic_class_id'])
                ->groupBy('academic_class_id')
                ->map(fn ($sections) => $sections->map->only(['id', 'name'])->values()),
        ];
    }
}
