<?php

namespace App\Support;

use App\Models\AcademicSession;
use App\Models\MadrassaDailyRecord;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentAttendance;
use App\Models\StudentPrayerAttendance;
use App\Models\StudentResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything the madrassa reports say about one student in one session.
 *
 * The reports are assembled here rather than in the controllers so the
 * printable result report and the detailed student report cannot drift
 * apart: the same attendance window, the same prayer arithmetic, the same
 * progress counts, the same grades. Two reports disagreeing about one
 * student's year would be worse than either of them being absent.
 *
 * Nothing in this class is told which enrollments to read. It is handed a
 * student and a session and derives the madrassa placements itself, so no
 * id from a browser can widen what a report covers. The school side of a
 * Hifz + School student is unreachable from here by construction: the track
 * condition sits on the one query every other query is bounded by.
 *
 * Historical accuracy works the same way it does everywhere else in this
 * module. A record, a mark or a result is read back through the enrollment
 * it was written against, so a First Term result keeps the class it was sat
 * in after the student is promoted. The student's current placement is used
 * for the profile header and for nothing else.
 *
 * The reporting window follows the rules the prayer session summary already
 * established: a session that has ended is counted to its end date, a
 * session still running is counted only as far as today, and each student's
 * own enrollment dates bound it further. Weekends are never counted,
 * because the institution does not sit on them.
 *
 * Aggregates are SQL. The one thing deliberately read row by row is the
 * per-day attendance and prayer bucketing, which is grouped by date in the
 * database and bucketed into months in PHP - the same approach
 * SessionAttendanceSummary uses, and for the same reason: a month
 * expression is driver-specific, while a session is only a few hundred
 * days and so stays small however many students or records exist.
 */
class MadrassaStudentReport
{
    /** @var Collection<int, StudentAcademicEnrollment>|null */
    private ?Collection $allEnrollments = null;

    /** @var Collection<int, StudentAcademicEnrollment>|null */
    private ?Collection $sessionEnrollments = null;

    /** @var array<int, array{0: Carbon, 1: Carbon}>|null */
    private ?array $intervals = null;

    /** @var array<string, array<string, int>>|null */
    private ?array $attendanceByDate = null;

    /** @var array<string, array<string, array<string, int>>>|null */
    private ?array $prayersByDate = null;

    /** @var array<string, array<string, int>>|null */
    private ?array $recordsByDate = null;

    public function __construct(
        private readonly Student $student,
        private readonly ?AcademicSession $session,
    ) {}

    public function student(): Student
    {
        return $this->student;
    }

    public function session(): ?AcademicSession
    {
        return $this->session;
    }

    /* ------------------------------------------------------------------ */
    /* The placements a report may read */
    /* ------------------------------------------------------------------ */

    /**
     * Every madrassa placement the student has ever held, newest first.
     *
     * The single gate. Everything below is bounded by the ids this returns,
     * so a school enrollment cannot appear in an attendance total, a prayer
     * count, a daily record, a result or a month row - not because each of
     * them remembers to exclude it, but because none of them can see it.
     *
     * @return Collection<int, StudentAcademicEnrollment>
     */
    public function enrollments(): Collection
    {
        return $this->allEnrollments ??= $this->student->academicEnrollments()
            ->where('academic_track', StudentResult::ACADEMIC_TRACK)
            ->with(['academicSession', 'department', 'academicClass', 'section'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The madrassa placements that fall inside the reporting session.
     *
     * @return Collection<int, StudentAcademicEnrollment>
     */
    public function enrollmentsInSession(): Collection
    {
        if ($this->sessionEnrollments !== null) {
            return $this->sessionEnrollments;
        }

        if ($this->session === null) {
            return $this->sessionEnrollments = $this->enrollments();
        }

        return $this->sessionEnrollments = $this->enrollments()
            ->where('academic_session_id', $this->session->id)
            ->values();
    }

    /**
     * Determine whether this student has anything to report at all.
     *
     * False for a school-only student, which is what turns a report URL
     * into a 404 for them rather than into an empty page that implies a
     * madrassa record exists.
     */
    public function hasMadrassaEnrollment(): bool
    {
        return $this->enrollments()->isNotEmpty();
    }

    /**
     * The placement to describe the student by today.
     *
     * The active one, or the most recent when the student no longer holds
     * an active madrassa enrollment. Used for the profile header only:
     * every historical figure reads its own enrollment instead.
     */
    public function currentEnrollment(): ?StudentAcademicEnrollment
    {
        return $this->enrollments()->firstWhere('status', 'Active')
            ?? $this->enrollments()->first();
    }

    /**
     * The placement the reporting session's results belong to.
     */
    public function sessionEnrollment(): ?StudentAcademicEnrollment
    {
        return $this->enrollmentsInSession()->first();
    }

    /**
     * The student's madrassa placements during the reporting session.
     *
     * The progression the report shows: where the student sat at the start
     * of the session and every placement they were moved into during it,
     * earliest first.
     *
     * Read from the enrollment rows themselves, never from the student's
     * current placement, so a period that has ended keeps the class and
     * section it was actually taught in. School placements are unreachable
     * from here - enrollments() is bounded by the madrassa track - and so
     * are placements from any other session.
     *
     * A placement with no end date is shown running to the end of the
     * session rather than left open: this table is scoped to one session,
     * and within that scope the session's end is where the placement stops.
     *
     * @return array<int, array<string, mixed>>
     */
    public function trackRecord(): array
    {
        $sessionEnd = $this->sessionEnd();

        return $this->enrollmentsInSession()
            // Chronological, and total: two placements that somehow start on
            // the same day still come out in a fixed order.
            ->sort(fn ($a, $b) => [$a->start_date?->timestamp ?? 0, $a->id]
                <=> [$b->start_date?->timestamp ?? 0, $b->id])
            ->values()
            ->map(fn (StudentAcademicEnrollment $enrollment) => [
                'enrollment' => $enrollment,
                'start' => $enrollment->start_date,
                // Null when the placement is still open. The view falls back
                // to the session end for the period it prints.
                'end' => $enrollment->end_date,
                'period_end' => $enrollment->end_date ?? $sessionEnd,
                'department' => $enrollment->department?->name,
                'class' => $enrollment->academicClass?->name,
                'section' => $enrollment->section?->name,
                'status' => $enrollment->status,
            ])
            ->all();
    }

    /**
     * The enrollment ids every query below is bounded by.
     *
     * @return array<int, int>
     */
    private function enrollmentIds(): array
    {
        return $this->enrollmentsInSession()->pluck('id')->all();
    }

    /**
     * The enrollment ids across the student's whole madrassa history.
     *
     * @return array<int, int>
     */
    private function historyEnrollmentIds(): array
    {
        return $this->enrollments()->pluck('id')->all();
    }

    /* ------------------------------------------------------------------ */
    /* The reporting window */
    /* ------------------------------------------------------------------ */

    /**
     * The first day the report counts from.
     */
    public function windowStart(): ?Carbon
    {
        return $this->session === null
            ? null
            : Carbon::parse($this->session->start_date)->startOfDay();
    }

    /**
     * The last day the session itself covers.
     *
     * A session with no end date is read to the day before its first
     * anniversary rather than left open, the same as the academic session
     * summary reads it: an unbounded session makes every count meaningless.
     */
    public function sessionEnd(): ?Carbon
    {
        if ($this->session === null) {
            return null;
        }

        return $this->session->end_date
            ? Carbon::parse($this->session->end_date)->startOfDay()
            : $this->windowStart()->copy()->addYear()->subDay();
    }

    /**
     * The last day the report actually counts through.
     *
     * A session still running is counted only as far as today: a day nobody
     * has reached cannot have been attended, prayed or recorded, so
     * counting it would report the rest of the year as a register the
     * office has failed to transcribe. A session that has already ended
     * keeps its own end date, because there is no future left in it to
     * exclude.
     *
     * The same rule the prayer session summary applies, restated here only
     * because this class has no session summary instance to ask.
     */
    public function countedEnd(): ?Carbon
    {
        $end = $this->sessionEnd();

        if ($end === null) {
            return null;
        }

        $today = Carbon::now()->startOfDay();

        return $end->lessThan($today) ? $end : $today;
    }

    /**
     * The student's own enrollment windows inside the reporting window.
     *
     * Merged, so a promotion that closes one placement and opens the next
     * on the same day does not count that day twice.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    public function intervals(): array
    {
        if ($this->intervals !== null) {
            return $this->intervals;
        }

        $start = $this->windowStart();
        $end = $this->countedEnd();

        if ($start === null || $end === null) {
            return $this->intervals = [];
        }

        $windows = [];

        foreach ($this->enrollmentsInSession() as $enrollment) {
            $from = Carbon::parse($enrollment->start_date)->startOfDay()->max($start);

            $to = $enrollment->end_date
                ? Carbon::parse($enrollment->end_date)->startOfDay()->min($end)
                : $end->copy();

            // A placement that closed before the session opened, or opened
            // after the counted window closed, covers nothing.
            if ($from->greaterThan($to)) {
                continue;
            }

            $windows[] = [$from, $to];
        }

        usort($windows, fn ($a, $b) => $a[0]->timestamp <=> $b[0]->timestamp);

        $merged = [];

        foreach ($windows as [$from, $to]) {
            $last = count($merged) - 1;

            if ($last >= 0 && $from->lessThanOrEqualTo($merged[$last][1]->copy()->addDay())) {
                $merged[$last][1] = $merged[$last][1]->max($to);

                continue;
            }

            $merged[] = [$from, $to];
        }

        return $this->intervals = $merged;
    }

    /**
     * Count the teaching days the student was enrolled for, in a range.
     *
     * Weekends never count: the rule is StudentAttendance's, so the report
     * and the register agree about which days the institution sits.
     */
    public function workingDaysBetween(?Carbon $from, ?Carbon $to): int
    {
        if ($from === null || $to === null) {
            return 0;
        }

        $days = 0;

        foreach ($this->intervals() as [$start, $end]) {
            $days += StudentAttendance::teachingDaysBetween(
                $start->copy()->max($from),
                $end->copy()->min($to)
            );
        }

        return $days;
    }

    /**
     * The teaching days the whole reporting window holds for this student.
     */
    public function workingDays(): int
    {
        return $this->workingDaysBetween($this->windowStart(), $this->countedEnd());
    }

    /* ------------------------------------------------------------------ */
    /* Academic attendance */
    /* ------------------------------------------------------------------ */

    /**
     * Count the student's attendance marks per day.
     *
     * Grouped by date in SQL and bucketed in PHP, so the same query runs on
     * both connections. One query for the whole session however many marks
     * it holds.
     *
     * Only the periods the madrassa track sits are counted, read from the
     * track rather than from the rows, so a stray school-shaped row could
     * not be totalled here even if one existed.
     *
     * @return array<string, array<string, int>>
     */
    private function attendanceByDate(): array
    {
        if ($this->attendanceByDate !== null) {
            return $this->attendanceByDate;
        }

        $ids = $this->enrollmentIds();
        $start = $this->windowStart();
        $end = $this->countedEnd();

        if ($ids === [] || $start === null || $end === null) {
            return $this->attendanceByDate = [];
        }

        $rows = StudentAttendance::query()
            ->whereIn('student_academic_enrollment_id', $ids)
            ->whereBetween('attendance_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereIn('attendance_period', StudentAttendance::periodsForTrack(StudentResult::ACADEMIC_TRACK))
            ->groupBy('attendance_date', 'status')
            ->selectRaw('attendance_date, status, count(*) as aggregate')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $key = Carbon::parse($row->attendance_date)->format('Y-m-d');

            $counts[$key][$row->status] = ($counts[$key][$row->status] ?? 0) + (int) $row->aggregate;
        }

        return $this->attendanceByDate = $counts;
    }

    /**
     * Summarise the student's academic attendance for the window.
     *
     * Present and Absent are counted as register marks, not as days: the
     * madrassa sits three registers a day, and that is the unit every other
     * attendance report in this project already counts and divides. The
     * percentage rule is StudentAttendance's, unchanged - the denominator
     * is what has been recorded, never the calendar, so a month nobody has
     * transcribed yet reads as a small sample rather than as absence.
     *
     * @return array<string, mixed>
     */
    public function attendanceSummary(): array
    {
        return $this->attendanceBetween($this->windowStart(), $this->countedEnd());
    }

    /**
     * Summarise attendance inside part of the window.
     *
     * @return array<string, mixed>
     */
    private function attendanceBetween(?Carbon $from, ?Carbon $to): array
    {
        $present = 0;
        $absent = 0;

        if ($from !== null && $to !== null) {
            $fromKey = $from->format('Y-m-d');
            $toKey = $to->format('Y-m-d');

            foreach ($this->attendanceByDate() as $date => $statuses) {
                if ($date < $fromKey || $date > $toKey) {
                    continue;
                }

                $present += $statuses[StudentAttendance::STATUS_PRESENT] ?? 0;
                $absent += $statuses[StudentAttendance::STATUS_ABSENT] ?? 0;
            }
        }

        $workingDays = $this->workingDaysBetween($from, $to);

        return [
            'working_days' => $workingDays,
            // What the registers could have held: three a day on this
            // track. Carried so the marks below reconcile with the days.
            'opportunities' => StudentAttendance::opportunitiesForTrack(
                $workingDays,
                StudentResult::ACADEMIC_TRACK
            ),
            'present' => $present,
            'absent' => $absent,
            'recorded' => $present + $absent,
            'percentage' => StudentAttendance::attendancePercentage($present, $absent),
        ];
    }

    /**
     * The student's academic attendance rows, newest first.
     *
     * Bounded by the madrassa placements, so a Hifz + School student's
     * school register is not reachable through this page.
     *
     * @return Collection<int, StudentAttendance>
     */
    public function attendanceHistory(int $limit = 400): Collection
    {
        $ids = $this->enrollmentIds();

        if ($ids === []) {
            return collect();
        }

        return StudentAttendance::query()
            ->whereIn('student_academic_enrollment_id', $ids)
            ->whereIn('attendance_period', StudentAttendance::periodsForTrack(StudentResult::ACADEMIC_TRACK))
            ->with([
                'studentAcademicEnrollment.academicSession',
                'studentAcademicEnrollment.academicClass',
                'studentAcademicEnrollment.section',
            ])
            ->inHistoryOrder()
            ->limit($limit)
            ->get();
    }

    /* ------------------------------------------------------------------ */
    /* Prayer attendance */
    /* ------------------------------------------------------------------ */

    /**
     * Count the student's prayer marks per day and prayer.
     *
     * @return array<string, array<string, array<string, int>>>
     */
    private function prayersByDate(): array
    {
        if ($this->prayersByDate !== null) {
            return $this->prayersByDate;
        }

        $ids = $this->enrollmentIds();
        $start = $this->windowStart();
        $end = $this->countedEnd();

        if ($ids === [] || $start === null || $end === null) {
            return $this->prayersByDate = [];
        }

        $rows = StudentPrayerAttendance::query()
            ->whereIn('student_academic_enrollment_id', $ids)
            ->whereBetween('attendance_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->groupBy('attendance_date', 'prayer', 'status')
            ->selectRaw('attendance_date, prayer, status, count(*) as aggregate')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $key = Carbon::parse($row->attendance_date)->format('Y-m-d');

            $counts[$key][$row->prayer][$row->status] =
                ($counts[$key][$row->prayer][$row->status] ?? 0) + (int) $row->aggregate;
        }

        return $this->prayersByDate = $counts;
    }

    /**
     * Summarise the five prayers for the window.
     *
     * The arithmetic is StudentPrayerAttendance's, unchanged: the
     * denominator is the rows on file, so a prayer nobody has transcribed
     * is unrecorded rather than an absence, and a weekend never appears in
     * either column because no row can exist for one.
     *
     * @return array<string, mixed>
     */
    public function prayerSummary(): array
    {
        return $this->prayersBetween($this->windowStart(), $this->countedEnd());
    }

    /**
     * @return array<string, mixed>
     */
    private function prayersBetween(?Carbon $from, ?Carbon $to): array
    {
        $byPrayer = [];

        foreach (StudentPrayerAttendance::PRAYERS as $prayer) {
            $byPrayer[$prayer] = ['present' => 0, 'absent' => 0];
        }

        if ($from !== null && $to !== null) {
            $fromKey = $from->format('Y-m-d');
            $toKey = $to->format('Y-m-d');

            foreach ($this->prayersByDate() as $date => $prayers) {
                if ($date < $fromKey || $date > $toKey) {
                    continue;
                }

                foreach ($prayers as $prayer => $statuses) {
                    if (! isset($byPrayer[$prayer])) {
                        continue;
                    }

                    $byPrayer[$prayer]['present'] += $statuses[StudentPrayerAttendance::STATUS_PRESENT] ?? 0;
                    $byPrayer[$prayer]['absent'] += $statuses[StudentPrayerAttendance::STATUS_ABSENT] ?? 0;
                }
            }
        }

        $present = 0;
        $absent = 0;

        foreach ($byPrayer as $prayer => $counts) {
            $present += $counts['present'];
            $absent += $counts['absent'];

            $byPrayer[$prayer]['recorded'] = $counts['present'] + $counts['absent'];
            $byPrayer[$prayer]['percentage'] = StudentPrayerAttendance::attendancePercentage(
                $counts['present'],
                $counts['absent']
            );
        }

        return [
            'prayers' => $byPrayer,
            'present' => $present,
            'absent' => $absent,
            'recorded' => $present + $absent,
            'expected' => StudentPrayerAttendance::expectedPrayersForDays(
                $this->workingDaysBetween($from, $to)
            ),
            'percentage' => StudentPrayerAttendance::attendancePercentage($present, $absent),
        ];
    }

    /**
     * The student's prayer rows, newest first, grouped by date.
     *
     * A weekend is reported as OFF rather than as an absence, and a prayer
     * with no row is Unmarked. Neither is a judgement about the student:
     * one is the institution not sitting, the other is the office not yet
     * having transcribed the page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function prayerHistory(int $limit = 400): array
    {
        $ids = $this->enrollmentIds();

        if ($ids === []) {
            return [];
        }

        $rows = StudentPrayerAttendance::query()
            ->whereIn('student_academic_enrollment_id', $ids)
            ->inPrayerOrder()
            ->limit($limit * count(StudentPrayerAttendance::PRAYERS))
            ->get(['attendance_date', 'prayer', 'status', 'absence_reason']);

        $byDate = [];

        foreach ($rows as $row) {
            $key = Carbon::parse($row->attendance_date)->format('Y-m-d');

            $byDate[$key][$row->prayer] = $row->status;
        }

        krsort($byDate);

        $history = [];

        foreach (array_slice($byDate, 0, $limit, true) as $date => $statuses) {
            $offDay = StudentPrayerAttendance::offDayName($date);

            $prayers = [];

            foreach (StudentPrayerAttendance::PRAYERS as $prayer) {
                $prayers[$prayer] = $offDay !== null
                    ? 'OFF'
                    : ($statuses[$prayer] ?? StudentPrayerAttendance::STATUS_UNMARKED);
            }

            $history[] = [
                'date' => Carbon::parse($date),
                'off_day' => $offDay,
                'prayers' => $prayers,
            ];
        }

        return $history;
    }

    /* ------------------------------------------------------------------ */
    /* Madrassa daily records */
    /* ------------------------------------------------------------------ */

    /**
     * Work out which daily record this student is kept on.
     *
     * The current programme decides it while the student is enrolled, and
     * otherwise what their records were actually written as - the same rule
     * the progress page uses, so the two never describe one student's work
     * differently.
     */
    public function recordType(): ?string
    {
        $fromProgramme = MadrassaDailyRecord::recordTypeForStudentType($this->student->student_type);

        if ($fromProgramme !== null) {
            return $fromProgramme;
        }

        $ids = $this->historyEnrollmentIds();

        if ($ids === []) {
            return null;
        }

        return MadrassaDailyRecord::query()
            ->whereIn('student_academic_enrollment_id', $ids)
            ->inDailyOrder()
            ->value('record_type');
    }

    /**
     * Count the days each kind of work was recorded on, per day.
     *
     * The labels and the SQL both come from MadrassaDailyRecord, so this
     * invents no progress arithmetic of its own. Nothing is added up: the
     * quantities are free text - "1 page", "half page", "1/2 para" - and
     * this module has never been entitled to turn those into numbers.
     *
     * @return array<string, array<string, int>>
     */
    private function recordsByDate(): array
    {
        if ($this->recordsByDate !== null) {
            return $this->recordsByDate;
        }

        $ids = $this->enrollmentIds();
        $start = $this->windowStart();
        $end = $this->countedEnd();
        $recordType = $this->recordType();

        if ($ids === [] || $start === null || $end === null || $recordType === null) {
            return $this->recordsByDate = [];
        }

        $query = MadrassaDailyRecord::query()
            ->whereIn('student_academic_enrollment_id', $ids)
            ->where('record_type', $recordType)
            ->whereBetween('record_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->groupBy('record_date')
            ->selectRaw('record_date, count(*) as recorded_days');

        foreach (MadrassaDailyRecord::summaryFieldsFor($recordType) as $label => $fields) {
            $query->selectRaw(
                MadrassaDailyRecord::recordedDaysExpression($fields).' as '.$this->countColumn($label)
            );
        }

        $counts = [];

        foreach ($query->get() as $row) {
            $key = Carbon::parse($row->record_date)->format('Y-m-d');

            $bucket = ['recorded_days' => (int) $row->recorded_days];

            foreach (array_keys(MadrassaDailyRecord::summaryFieldsFor($recordType)) as $label) {
                $bucket[$label] = (int) ($row->{$this->countColumn($label)} ?? 0);
            }

            $counts[$key] = $bucket;
        }

        return $this->recordsByDate = $counts;
    }

    /**
     * Summarise the student's madrassa progress for the window.
     *
     * "Prepared" is a day the lesson was written down; "unprepared" is a
     * recorded day it was not. That is what the existing data says and no
     * more: there is no prepared/unprepared column in madrassa_daily_records,
     * so inventing a richer judgement would mean inventing the data behind
     * it.
     *
     * @return array<string, mixed>
     */
    public function progressSummary(): array
    {
        return $this->progressBetween($this->windowStart(), $this->countedEnd());
    }

    /**
     * @return array<string, mixed>
     */
    private function progressBetween(?Carbon $from, ?Carbon $to): array
    {
        $recordType = $this->recordType();
        $labels = array_keys(MadrassaDailyRecord::summaryFieldsFor($recordType));

        $recorded = 0;
        $byLabel = array_fill_keys($labels, 0);

        if ($from !== null && $to !== null) {
            $fromKey = $from->format('Y-m-d');
            $toKey = $to->format('Y-m-d');

            foreach ($this->recordsByDate() as $date => $counts) {
                if ($date < $fromKey || $date > $toKey) {
                    continue;
                }

                $recorded += $counts['recorded_days'] ?? 0;

                foreach ($labels as $label) {
                    $byLabel[$label] += $counts[$label] ?? 0;
                }
            }
        }

        // The lesson label differs by programme: a Hifz day has a Sabaq, a
        // Dars-e-Nizami day has a Lesson. Read from the record type rather
        // than assumed, so both programmes report honestly.
        $lessonLabel = $recordType === MadrassaDailyRecord::TYPE_HIFZ ? 'Sabaq' : 'Lesson';
        $revisionLabel = $recordType === MadrassaDailyRecord::TYPE_HIFZ ? 'Sabqi' : 'Revision';

        $preparedLessons = $byLabel[$lessonLabel] ?? 0;
        $preparedRevision = $byLabel[$revisionLabel] ?? 0;

        return [
            'record_type' => $recordType,
            'recorded_days' => $recorded,
            'lesson_label' => $lessonLabel,
            'revision_label' => $revisionLabel,
            'prepared_lessons' => $preparedLessons,
            'unprepared_lessons' => max(0, $recorded - $preparedLessons),
            'unprepared_revision' => max(0, $recorded - $preparedRevision),
            'manzil' => $byLabel['Manzil'] ?? 0,
            'days_by_label' => $byLabel,
        ];
    }

    /**
     * The student's most recent daily record, whatever the window shows.
     *
     * "Latest lesson" is a fact about the student rather than about the
     * range being viewed, so this ignores the session bounds.
     */
    public function latestRecord(): ?MadrassaDailyRecord
    {
        $ids = $this->historyEnrollmentIds();

        if ($ids === []) {
            return null;
        }

        return MadrassaDailyRecord::query()
            ->whereIn('student_academic_enrollment_id', $ids)
            ->with([
                'studentAcademicEnrollment.academicSession',
                'studentAcademicEnrollment.academicClass',
                'studentAcademicEnrollment.section',
                'teacher',
            ])
            ->inDailyOrder()
            ->first();
    }

    /**
     * The student's daily records, newest first.
     *
     * @return Collection<int, MadrassaDailyRecord>
     */
    public function dailyRecords(int $limit = 400): Collection
    {
        $ids = $this->enrollmentIds();

        if ($ids === []) {
            return collect();
        }

        return MadrassaDailyRecord::query()
            ->whereIn('student_academic_enrollment_id', $ids)
            ->with([
                'studentAcademicEnrollment.academicSession',
                'studentAcademicEnrollment.department',
                'studentAcademicEnrollment.academicClass',
                'studentAcademicEnrollment.section',
                'teacher',
            ])
            ->inDailyOrder()
            ->limit($limit)
            ->get();
    }

    /* ------------------------------------------------------------------ */
    /* Results */
    /* ------------------------------------------------------------------ */

    /**
     * The student's Grand Test results for the reporting session.
     *
     * Keyed by term, and both terms are always present as keys so a term
     * nobody has marked reads as Not Entered rather than going missing.
     * Never combined: a First Term and a Final Term are separate papers.
     *
     * @return array<string, StudentResult|null>
     */
    public function resultsByTerm(): array
    {
        $ids = $this->enrollmentIds();

        $results = $ids === []
            ? collect()
            : StudentResult::query()
                ->whereIn('student_academic_enrollment_id', $ids)
                ->where('test_type', StudentResult::TEST_GRAND)
                ->with([
                    'studentAcademicEnrollment.academicSession',
                    'studentAcademicEnrollment.department',
                    'studentAcademicEnrollment.academicClass',
                    'studentAcademicEnrollment.section',
                ])
                ->get()
                ->keyBy('term');

        $byTerm = [];

        foreach (StudentResult::TERMS as $term) {
            $byTerm[$term] = $results->get($term);
        }

        return $byTerm;
    }

    /**
     * The student's whole madrassa result history, newest first.
     *
     * Across every placement, so being promoted does not start the record
     * over, and each result keeps the enrollment it was recorded against.
     *
     * @return Collection<int, StudentResult>
     */
    public function resultHistory(): Collection
    {
        $ids = $this->historyEnrollmentIds();

        if ($ids === []) {
            return collect();
        }

        return StudentResult::query()
            ->whereIn('student_academic_enrollment_id', $ids)
            ->with([
                'studentAcademicEnrollment.academicSession',
                'studentAcademicEnrollment.department',
                'studentAcademicEnrollment.academicClass',
                'studentAcademicEnrollment.section',
            ])
            ->inResultOrder()
            ->get();
    }

    /**
     * The status a result carries on a report.
     *
     * GradeScale decides which grades pass, so this module never states a
     * second opinion about it. An unmarked paper is neither passed nor
     * failed.
     */
    public static function statusFor(?StudentResult $result): string
    {
        if ($result === null) {
            return 'Not Entered';
        }

        return GradeScale::isPassing($result->grade) ? 'Passed' : 'Failed';
    }

    /* ------------------------------------------------------------------ */
    /* Month by month */
    /* ------------------------------------------------------------------ */

    /**
     * One row per month the reporting session covers.
     *
     * Every figure is bounded three ways: by the session, by the student's
     * own enrollment dates, and by today when the session is still running.
     * A month still to come therefore reports zero days rather than a year
     * of absences, and a student who joined in October has no September.
     *
     * @return array<int, array<string, mixed>>
     */
    public function months(): array
    {
        $start = $this->windowStart();
        $sessionEnd = $this->sessionEnd();
        $countedEnd = $this->countedEnd();

        if ($start === null || $sessionEnd === null || $countedEnd === null) {
            return [];
        }

        $rows = [];

        $cursor = $start->copy()->startOfMonth();
        $last = $sessionEnd->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($last)) {
            $from = $cursor->copy()->startOfMonth()->max($start);
            // Bounded at today for a running session: the remaining months
            // of the year are listed, but they count nothing.
            $to = $cursor->copy()->endOfMonth()->startOfDay()->min($countedEnd);

            $inWindow = $from->lessThanOrEqualTo($to);

            $attendance = $inWindow ? $this->attendanceBetween($from, $to) : $this->emptyAttendance();
            $progress = $inWindow ? $this->progressBetween($from, $to) : $this->progressBetween(null, null);
            $prayer = $inWindow ? $this->prayersBetween($from, $to) : $this->prayersBetween(null, null);

            $rows[] = [
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
                'in_window' => $inWindow,
                'working_days' => $attendance['working_days'],
                'present' => $attendance['present'],
                'absent' => $attendance['absent'],
                'attendance_percentage' => $attendance['percentage'],
                'prepared_lessons' => $progress['prepared_lessons'],
                'unprepared_lessons' => $progress['unprepared_lessons'],
                'unprepared_revision' => $progress['unprepared_revision'],
                'manzil' => $progress['manzil'],
                'recorded_days' => $progress['recorded_days'],
                'prayer_present' => $prayer['present'],
                'prayer_absent' => $prayer['absent'],
                'prayer_percentage' => $prayer['percentage'],
            ];

            $cursor->addMonth();
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAttendance(): array
    {
        return [
            'working_days' => 0,
            'opportunities' => 0,
            'present' => 0,
            'absent' => 0,
            'recorded' => 0,
            'percentage' => null,
        ];
    }

    /**
     * Turn a summary label into a safe SQL column alias.
     */
    private function countColumn(string $label): string
    {
        return 'days_with_'.strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $label));
    }
}
