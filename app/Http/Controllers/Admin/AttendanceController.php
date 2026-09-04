<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttendanceSheetRequest;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentAttendance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The monthly attendance sheet.
 *
 * Attendance is taken on paper through the month and transcribed into JZCMS
 * afterwards, so the interface is a month of columns rather than one day at
 * a time. What it writes is still one row per student, day and period: the
 * sheet is the way a month of those rows is entered, not how they are kept.
 *
 * The students come from student_academic_enrollments, exactly as the
 * academic group page does. Nothing about a student's placement is read
 * from the students table, because that table cannot describe a dual-track
 * student.
 */
class AttendanceController extends Controller
{
    /**
     * The filters that must all be chosen before a sheet can be drawn.
     *
     * No date among them: a month is opened, not a day.
     *
     * @var array<int, string>
     */
    private const REQUIRED_FILTERS = [
        'academic_session_id',
        'academic_track',
        'department_id',
        'academic_class_id',
    ];

    /**
     * Show the monthly attendance sheet.
     */
    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $track = $filters['academic_track'];

        // Driven from the track on the server rather than in the browser, so
        // the sheet can never offer a period the save would reject.
        $availablePeriods = StudentAttendance::periodsForTrack($track);

        // The period is a tab on the sheet, not something to be chosen
        // before it opens, so it falls back to the first the track runs.
        if (! in_array($filters['attendance_period'], $availablePeriods, true)) {
            $filters['attendance_period'] = $availablePeriods[0] ?? null;
        }

        // The month decides its own length, so this is where 28, 29, 30 and
        // 31 day months stop being a special case.
        $days = StudentAttendance::monthDays($filters['year'], $filters['month']);

        $data = [
            'filters' => $filters,
            'availablePeriods' => $availablePeriods,
            'academicTracks' => StudentAcademicEnrollment::ACADEMIC_TRACKS,
            'years' => $this->selectableYears(),
            'months' => $this->selectableMonths(),
            'monthLabel' => Carbon::create($filters['year'], $filters['month'], 1)->format('F Y'),
            'days' => $days,
            'sheet' => null,
            'group' => null,
            'existingAttendance' => collect(),
            'summary' => null,
            'initialCells' => [],
            'cells' => [],
            'studentNames' => [],
            'dayLabels' => $this->dayLabels($days),
            ...$this->filterOptions(),
        ];

        if (! $this->isComplete($filters)) {
            return view('attendance.index', $data);
        }

        $enrollments = $this->sheetEnrollments($filters);

        // One query for the whole month rather than one per student or per
        // day.
        $existing = StudentAttendance::forMonth(
            $enrollments->pluck('id')->all(),
            $filters['year'],
            $filters['month'],
            $filters['attendance_period']
        );

        $initialCells = $this->cellState($enrollments, $existing, $days);

        return view('attendance.index', [
            ...$data,
            'sheet' => $enrollments,
            'existingAttendance' => $existing,
            'group' => $this->groupHeading($filters),
            'summary' => $this->summary($enrollments, $existing, $filters),
            // What the database holds; Reset returns the sheet to this.
            'initialCells' => $initialCells,
            // What the sheet shows, which after a rejected save is the work
            // the administrator had already typed in.
            'cells' => $this->withPendingCells($initialCells),
            'studentNames' => $enrollments
                ->mapWithKeys(fn ($enrollment) => [$enrollment->id => $enrollment->student->full_name])
                ->all(),
        ]);
    }

    /**
     * Build the state every cell of the sheet starts in.
     *
     * One entry per student per teaching day. Off days get no entry at all:
     * there is nothing to mark and nothing to submit.
     *
     * @param  Collection<int, StudentAcademicEnrollment>  $enrollments
     * @param  Collection<string, StudentAttendance>  $existing
     * @param  array<int, array<string, mixed>>  $days
     * @return array<string, array{status: string, reason: string}>
     */
    private function cellState($enrollments, $existing, array $days): array
    {
        $cells = [];

        foreach ($enrollments as $enrollment) {
            foreach ($days as $day) {
                if ($day['is_off_day']) {
                    continue;
                }

                $key = StudentAttendance::cellKey($enrollment->id, $day['date']);
                $record = $existing->get($key);

                $cells[$key] = [
                    'status' => $record?->status ?? '',
                    'reason' => $record?->absence_reason ?? '',
                ];
            }
        }

        return $cells;
    }

    /**
     * Lay a rejected submission back over the loaded cells.
     *
     * The sheet is submitted as one JSON field, so a validation failure
     * would otherwise throw away everything typed since the month was
     * opened. Only cells that belong to this sheet are restored.
     *
     * @param  array<string, array{status: string, reason: string}>  $initial
     * @return array<string, array{status: string, reason: string}>
     */
    private function withPendingCells(array $initial): array
    {
        $submitted = json_decode((string) old('sheet', '[]'), true);

        if (! is_array($submitted)) {
            return $initial;
        }

        foreach ($submitted as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = StudentAttendance::cellKey(
                $row['student_academic_enrollment_id'] ?? 0,
                $row['attendance_date'] ?? ''
            );

            if (! array_key_exists($key, $initial)) {
                continue;
            }

            $initial[$key] = [
                'status' => (string) ($row['status'] ?? ''),
                'reason' => (string) ($row['absence_reason'] ?? ''),
            ];
        }

        return $initial;
    }

    /**
     * Label every day of the month for the reason dialog.
     *
     * @param  array<int, array<string, mixed>>  $days
     * @return array<string, string>
     */
    private function dayLabels(array $days): array
    {
        $labels = [];

        foreach ($days as $day) {
            $labels[$day['date']] = Carbon::parse($day['date'])->format('j F Y');
        }

        return $labels;
    }

    /**
     * Show the printable version of the month.
     *
     * The same students, month and cells the sheet is showing, laid out as
     * the paper register it came from. Nothing is written and nothing is
     * recalculated: this is the sheet, on paper.
     */
    public function printSheet(Request $request)
    {
        $filters = $this->filters($request);

        $availablePeriods = StudentAttendance::periodsForTrack($filters['academic_track']);

        // The period is read from the track, so a school register can never
        // be printed with an evening column heading.
        if (! in_array($filters['attendance_period'], $availablePeriods, true)) {
            $filters['attendance_period'] = $availablePeriods[0] ?? null;
        }

        // Nothing to print until a group has been chosen. Back to the sheet
        // rather than an empty page.
        if (! $this->isComplete($filters)) {
            return redirect()
                ->route('attendance.index', array_filter($filters, fn ($value) => $value !== null && $value !== ''))
                ->with('error', 'Choose an academic session, track, department and class before printing the sheet.');
        }

        $days = StudentAttendance::monthDays($filters['year'], $filters['month']);
        $enrollments = $this->sheetEnrollments($filters);

        $existing = StudentAttendance::forMonth(
            $enrollments->pluck('id')->all(),
            $filters['year'],
            $filters['month'],
            $filters['attendance_period']
        );

        return view('attendance.print', [
            'filters' => $filters,
            'days' => $days,
            'sheet' => $enrollments,
            'group' => $this->groupHeading($filters),
            'monthLabel' => Carbon::create($filters['year'], $filters['month'], 1)->format('F Y'),
            // The same cell state the sheet draws from, so the printout and
            // the screen can never disagree.
            'cells' => $this->cellState($enrollments, $existing, $days),
        ]);
    }

    /**
     * Save the marked cells of the sheet.
     *
     * The whole submission is one transaction, and only the cells handed in
     * are written: days the administrator has not transcribed yet stay
     * unmarked rather than being filled in for them.
     */
    public function store(StoreAttendanceSheetRequest $request)
    {
        $validated = $request->validated();

        $saved = StudentAttendance::recordSheet(
            $validated['attendance_period'],
            $validated['attendance']
        );

        return redirect()
            ->route('attendance.index', array_filter([
                'academic_session_id' => $validated['academic_session_id'],
                'academic_track' => $validated['academic_track'],
                'department_id' => $validated['department_id'],
                'academic_class_id' => $validated['academic_class_id'],
                'section_id' => $validated['section_id'] ?? null,
                'month' => $validated['month'],
                'year' => $validated['year'],
                'attendance_period' => $validated['attendance_period'],
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', $saved === 0
                ? 'No changes to save.'
                : "Attendance saved for {$saved} ".($saved === 1 ? 'entry' : 'entries').'.');
    }

    /**
     * Read the sheet filters off the request.
     *
     * Anything absent is null rather than an empty string, so the checks
     * below read as questions about whether a choice was made. The month
     * and year always resolve to something, because the sheet has to name
     * a month even before a group is picked.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $track = $request->input('academic_track');
        $today = Carbon::now();

        return [
            'academic_session_id' => $this->cleaned($request->input('academic_session_id')),
            'academic_track' => in_array($track, StudentAcademicEnrollment::ACADEMIC_TRACKS, true) ? $track : null,
            'department_id' => $this->cleaned($request->input('department_id')),
            'academic_class_id' => $this->cleaned($request->input('academic_class_id')),
            'section_id' => $this->cleaned($request->input('section_id')),
            'month' => $this->boundedInt($request->input('month'), 1, 12, (int) $today->month),
            'year' => $this->boundedInt($request->input('year'), 2000, 2100, (int) $today->year),
            'attendance_period' => $this->cleaned($request->input('attendance_period')),
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
     * Determine whether enough has been chosen to draw a sheet.
     *
     * The section is not on the list: a class may be run without sections,
     * and leaving it unset draws the whole class.
     *
     * @param  array<string, mixed>  $filters
     */
    private function isComplete(array $filters): bool
    {
        foreach (self::REQUIRED_FILTERS as $filter) {
            if ($filters[$filter] === null) {
                return false;
            }
        }

        return $filters['attendance_period'] !== null;
    }

    /**
     * Get the students the sheet covers.
     *
     * Active enrollments only: the sheet is the register of who is currently
     * in the class, so Completed and Left placements are out.
     *
     * The students are joined for ordering and eager loaded for display, so
     * the sheet costs a fixed number of queries however many students it
     * holds.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, StudentAcademicEnrollment>
     */
    private function sheetEnrollments(array $filters)
    {
        return StudentAcademicEnrollment::query()
            ->with(['student', 'section'])
            // Qualified throughout: the students table is joined below for
            // ordering, and it carries columns of the same name.
            ->where('student_academic_enrollments.academic_session_id', $filters['academic_session_id'])
            ->where('student_academic_enrollments.academic_track', $filters['academic_track'])
            ->where('student_academic_enrollments.department_id', $filters['department_id'])
            ->where('student_academic_enrollments.academic_class_id', $filters['academic_class_id'])
            ->when($filters['section_id'], fn ($query, $section) => $query->where('student_academic_enrollments.section_id', $section))
            ->where('student_academic_enrollments.status', 'Active')
            ->join('students', 'students.id', '=', 'student_academic_enrollments.student_id')
            // Roll number first, as the paper register is kept, then the two
            // columns that are always present so the order is total.
            ->orderBy('students.roll_number')
            ->orderBy('students.registration_number')
            ->orderBy('students.full_name')
            ->select('student_academic_enrollments.*')
            ->get();
    }

    /**
     * Count what the loaded month holds for the selected period.
     *
     * Off days are never counted: they are not attendance that is missing,
     * they are days on which nobody sits.
     *
     * @param  Collection<int, StudentAcademicEnrollment>  $enrollments
     * @param  Collection<string, StudentAttendance>  $existing
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function summary($enrollments, $existing, array $filters): array
    {
        $teachingDays = StudentAttendance::teachingDaysInMonth($filters['year'], $filters['month']);

        $present = $existing->where('status', StudentAttendance::STATUS_PRESENT)->count();
        $absent = $existing->where('status', StudentAttendance::STATUS_ABSENT)->count();

        return [
            'students' => $enrollments->count(),
            'teaching_days' => $teachingDays,
            'present' => $present,
            'absent' => $absent,
            // Everything the month has room for that has not been entered.
            'unmarked' => max(0, ($enrollments->count() * $teachingDays) - $present - $absent),
        ];
    }

    /**
     * Resolve the names the sheet heading is built from.
     *
     * Looked up once for the heading, never per row.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function groupHeading(array $filters): array
    {
        return [
            'session' => AcademicSession::find($filters['academic_session_id']),
            'track' => $filters['academic_track'],
            'department' => Department::find($filters['department_id']),
            'academicClass' => AcademicClass::find($filters['academic_class_id']),
            'section' => $filters['section_id'] ? Section::find($filters['section_id']) : null,
        ];
    }

    /**
     * Get the years the month selector offers.
     *
     * Past years reach back far enough to transcribe old registers; one year
     * ahead covers a session that runs into the next calendar year.
     *
     * @return array<int, int>
     */
    private function selectableYears(): array
    {
        $current = (int) Carbon::now()->year;

        return range($current + 1, $current - 5);
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
     * Get the options the filter selects are built from.
     *
     * The same shape the enrollment and academic pages use, so the class and
     * section selects narrow without a round trip.
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
