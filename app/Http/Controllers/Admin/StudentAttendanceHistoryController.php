<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * One student's recorded attendance.
 *
 * A viewing page only. The records it shows are exactly the rows the
 * monthly entry sheet writes; nothing here creates, edits or invents one.
 * A day with no row is a day that was never transcribed, and it is left
 * blank rather than given a status.
 *
 * The student comes from the route, and every record shown is reached
 * through that student's own enrollments. A filter can therefore narrow the
 * history but never widen it past the student it belongs to.
 */
class StudentAttendanceHistoryController extends Controller
{
    /**
     * How many records to show per page.
     */
    private const PER_PAGE = 25;

    /**
     * The value the period filter uses for "no period filter".
     */
    private const ALL_PERIODS = 'all';

    /**
     * Display the student's attendance history.
     */
    public function index(Request $request, Student $student)
    {
        // The header names the student's current placements, so the
        // enrollments and their master data are loaded once here rather
        // than one lookup at a time in the view.
        $student->load([
            'academicEnrollments.academicSession',
            'academicEnrollments.academicClass',
            'academicEnrollments.section',
        ]);

        // Every track the student has ever been enrolled on, so a history
        // stays reachable after a placement ends.
        $tracks = $student->academicEnrollments()
            ->select('academic_track')
            ->distinct()
            ->pluck('academic_track')
            ->all();

        $filters = $this->filters($request, $student, $tracks);

        // The track decides the periods, and it is read from the enrollment
        // side rather than from the request: a school student has no
        // evening to filter by, whatever the URL says.
        $availablePeriods = StudentAttendance::periodsForTrack($filters['academic_track']);

        // The enrollments the history may draw from. Scoped to this student
        // by construction, so nothing below can reach another student's
        // records.
        $enrollmentIds = $this->enrollmentIds($student, $filters);

        $records = $this->query($enrollmentIds, $filters)
            ->with([
                'studentAcademicEnrollment.academicSession',
                'studentAcademicEnrollment.department',
                'studentAcademicEnrollment.academicClass',
                'studentAcademicEnrollment.section',
            ])
            ->inHistoryOrder()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('students.attendance', [
            'student' => $student,
            'filters' => $filters,
            'tracks' => $tracks !== [] ? $tracks : StudentAcademicEnrollment::ACADEMIC_TRACKS,
            'availablePeriods' => $availablePeriods,
            'allPeriodsValue' => self::ALL_PERIODS,
            'records' => $records,
            'summary' => $this->summary($enrollmentIds, $filters),
            'months' => $this->selectableMonths(),
            'years' => $this->selectableYears(),
            'monthLabel' => Carbon::create($filters['year'], $filters['month'], 1)->format('F Y'),
            'days' => StudentAttendance::monthDays($filters['year'], $filters['month']),
            // The month at a glance, across every period the track runs,
            // whatever the period filter narrows the table to.
            'overview' => $this->overview($enrollmentIds, $filters),
            // Only the sessions this student was actually enrolled in.
            'academicSessions' => $this->sessionsFor($student),
        ]);
    }

    /**
     * Show the printable version of the history.
     *
     * The same filters the page is showing, unpaginated: a printed record
     * that stopped at twenty-five rows would not be a record. One student's
     * month is at most a few dozen rows, so there is nothing to page.
     */
    public function printHistory(Request $request, Student $student)
    {
        $student->load([
            'academicEnrollments.academicSession',
            'academicEnrollments.academicClass',
            'academicEnrollments.section',
        ]);

        $tracks = $student->academicEnrollments()
            ->select('academic_track')
            ->distinct()
            ->pluck('academic_track')
            ->all();

        $filters = $this->filters($request, $student, $tracks);
        $enrollmentIds = $this->enrollmentIds($student, $filters);

        return view('students.attendance-print', [
            'student' => $student,
            'filters' => $filters,
            'records' => $this->query($enrollmentIds, $filters)
                ->with([
                    'studentAcademicEnrollment.academicSession',
                    'studentAcademicEnrollment.department',
                    'studentAcademicEnrollment.academicClass',
                    'studentAcademicEnrollment.section',
                ])
                ->inHistoryOrder()
                ->get(),
            'summary' => $this->summary($enrollmentIds, $filters),
            'allPeriodsValue' => self::ALL_PERIODS,
            'monthLabel' => Carbon::create($filters['year'], $filters['month'], 1)->format('F Y'),
        ]);
    }

    /**
     * Read the history filters off the request.
     *
     * Each one falls back to something the student actually has, so the
     * page opens on records rather than on an empty month.
     *
     * @param  array<int, string>  $tracks
     * @return array<string, mixed>
     */
    private function filters(Request $request, Student $student, array $tracks): array
    {
        $track = $request->input('academic_track');

        if (! in_array($track, StudentAcademicEnrollment::ACADEMIC_TRACKS, true)) {
            $track = null;
        }

        // A track the student has never been on shows nothing, so the
        // default is one they have.
        $track ??= $tracks[0] ?? StudentAcademicEnrollment::ACADEMIC_TRACKS[0];

        $session = $request->input('academic_session_id');
        $session = is_numeric($session) ? (int) $session : null;

        // Opening on the most recently recorded month means the page lands
        // on records instead of on whichever month today happens to be in.
        $latest = $this->latestRecordedMonth($student, $track, $session);

        return [
            'academic_session_id' => $session,
            'academic_track' => $track,
            'month' => $this->boundedInt($request->input('month'), 1, 12, $latest['month']),
            'year' => $this->boundedInt($request->input('year'), 2000, 2100, $latest['year']),
            'attendance_period' => $this->period($request, $track),
        ];
    }

    /**
     * Resolve the period filter for a track.
     *
     * A period the track does not run is dropped rather than used: moving
     * from Madrassa to School must not leave Evening selected against a
     * track that has no evening.
     */
    private function period(Request $request, string $track): string
    {
        $period = $request->input('attendance_period');
        $available = StudentAttendance::periodsForTrack($track);

        // School runs one period, so there is nothing to filter by and the
        // filter is pinned to it.
        if (count($available) === 1) {
            return $available[0];
        }

        return in_array($period, $available, true) ? $period : self::ALL_PERIODS;
    }

    /**
     * Find the month the student's most recent attendance falls in.
     *
     * @return array{month: int, year: int}
     */
    private function latestRecordedMonth(Student $student, string $track, ?int $session): array
    {
        $latest = StudentAttendance::query()
            ->whereIn('student_academic_enrollment_id', $this->enrollmentIds($student, [
                'academic_track' => $track,
                'academic_session_id' => $session,
            ]))
            ->max('attendance_date');

        $date = $latest ? Carbon::parse($latest) : Carbon::now();

        return ['month' => (int) $date->month, 'year' => (int) $date->year];
    }

    /**
     * Get the enrollments the history may read.
     *
     * Always started from the student's own relation. That is what makes
     * cross-student access impossible: an id that is not this student's
     * never enters the list, however the request is written.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, int>
     */
    private function enrollmentIds(Student $student, array $filters): array
    {
        return $student->academicEnrollments()
            ->where('academic_track', $filters['academic_track'])
            ->when(
                $filters['academic_session_id'],
                fn ($query, $session) => $query->where('academic_session_id', $session)
            )
            ->pluck('id')
            ->all();
    }

    /**
     * Start the attendance query for a set of enrollments and a month.
     *
     * @param  array<int, int>  $enrollmentIds
     * @param  array<string, mixed>  $filters
     */
    private function query(array $enrollmentIds, array $filters)
    {
        $start = Carbon::create($filters['year'], $filters['month'], 1)->startOfMonth();

        return StudentAttendance::query()
            ->whereIn('student_academic_enrollment_id', $enrollmentIds)
            ->whereBetween('attendance_date', [
                $start->format('Y-m-d'),
                $start->copy()->endOfMonth()->format('Y-m-d'),
            ])
            ->when(
                $filters['attendance_period'] !== self::ALL_PERIODS,
                fn ($query) => $query->where('attendance_period', $filters['attendance_period'])
            );
    }

    /**
     * Count what the selected filters cover.
     *
     * Recorded rows only. A day nobody has transcribed yet is not an
     * absence and not a present; it is simply not here.
     *
     * @param  array<int, int>  $enrollmentIds
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function summary(array $enrollmentIds, array $filters): array
    {
        $counts = $this->query($enrollmentIds, $filters)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $present = (int) $counts->get(StudentAttendance::STATUS_PRESENT, 0);
        $absent = (int) $counts->get(StudentAttendance::STATUS_ABSENT, 0);

        return [
            'total' => $present + $absent,
            'present' => $present,
            'absent' => $absent,
        ];
    }

    /**
     * Build the month-at-a-glance grid.
     *
     * Every period the track runs, whatever the table is filtered to, so
     * the overview stays an overview. Keyed by date and period; a date and
     * period that is missing from it was never recorded.
     *
     * @param  array<int, int>  $enrollmentIds
     * @param  array<string, mixed>  $filters
     * @return array<string, array<string, StudentAttendance>>
     */
    private function overview(array $enrollmentIds, array $filters): array
    {
        $start = Carbon::create($filters['year'], $filters['month'], 1)->startOfMonth();

        $records = StudentAttendance::query()
            ->whereIn('student_academic_enrollment_id', $enrollmentIds)
            ->whereBetween('attendance_date', [
                $start->format('Y-m-d'),
                $start->copy()->endOfMonth()->format('Y-m-d'),
            ])
            ->get();

        $grid = [];

        foreach ($records as $record) {
            $grid[$record->attendance_date->format('Y-m-d')][$record->attendance_period] = $record;
        }

        return $grid;
    }

    /**
     * Get the academic sessions this student has been enrolled in.
     *
     * Not the active sessions: a history has to stay readable after its
     * session is closed.
     */
    private function sessionsFor(Student $student)
    {
        return AcademicSession::query()
            ->whereIn('id', $student->academicEnrollments()->select('academic_session_id'))
            ->orderByDesc('start_date')
            ->get();
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
}
