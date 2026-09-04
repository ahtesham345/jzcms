<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePrayerAttendanceSheetRequest;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentPrayerAttendance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The monthly prayer attendance sheet.
 *
 * The madrassa keeps its prayer register on paper through the month and
 * transcribes it into JZCMS afterwards, so the interface is a month of
 * columns rather than one day at a time. What it writes is still one row
 * per student, day and prayer: the sheet is the way a month of those rows
 * is entered, not how they are kept.
 *
 * Madrassa only, and no track selector. The five daily prayers are the
 * madrassa's register; a school-only student has no prayer sheet to appear
 * on. The rule is applied to the enrollment rows here and re-applied to
 * every submitted row in the form request, so the browser is never what
 * decides it.
 *
 * Entirely separate from the academic attendance module. This controller
 * reads and writes student_prayer_attendances and nothing else; academic
 * attendance is untouched by anything here, and neither register is
 * consulted to fill in the other.
 *
 * The students come from student_academic_enrollments, exactly as the
 * academic sheet does. Nothing about a student's placement is read from the
 * students table, because that table cannot describe a dual-track student.
 */
class PrayerAttendanceController extends Controller
{
    /**
     * The filters that must all be chosen before a sheet can be drawn.
     *
     * No date among them: a month is opened, not a day. The section is not
     * on the list either - a class may be run without sections, and leaving
     * it unset draws the whole class.
     *
     * @var array<int, string>
     */
    private const REQUIRED_FILTERS = [
        'academic_session_id',
        'department_id',
        'academic_class_id',
    ];

    /**
     * Show the monthly prayer sheet.
     */
    public function index(Request $request)
    {
        $filters = $this->filters($request);

        // The month decides its own length, so this is where 28, 29, 30 and
        // 31 day months stop being a special case.
        $days = StudentPrayerAttendance::monthDays($filters['year'], $filters['month']);

        $data = [
            'filters' => $filters,
            'prayers' => StudentPrayerAttendance::PRAYERS,
            'prayerInitials' => StudentPrayerAttendance::PRAYER_INITIALS,
            'years' => $this->selectableYears(),
            'months' => $this->selectableMonths(),
            'monthLabel' => Carbon::create($filters['year'], $filters['month'], 1)->format('F Y'),
            'days' => $days,
            'sheet' => null,
            'group' => null,
            'summary' => null,
            'initialCells' => [],
            'cells' => [],
            'studentNames' => [],
            'dayLabels' => $this->dayLabels($days),
            ...$this->filterOptions(),
        ];

        if (! $this->isComplete($filters)) {
            return view('prayer-attendance.index', $data);
        }

        $enrollments = $this->sheetEnrollments($filters);

        // One query for the whole month rather than one per student, per day
        // or per prayer.
        $existing = StudentPrayerAttendance::forMonth(
            $enrollments->pluck('id')->all(),
            $filters['year'],
            $filters['month']
        );

        $initialCells = $this->cellState($enrollments, $existing, $days);

        return view('prayer-attendance.index', [
            ...$data,
            'sheet' => $enrollments,
            'group' => $this->groupHeading($filters),
            'summary' => $this->summary($enrollments, $filters),
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
     * Save the changed cells of the sheet.
     *
     * The whole submission is one transaction, and only the cells handed in
     * are written: prayers the administrator has not transcribed yet stay
     * unmarked rather than being filled in for them.
     */
    public function store(StorePrayerAttendanceSheetRequest $request)
    {
        $validated = $request->validated();

        $result = StudentPrayerAttendance::recordSheet($validated['prayers']);

        return redirect()
            ->route('prayer-attendance.index', array_filter([
                'academic_session_id' => $validated['academic_session_id'],
                'department_id' => $validated['department_id'],
                'academic_class_id' => $validated['academic_class_id'],
                'section_id' => $validated['section_id'] ?? null,
                'month' => $validated['month'],
                'year' => $validated['year'],
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', $this->savedMessage($result));
    }

    /**
     * Describe what a save actually did.
     *
     * @param  array{saved: int, cleared: int}  $result
     */
    private function savedMessage(array $result): string
    {
        $parts = [];

        if ($result['saved'] > 0) {
            $parts[] = $result['saved'].' '.($result['saved'] === 1 ? 'prayer' : 'prayers').' saved';
        }

        if ($result['cleared'] > 0) {
            $parts[] = $result['cleared'].' cleared';
        }

        return $parts === [] ? 'No changes to save.' : implode(', ', $parts).'.';
    }

    /* ------------------------------------------------------------------ */
    /* The sheet */
    /* ------------------------------------------------------------------ */

    /**
     * Build the state every cell of the sheet starts in.
     *
     * One entry per student, per working day, per prayer. Off days get no
     * entry at all: there is nothing to mark and nothing to submit.
     *
     * @param  Collection<int, StudentAcademicEnrollment>  $enrollments
     * @param  Collection<string, StudentPrayerAttendance>  $existing
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

                foreach (StudentPrayerAttendance::PRAYERS as $prayer) {
                    $key = StudentPrayerAttendance::cellKey($enrollment->id, $day['date'], $prayer);
                    $record = $existing->get($key);

                    $cells[$key] = [
                        'status' => $record?->status ?? '',
                        'reason' => $record?->absence_reason ?? '',
                    ];
                }
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

            $key = StudentPrayerAttendance::cellKey(
                $row['student_academic_enrollment_id'] ?? 0,
                $row['attendance_date'] ?? '',
                (string) ($row['prayer'] ?? '')
            );

            if (! array_key_exists($key, $initial)) {
                continue;
            }

            $status = (string) ($row['status'] ?? '');

            $initial[$key] = [
                // Unmarked is an instruction to clear, so it comes back as
                // the empty state the sheet draws it in.
                'status' => $status === StudentPrayerAttendance::STATUS_UNMARKED ? '' : $status,
                'reason' => (string) ($row['absence_reason'] ?? ''),
            ];
        }

        return $initial;
    }

    /**
     * Get the students the sheet covers.
     *
     * Active Madrassa enrollments only: the sheet is the register of who is
     * currently in the class, so Completed and Left placements are out, and
     * the School track is never drawn at all.
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
            ->where('student_academic_enrollments.academic_track', StudentPrayerAttendance::ACADEMIC_TRACK)
            ->where('student_academic_enrollments.academic_session_id', $filters['academic_session_id'])
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
     * Count what the loaded month has room for.
     *
     * The per-status counts are worked out in the browser from the cells it
     * is showing, so they follow the administrator's edits. What the server
     * contributes is the shape of the month: how many students, how many
     * days prayers are recorded on, and how many cells that adds up to.
     *
     * @param  Collection<int, StudentAcademicEnrollment>  $enrollments
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function summary($enrollments, array $filters): array
    {
        $prayerDays = StudentPrayerAttendance::prayerDaysInMonth($filters['year'], $filters['month']);

        return [
            'students' => $enrollments->count(),
            'prayer_days' => $prayerDays,
            'prayers_per_day' => count(StudentPrayerAttendance::PRAYERS),
            // Everything the month has room for, off days excluded.
            'total_cells' => $enrollments->count() * $prayerDays * count(StudentPrayerAttendance::PRAYERS),
        ];
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

    /* ------------------------------------------------------------------ */
    /* Filters */
    /* ------------------------------------------------------------------ */

    /**
     * Read the sheet filters off the request.
     *
     * Anything absent is null rather than an empty string, so the checks
     * below read as questions about whether a choice was made. The month
     * and year always resolve to something, because the sheet has to name a
     * month even before a group is picked.
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
     * @param  array<string, mixed>  $filters
     */
    private function isComplete(array $filters): bool
    {
        foreach (self::REQUIRED_FILTERS as $filter) {
            if ($filters[$filter] === null) {
                return false;
            }
        }

        return true;
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
     * The same shape the academic attendance and enrollment pages use, so
     * the class and section selects narrow without a round trip.
     *
     * Every department is offered, not only madrassa ones: the departments
     * table does not name a track, and the Madrassa-only rule is applied to
     * the enrollments the sheet draws rather than to the filter lists. A
     * school department simply produces an empty sheet.
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
