<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentPrayerAttendance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * One student's prayer attendance history.
 *
 * A viewing page only. Prayers are entered and corrected on the monthly
 * prayer sheet, which is the module's only writer; nothing here creates,
 * updates or deletes a row, and drawing a month has never written one. A
 * day with no record is a day nobody has transcribed yet, and it is shown
 * as unmarked rather than given a status.
 *
 * The student comes from the route and every record shown is reached
 * through that student's own Madrassa enrollments. A filter can therefore
 * narrow the history but never widen it past the student it belongs to, and
 * never past the Madrassa track: a Hifz + School student's school
 * enrollment is not a row this page can reach, whatever the query string
 * says.
 *
 * Everything about a record's placement - session, department, class,
 * section - is read back through the enrollment it was written against, so
 * a prayer recorded in Nazra / Section A still reads that way after the
 * student is promoted to Hifz / Section B.
 */
class StudentPrayerAttendanceHistoryController extends Controller
{
    /**
     * How many records to show per page.
     */
    private const PER_PAGE = 25;

    /**
     * Display the student's prayer attendance history.
     */
    public function index(Request $request, Student $student)
    {
        // Every madrassa enrollment the student has ever held, with the
        // master data the table names. Being promoted does not start the
        // history over, and the old rows are what keep each record showing
        // the placement it was made under.
        $enrollments = $this->madrassaEnrollments($student);

        $filters = $this->filters($request, $enrollments);

        // The ids the whole page is bounded by. Derived from the student,
        // never from the request, which is what makes another student's
        // records unreachable rather than merely unlinked.
        $enrollmentIds = $this->filteredEnrollmentIds($enrollments, $filters);

        $records = $this->query($enrollmentIds, $filters)
            // Eager loaded: every row renders the placement it was recorded
            // under. Without this the page would cost four queries a row.
            ->with([
                'studentAcademicEnrollment.academicSession',
                'studentAcademicEnrollment.department',
                'studentAcademicEnrollment.academicClass',
                'studentAcademicEnrollment.section',
            ])
            ->inPrayerOrder()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // The month the grid draws. Resolved rather than required: with no
        // month chosen it falls to the most recent month that actually has
        // records, and to today only when there are none at all.
        [$gridYear, $gridMonth] = $this->gridMonth($enrollmentIds, $filters);

        $current = $student->activeEnrollmentForTrack(StudentPrayerAttendance::ACADEMIC_TRACK);

        return view('students.prayer-attendance', [
            'student' => $student,
            'filters' => $filters,
            'records' => $records,
            'prayers' => StudentPrayerAttendance::PRAYERS,
            'prayerInitials' => StudentPrayerAttendance::PRAYER_INITIALS,
            // Only the sessions this student was actually enrolled in on the
            // madrassa side. Offering every session in the institution would
            // list years the student was never here for, and would suggest a
            // filter that can only ever return nothing.
            'academicSessions' => $this->sessionsFor($enrollments),
            'currentEnrollment' => $current?->loadMissing(['academicSession', 'department', 'academicClass', 'section']),
            'hasMadrassaEnrollment' => $enrollments->isNotEmpty(),
            'enrollmentCount' => $enrollments->count(),
            'summary' => $this->summary($enrollmentIds, $filters),
            'grid' => $this->monthlyGrid($enrollmentIds, $gridYear, $gridMonth),
            'gridMonthLabel' => Carbon::create($gridYear, $gridMonth, 1)->format('F Y'),
            'gridIsDefaulted' => $filters['month'] === null && $filters['year'] === null,
            'months' => $this->selectableMonths(),
            'years' => $this->selectableYears(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* What the page may read */
    /* ------------------------------------------------------------------ */

    /**
     * Get the madrassa enrollments this student has ever held.
     *
     * The track condition is the security boundary of the whole page: a
     * school enrollment is not in this list, so nothing downstream can
     * reach one however the request is written.
     *
     * @return Collection<int, StudentAcademicEnrollment>
     */
    private function madrassaEnrollments(Student $student)
    {
        return $student->academicEnrollments()
            ->where('academic_track', StudentPrayerAttendance::ACADEMIC_TRACK)
            ->with(['academicSession', 'department', 'academicClass', 'section'])
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Narrow the student's enrollments by the session filter.
     *
     * The filter picks from this student's own enrollments, so naming
     * another student's session simply matches none of them rather than
     * reaching across.
     *
     * @param  Collection<int, StudentAcademicEnrollment>  $enrollments
     * @param  array<string, mixed>  $filters
     * @return array<int, int>
     */
    private function filteredEnrollmentIds($enrollments, array $filters): array
    {
        return $enrollments
            ->when(
                $filters['academic_session_id'] !== null,
                fn ($rows) => $rows->where('academic_session_id', (int) $filters['academic_session_id'])
            )
            ->pluck('id')
            ->all();
    }

    /**
     * Build the record query from the filters.
     *
     * The enrollment ids come first and are not optional: they are what
     * bounds the page to this student. Everything else only narrows.
     *
     * @param  array<int, int>  $enrollmentIds
     * @param  array<string, mixed>  $filters
     */
    private function query(array $enrollmentIds, array $filters)
    {
        return StudentPrayerAttendance::query()
            ->whereIn('student_academic_enrollment_id', $enrollmentIds)
            ->when($filters['year'], fn ($query, $year) => $query->whereYear('attendance_date', $year))
            ->when($filters['month'], fn ($query, $month) => $query->whereMonth('attendance_date', $month))
            ->when($filters['prayer'], fn ($query, $prayer) => $query->where('prayer', $prayer));
    }

    /* ------------------------------------------------------------------ */
    /* The summary */
    /* ------------------------------------------------------------------ */

    /**
     * Count what the selected period holds, per prayer and overall.
     *
     * One grouped query for all ten figures rather than ten counts, and
     * nothing is read into PHP that is not a total.
     *
     * The prayer filter is deliberately not applied. The point of this
     * panel is the breakdown across the five prayers; narrowing it to one
     * would show the other four as zero, which reads as "no Zuhr records
     * exist" rather than "Zuhr is filtered out of the table below".
     *
     * Weekends need no exclusion here: no row exists for a Saturday or
     * Sunday, so they cannot be counted as anything. Unmarked prayers are
     * likewise absent rather than counted, which is what keeps a day nobody
     * has transcribed from becoming a judgement about the student.
     *
     * @param  array<int, int>  $enrollmentIds
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function summary(array $enrollmentIds, array $filters): array
    {
        $rows = $this->query($enrollmentIds, ['prayer' => null] + $filters)
            ->reorder()
            ->selectRaw('prayer, status, count(*) as total')
            ->groupBy('prayer', 'status')
            ->get();

        $byPrayer = [];
        $present = 0;
        $absent = 0;

        foreach (StudentPrayerAttendance::PRAYERS as $prayer) {
            $prayerPresent = (int) $rows->firstWhere(
                fn ($row) => $row->prayer === $prayer && $row->status === StudentPrayerAttendance::STATUS_PRESENT
            )?->total;

            $prayerAbsent = (int) $rows->firstWhere(
                fn ($row) => $row->prayer === $prayer && $row->status === StudentPrayerAttendance::STATUS_ABSENT
            )?->total;

            $byPrayer[$prayer] = [
                'present' => $prayerPresent,
                'absent' => $prayerAbsent,
                'total' => $prayerPresent + $prayerAbsent,
            ];

            $present += $prayerPresent;
            $absent += $prayerAbsent;
        }

        return [
            'total' => $present + $absent,
            'present' => $present,
            'absent' => $absent,
            'by_prayer' => $byPrayer,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* The monthly grid */
    /* ------------------------------------------------------------------ */

    /**
     * Work out which month the at-a-glance grid should draw.
     *
     * A chosen month and year win. Otherwise the most recent month that
     * actually holds a record, which is almost always the one the
     * administrator has just finished transcribing. Only when there are no
     * records at all does it fall back to today.
     *
     * @param  array<int, int>  $enrollmentIds
     * @param  array<string, mixed>  $filters
     * @return array{0: int, 1: int}
     */
    private function gridMonth(array $enrollmentIds, array $filters): array
    {
        $now = Carbon::now();

        if ($filters['year'] !== null && $filters['month'] !== null) {
            return [(int) $filters['year'], (int) $filters['month']];
        }

        // Read under the same filters the table uses, so narrowing to a
        // year lands the grid on the latest month inside that year.
        $latest = $this->query($enrollmentIds, ['prayer' => null] + $filters)
            ->reorder()
            ->max('attendance_date');

        $latest = $latest === null ? null : Carbon::parse($latest);

        return [
            (int) ($filters['year'] ?? $latest?->year ?? $now->year),
            (int) ($filters['month'] ?? $latest?->month ?? $now->month),
        ];
    }

    /**
     * Build the month at a glance: one row per day, five prayers across.
     *
     * Reading only. A cell is Present or Absent when a record says so, OFF
     * on a Saturday or Sunday, and unmarked otherwise - and unmarked means
     * exactly that no row exists, never that the student was away. Drawing
     * this grid writes nothing.
     *
     * The month's records are loaded in one query and mapped onto the
     * calendar, rather than asked for a day or a prayer at a time.
     *
     * The prayer filter is not applied: the grid is the whole month across
     * all five prayers, and hiding four of them would make them look
     * unmarked when they are merely filtered.
     *
     * @param  array<int, int>  $enrollmentIds
     * @return array<int, array<string, mixed>>
     */
    private function monthlyGrid(array $enrollmentIds, int $year, int $month): array
    {
        $days = StudentPrayerAttendance::monthDays($year, $month);

        // Re-keyed by day and prayer once, so each of the month's cells is
        // a lookup rather than a scan of the month's records.
        $byDayAndPrayer = [];

        foreach (StudentPrayerAttendance::forMonth($enrollmentIds, $year, $month) as $record) {
            $key = $record->attendance_date->format('Y-m-d').'|'.$record->prayer;

            // A student who was promoted mid-month can hold two overlapping
            // placements, so the first one drawn stands rather than leaving
            // it to whichever the database returned last.
            $byDayAndPrayer[$key] ??= $record;
        }

        $grid = [];

        foreach ($days as $day) {
            $cells = [];

            foreach (StudentPrayerAttendance::PRAYERS as $prayer) {
                if ($day['is_off_day']) {
                    // Never a status. A weekend is not an absence, and the
                    // module never writes a row for one.
                    $cells[$prayer] = null;

                    continue;
                }

                $record = $byDayAndPrayer[$day['date'].'|'.$prayer] ?? null;

                $cells[$prayer] = [
                    'status' => $record?->status,
                    'reason' => $record?->absence_reason,
                ];
            }

            $grid[] = [
                'date' => $day['date'],
                'day' => $day['day'],
                'weekday' => $day['weekday'],
                'is_off_day' => $day['is_off_day'],
                'label' => Carbon::parse($day['date'])->format('j M'),
                'cells' => $cells,
            ];
        }

        return $grid;
    }

    /* ------------------------------------------------------------------ */
    /* Filters */
    /* ------------------------------------------------------------------ */

    /**
     * Read the history filters off the request.
     *
     * Anything absent or unrecognised is null rather than an empty string,
     * so a hand-edited prayer or a session the student never sat in narrows
     * nothing instead of narrowing to nothing.
     *
     * @param  Collection<int, StudentAcademicEnrollment>  $enrollments
     * @return array<string, mixed>
     */
    private function filters(Request $request, $enrollments): array
    {
        $prayer = $request->input('prayer');
        $session = $request->input('academic_session_id');

        // Only sessions the student actually holds a madrassa enrollment
        // in are accepted. Anything else is not a narrower view of this
        // student's history, it is somebody else's.
        $ownSessions = $enrollments->pluck('academic_session_id')->map(fn ($id) => (int) $id)->all();

        return [
            'academic_session_id' => in_array((int) $session, $ownSessions, true) ? (int) $session : null,
            'month' => $this->boundedInt($request->input('month'), 1, 12),
            'year' => $this->boundedInt($request->input('year'), 2000, 2100),
            'prayer' => in_array($prayer, StudentPrayerAttendance::PRAYERS, true) ? $prayer : null,
        ];
    }

    /**
     * Read an integer filter, or null when it is missing or absurd.
     */
    private function boundedInt(mixed $value, int $min, int $max): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (int) $value;

        return $number >= $min && $number <= $max ? $number : null;
    }

    /**
     * Get the sessions the student holds a madrassa enrollment in.
     *
     * Read from the enrollments already in hand rather than queried again.
     *
     * @param  Collection<int, StudentAcademicEnrollment>  $enrollments
     * @return \Illuminate\Support\Collection<int, AcademicSession>
     */
    private function sessionsFor($enrollments)
    {
        return $enrollments
            ->pluck('academicSession')
            ->filter()
            ->unique('id')
            ->sortByDesc('start_date')
            ->values();
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
     * Past years reach back far enough to read old registers; one year
     * ahead covers a session that runs into the next calendar year.
     *
     * @return array<int, int>
     */
    private function selectableYears(): array
    {
        $current = (int) Carbon::now()->year;

        return range($current + 1, $current - 5);
    }
}
