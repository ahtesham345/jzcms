<?php

namespace App\Support;

use App\Models\AcademicSession;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentAttendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A whole academic session's attendance, for one track.
 *
 * Two different numbers live here and must never be confused. Attendance
 * opportunities are what the paper registers could hold: every teaching day
 * a student was enrolled for, multiplied by the registers their track sits
 * that day. Recorded is what has actually been transcribed. The gap between
 * them is the work still to do, and it is never treated as absence.
 *
 * A student appears once. Today the academic module already guarantees that,
 * because a unique index allows one enrollment per student per session per
 * track and a promotion therefore moves the student into the next session.
 * The windows are still merged before the days are counted, so that a
 * student who somehow holds two placements on one track is one row and the
 * day they change over is counted once rather than twice.
 *
 * The computation is shared by the page, the printout and the export, so
 * all three report the same figures.
 */
class SessionAttendanceSummary
{
    /**
     * The matching enrollments, with the student columns for display.
     *
     * @var Collection<int, StudentAcademicEnrollment>|null
     */
    private ?Collection $enrollments = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $students = null;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly AcademicSession $session,
        private readonly array $filters,
    ) {}

    /**
     * The periods the selected track sits.
     *
     * Read from the track, so a school session can never total an evening
     * register even if a stray row exists.
     *
     * @return array<int, string>
     */
    public function periods(): array
    {
        return StudentAttendance::periodsForTrack($this->filters['academic_track']);
    }

    /**
     * The first day of the session.
     */
    public function start(): Carbon
    {
        return Carbon::parse($this->session->start_date)->startOfDay();
    }

    /**
     * The last day of the session.
     *
     * A session without an end date is read to the end of its starting year
     * plus one, rather than left open: an unbounded session would make every
     * opportunity count meaningless.
     */
    public function end(): Carbon
    {
        return $this->session->end_date
            ? Carbon::parse($this->session->end_date)->startOfDay()
            : $this->start()->copy()->addYear()->subDay();
    }

    /**
     * The teaching days the session itself holds.
     *
     * The calendar figure, not a per-student one: it says how long the
     * session is, which is what makes the opportunity counts readable.
     */
    public function sessionTeachingDays(): int
    {
        return StudentAttendance::teachingDaysBetween($this->start(), $this->end());
    }

    /**
     * One row per student, aggregated across their enrollments.
     *
     * @return array<int, array<string, mixed>>
     */
    public function students(): array
    {
        if ($this->students !== null) {
            return $this->students;
        }

        $attendance = $this->attendanceByEnrollment();
        $periodCount = count($this->periods());

        $rows = [];

        foreach ($this->enrollmentRows()->groupBy('student_id') as $studentId => $enrollments) {
            // Newest placement last, so the row names where the student
            // ended the session rather than where they started it.
            $ordered = $enrollments->sortBy(fn ($enrollment) => $this->windowStart($enrollment)->timestamp)->values();
            $latest = $ordered->last();

            $intervals = $this->mergedIntervals($ordered);

            $teachingDays = collect($intervals)
                ->sum(fn ($interval) => StudentAttendance::teachingDaysBetween($interval[0], $interval[1]));

            $present = 0;
            $absent = 0;

            foreach ($ordered as $enrollment) {
                $present += $attendance[$enrollment->id][StudentAttendance::STATUS_PRESENT] ?? 0;
                $absent += $attendance[$enrollment->id][StudentAttendance::STATUS_ABSENT] ?? 0;
            }

            $opportunities = $teachingDays * $periodCount;
            $recorded = $present + $absent;

            $rows[] = [
                'student_id' => (int) $studentId,
                'full_name' => $latest->full_name,
                'registration_number' => $latest->registration_number,
                'roll_number' => $latest->roll_number,
                'department_id' => $latest->department_id,
                'academic_class_id' => $latest->academic_class_id,
                'section_id' => $latest->section_id,
                'academic_track' => $this->filters['academic_track'],
                'enrollment_id' => $latest->id,
                // More than one placement means the student was promoted
                // inside the session; the figures cover both.
                'placements' => $ordered->count(),
                'intervals' => $intervals,
                'teaching_days' => $teachingDays,
                'opportunities' => $opportunities,
                'present' => $present,
                'absent' => $absent,
                'recorded' => $recorded,
                // Never negative: a record entered outside the enrollment
                // window would otherwise read as less than nothing left.
                'unrecorded' => max(0, $opportunities - $recorded),
                'percentage' => StudentAttendance::attendancePercentage($present, $absent),
            ];
        }

        return $this->students = $rows;
    }

    /**
     * Total the whole filtered group.
     *
     * The overall percentage comes from the aggregate present and absent
     * counts, not from averaging the students' own percentages: a student
     * with three records must not weigh as much as one with two hundred.
     *
     * @return array<string, mixed>
     */
    public function totals(): array
    {
        $students = collect($this->students());

        $present = (int) $students->sum('present');
        $absent = (int) $students->sum('absent');
        $opportunities = (int) $students->sum('opportunities');
        $recorded = $present + $absent;

        return [
            'students' => $students->count(),
            'session_teaching_days' => $this->sessionTeachingDays(),
            'teaching_days' => (int) $students->sum('teaching_days'),
            'opportunities' => $opportunities,
            'recorded' => $recorded,
            'unrecorded' => max(0, $opportunities - $recorded),
            'present' => $present,
            'absent' => $absent,
            'percentage' => StudentAttendance::attendancePercentage($present, $absent),
        ];
    }

    /**
     * One row per month the session touches.
     *
     * Only months that overlap the session, and a month the session starts
     * or ends inside is counted from the overlap alone rather than from the
     * whole calendar month.
     *
     * @return array<int, array<string, mixed>>
     */
    public function months(): array
    {
        $attendance = $this->attendanceByMonth();
        $periodCount = count($this->periods());
        $students = $this->students();

        $rows = [];
        $cursor = $this->start()->copy()->startOfMonth();
        $last = $this->end()->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($last)) {
            $from = $cursor->copy()->startOfMonth()->max($this->start());
            $to = $cursor->copy()->endOfMonth()->startOfDay()->min($this->end());

            $key = $cursor->format('Y-m');

            // Every student's own days inside this month, so the
            // opportunities line up with what was recorded for the group.
            $teachingDays = 0;

            foreach ($students as $student) {
                foreach ($student['intervals'] as [$intervalStart, $intervalEnd]) {
                    $teachingDays += StudentAttendance::teachingDaysBetween(
                        $intervalStart->copy()->max($from),
                        $intervalEnd->copy()->min($to)
                    );
                }
            }

            $present = $attendance[$key][StudentAttendance::STATUS_PRESENT] ?? 0;
            $absent = $attendance[$key][StudentAttendance::STATUS_ABSENT] ?? 0;
            $opportunities = $teachingDays * $periodCount;

            $rows[] = [
                'key' => $key,
                'label' => $cursor->format('M Y'),
                'month' => (int) $cursor->month,
                'year' => (int) $cursor->year,
                // The calendar length of the month inside the session.
                'session_teaching_days' => StudentAttendance::teachingDaysBetween($from, $to),
                'teaching_days' => $teachingDays,
                'opportunities' => $opportunities,
                'present' => $present,
                'absent' => $absent,
                'recorded' => $present + $absent,
                'unrecorded' => max(0, $opportunities - $present - $absent),
                'percentage' => StudentAttendance::attendancePercentage($present, $absent),
            ];

            $cursor->addMonth();
        }

        return $rows;
    }

    /**
     * The enrollments the summary is built from.
     *
     * One query. Enrollment rows, not attendance rows: there is one per
     * student per placement, and their date windows cannot be merged in SQL.
     *
     * @return Collection<int, StudentAcademicEnrollment>
     */
    private function enrollmentRows(): Collection
    {
        return $this->enrollments ??= $this->baseQuery()
            ->orderBy('students.full_name')
            ->orderBy('students.registration_number')
            ->orderBy('student_academic_enrollments.start_date')
            ->get([
                'student_academic_enrollments.id',
                'student_academic_enrollments.student_id',
                'student_academic_enrollments.start_date',
                'student_academic_enrollments.end_date',
                'student_academic_enrollments.status',
                'student_academic_enrollments.department_id',
                'student_academic_enrollments.academic_class_id',
                'student_academic_enrollments.section_id',
                'students.full_name',
                'students.registration_number',
                'students.roll_number',
            ]);
    }

    /**
     * Build the enrollment query the whole summary is drawn from.
     *
     * The academic group is matched on the enrollment row, so the filters
     * combine with AND: a class from another department matches nothing
     * rather than one filter quietly overriding the other.
     */
    private function baseQuery()
    {
        return StudentAcademicEnrollment::query()
            ->join('students', 'students.id', '=', 'student_academic_enrollments.student_id')
            ->where('student_academic_enrollments.academic_session_id', $this->session->id)
            ->where('student_academic_enrollments.academic_track', $this->filters['academic_track'])
            ->when($this->filters['department_id'] ?? null, fn ($query, $value) => $query->where('student_academic_enrollments.department_id', $value))
            ->when($this->filters['academic_class_id'] ?? null, fn ($query, $value) => $query->where('student_academic_enrollments.academic_class_id', $value))
            ->when($this->filters['section_id'] ?? null, fn ($query, $value) => $query->where('student_academic_enrollments.section_id', $value))
            ->when($this->filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($student) use ($search) {
                    $student->where('students.full_name', 'like', "%{$search}%")
                        ->orWhere('students.registration_number', 'like', "%{$search}%")
                        ->orWhere('students.roll_number', 'like', "%{$search}%");
                });
            });
    }

    /**
     * Count the recorded attendance for each enrollment.
     *
     * One grouped query for the whole group; no attendance row reaches PHP.
     *
     * @return array<int, array<string, int>>
     */
    private function attendanceByEnrollment(): array
    {
        $rows = $this->attendanceQuery()
            ->groupBy('student_academic_enrollment_id', 'status')
            ->selectRaw('student_academic_enrollment_id, status, count(*) as aggregate')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row->student_academic_enrollment_id][$row->status] = (int) $row->aggregate;
        }

        return $counts;
    }

    /**
     * Count the recorded attendance for each day, bucketed into months.
     *
     * Grouped by date rather than by a driver-specific month expression, so
     * the same query works on both connections. A session is a few hundred
     * days, so this stays small however many students it covers.
     *
     * @return array<string, array<string, int>>
     */
    private function attendanceByMonth(): array
    {
        $rows = $this->attendanceQuery()
            ->groupBy('attendance_date', 'status')
            ->selectRaw('attendance_date, status, count(*) as aggregate')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $key = Carbon::parse($row->attendance_date)->format('Y-m');

            $counts[$key][$row->status] = ($counts[$key][$row->status] ?? 0) + (int) $row->aggregate;
        }

        return $counts;
    }

    /**
     * Start an attendance query over the filtered enrollments.
     *
     * The enrollments arrive as a subquery rather than as a list of ids, so
     * a large cohort does not become a large IN clause. The caller adds the
     * grouping and the counts: a select passed to get() would replace them.
     */
    private function attendanceQuery()
    {
        return StudentAttendance::query()
            ->whereIn(
                'student_academic_enrollment_id',
                $this->baseQuery()->select('student_academic_enrollments.id')
            )
            ->whereBetween('attendance_date', [
                $this->start()->format('Y-m-d'),
                $this->end()->format('Y-m-d'),
            ])
            ->whereIn('attendance_period', $this->periods());
    }

    /**
     * The first day of an enrollment inside the session.
     */
    private function windowStart(StudentAcademicEnrollment $enrollment): Carbon
    {
        return Carbon::parse($enrollment->start_date)->startOfDay()->max($this->start());
    }

    /**
     * The last day of an enrollment inside the session.
     *
     * An enrollment that is still open runs to the end of the session: there
     * is no later boundary to use, and the session is what is being reported.
     */
    private function windowEnd(StudentAcademicEnrollment $enrollment): Carbon
    {
        $end = $enrollment->end_date
            ? Carbon::parse($enrollment->end_date)->startOfDay()
            : $this->end();

        return $end->min($this->end());
    }

    /**
     * Merge a student's enrollment windows into non-overlapping intervals.
     *
     * Promotion closes one enrollment and opens the next on the same day, so
     * without this the day of the promotion would be counted twice. Adjacent
     * windows are joined for the same reason.
     *
     * @param  Collection<int, StudentAcademicEnrollment>  $enrollments
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function mergedIntervals(Collection $enrollments): array
    {
        $windows = [];

        foreach ($enrollments as $enrollment) {
            $start = $this->windowStart($enrollment);
            $end = $this->windowEnd($enrollment);

            // Entirely outside the session, or closed before it opened.
            if ($start->greaterThan($end)) {
                continue;
            }

            $windows[] = [$start, $end];
        }

        usort($windows, fn ($a, $b) => $a[0]->timestamp <=> $b[0]->timestamp);

        $merged = [];

        foreach ($windows as $window) {
            $last = count($merged) - 1;

            // Touching or overlapping windows become one. The day after the
            // previous end still joins: two placements that run back to back
            // are one stretch of schooling.
            if ($last >= 0 && $window[0]->lessThanOrEqualTo($merged[$last][1]->copy()->addDay())) {
                $merged[$last][1] = $merged[$last][1]->max($window[1]);

                continue;
            }

            $merged[] = $window;
        }

        return $merged;
    }
}
