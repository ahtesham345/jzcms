<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One student's attendance for one enrollment, date and period.
 *
 * The row belongs to a student_academic_enrollments record rather than to a
 * student. That is the whole point: a Hifz + School student holds one
 * madrassa and one school enrollment in the same session, and marking one
 * of them must never touch the other.
 *
 * One row is still one student on one day in one period, because that is
 * what the paper register holds. The monthly sheet in the interface is only
 * how a month of those rows is transcribed at once; nothing is stored per
 * month.
 *
 * The rules that decide what may be recorded live here so the controller,
 * the request and the tests all read them from one place.
 */
class StudentAttendance extends Model
{
    use HasFactory;

    public const STATUS_PRESENT = 'Present';

    public const STATUS_ABSENT = 'Absent';

    /**
     * The selectable attendance statuses.
     *
     * Present and Absent only. Late, Leave and Holiday are deliberately not
     * here: they change what a day means, not just who was in the room.
     *
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
    ];

    public const PERIOD_MORNING = 'Morning';

    public const PERIOD_AFTERNOON = 'Afternoon';

    public const PERIOD_EVENING = 'Evening';

    /**
     * Every period the table can store.
     *
     * @var array<int, string>
     */
    public const ATTENDANCE_PERIODS = [
        self::PERIOD_MORNING,
        self::PERIOD_AFTERNOON,
        self::PERIOD_EVENING,
    ];

    /**
     * The periods each academic track actually runs.
     *
     * Madrassa sits three times a day; school registers once, in the
     * morning. The track is always read from the enrollment, never from
     * whatever the browser submitted.
     *
     * @var array<string, array<int, string>>
     */
    public const PERIODS_BY_TRACK = [
        'Madrassa' => [self::PERIOD_MORNING, self::PERIOD_AFTERNOON, self::PERIOD_EVENING],
        'School' => [self::PERIOD_MORNING],
    ];

    /**
     * The days no attendance is taken, as Carbon day-of-week numbers.
     *
     * Saturday and Sunday are off for both tracks, so no row exists for
     * them at all rather than a row marked as a holiday.
     *
     * @var array<int, int>
     */
    public const OFF_DAYS = [
        CarbonInterface::SATURDAY,
        CarbonInterface::SUNDAY,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_academic_enrollment_id',
        'attendance_date',
        'attendance_period',
        'status',
        'absence_reason',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
        ];
    }

    /**
     * Keep the stored attendance date a plain calendar date.
     *
     * Without this the date cast would hand the driver a full timestamp,
     * which MySQL truncates to a DATE and SQLite stores verbatim. The two
     * would then disagree about whether a lookup matches and whether the
     * unique index has been hit, so the value is normalised on the way in.
     */
    protected function attendanceDate(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null ? null : self::normalizeDate($value),
        );
    }

    /**
     * Get the enrollment this attendance was recorded against.
     */
    public function studentAcademicEnrollment()
    {
        return $this->belongsTo(StudentAcademicEnrollment::class);
    }

    /**
     * Get the student, through the enrollment.
     */
    public function student()
    {
        return $this->hasOneThrough(
            Student::class,
            StudentAcademicEnrollment::class,
            'id',
            'id',
            'student_academic_enrollment_id',
            'student_id'
        );
    }

    /**
     * Get the academic session, through the enrollment.
     */
    public function academicSession()
    {
        return $this->hasOneThrough(
            AcademicSession::class,
            StudentAcademicEnrollment::class,
            'id',
            'id',
            'student_academic_enrollment_id',
            'academic_session_id'
        );
    }

    /**
     * Get the department, through the enrollment.
     */
    public function department()
    {
        return $this->hasOneThrough(
            Department::class,
            StudentAcademicEnrollment::class,
            'id',
            'id',
            'student_academic_enrollment_id',
            'department_id'
        );
    }

    /**
     * Get the class, through the enrollment.
     */
    public function academicClass()
    {
        return $this->hasOneThrough(
            AcademicClass::class,
            StudentAcademicEnrollment::class,
            'id',
            'id',
            'student_academic_enrollment_id',
            'academic_class_id'
        );
    }

    /**
     * Get the section, through the enrollment.
     *
     * Null for a class run without sections, the same as the enrollment.
     */
    public function section()
    {
        return $this->hasOneThrough(
            Section::class,
            StudentAcademicEnrollment::class,
            'id',
            'id',
            'student_academic_enrollment_id',
            'section_id'
        );
    }

    /**
     * Get the periods a track runs.
     *
     * An unrecognised track runs nothing, so a bad value cannot widen what
     * is accepted.
     *
     * @return array<int, string>
     */
    public static function periodsForTrack(?string $track): array
    {
        return self::PERIODS_BY_TRACK[$track] ?? [];
    }

    /**
     * Determine whether a track may be marked for a period.
     */
    public static function periodAllowedForTrack(?string $period, ?string $track): bool
    {
        return in_array($period, self::periodsForTrack($track), true);
    }

    /**
     * Determine whether a date is a teaching day.
     *
     * Saturday and Sunday are off for both tracks. An unparseable date is
     * not a teaching day either: it can never be one.
     */
    public static function isAttendanceDay(mixed $date): bool
    {
        $parsed = self::parseDate($date);

        return $parsed !== null && ! in_array($parsed->dayOfWeek, self::OFF_DAYS, true);
    }

    /**
     * Get the name of the off day a date falls on, or null on a teaching day.
     */
    public static function offDayName(mixed $date): ?string
    {
        $parsed = self::parseDate($date);

        if ($parsed === null || ! in_array($parsed->dayOfWeek, self::OFF_DAYS, true)) {
            return null;
        }

        return $parsed->format('l');
    }

    /**
     * Reduce a date to the Y-m-d form the column stores.
     */
    public static function normalizeDate(mixed $date): ?string
    {
        return self::parseDate($date)?->format('Y-m-d');
    }

    /**
     * Parse a date without throwing on rubbish.
     */
    private static function parseDate(mixed $date): ?Carbon
    {
        if ($date instanceof CarbonInterface) {
            return Carbon::instance($date);
        }

        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Build the calendar the monthly sheet is drawn from.
     *
     * The month decides its own length, so February 2028 is 29 columns and
     * April is 30. Weekends are carried as off days rather than left out,
     * because the sheet still has to show them as OFF.
     *
     * @return array<int, array{date: string, day: int, weekday: string, is_off_day: bool}>
     */
    public static function monthDays(int $year, int $month): array
    {
        $cursor = Carbon::create($year, $month, 1)->startOfMonth();
        $days = [];

        foreach (range(1, $cursor->daysInMonth) as $day) {
            $date = $cursor->copy()->day($day);

            $days[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $day,
                'weekday' => $date->format('D'),
                'is_off_day' => in_array($date->dayOfWeek, self::OFF_DAYS, true),
            ];
        }

        return $days;
    }

    /**
     * Count the teaching days in a month.
     */
    public static function teachingDaysInMonth(int $year, int $month): int
    {
        return count(array_filter(self::monthDays($year, $month), fn ($day) => ! $day['is_off_day']));
    }

    /**
     * Determine whether a date falls inside a month.
     *
     * The sheet may only write the month it was drawn for, so a row naming
     * any other date is rejected rather than quietly saved elsewhere.
     */
    public static function isWithinMonth(mixed $date, int $year, int $month): bool
    {
        $parsed = self::parseDate($date);

        return $parsed !== null
            && (int) $parsed->year === $year
            && (int) $parsed->month === $month;
    }

    /**
     * Build the key one cell of the monthly sheet is held under.
     *
     * Enrollment and date together, because one sheet holds a month of days
     * for every student on it.
     */
    public static function cellKey(mixed $enrollmentId, mixed $date): string
    {
        return ((int) $enrollmentId).'|'.self::normalizeDate($date);
    }

    /**
     * Get a month of attendance already on file, keyed by cell.
     *
     * One query for the whole month rather than one per student or per day.
     *
     * @param  array<int, int|string>  $enrollmentIds
     * @return Collection<string, self>
     */
    public static function forMonth(array $enrollmentIds, int $year, int $month, string $attendancePeriod)
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();

        return self::query()
            ->whereIn('student_academic_enrollment_id', $enrollmentIds)
            ->where('attendance_period', $attendancePeriod)
            ->whereBetween('attendance_date', [
                $start->format('Y-m-d'),
                $start->copy()->endOfMonth()->format('Y-m-d'),
            ])
            ->get()
            ->keyBy(fn (self $attendance) => self::cellKey(
                $attendance->student_academic_enrollment_id,
                $attendance->attendance_date
            ));
    }

    /**
     * Save the marked cells of a monthly sheet.
     *
     * One transaction for the submission: the paper sheet is transcribed as
     * a unit, so a row that cannot be written leaves the month as it was
     * rather than partly rewritten.
     *
     * Only the cells handed in are touched. A month is digitised over
     * several sittings, so days the administrator has not reached yet are
     * simply absent from the rows and stay unmarked; nothing here fills
     * them in. Cells already on file are updated in place, which is what
     * keeps re-saving an edit rather than a duplicate.
     *
     * The caller is responsible for having validated the rows. This method
     * is the writer, not the gate.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return int the number of records written
     */
    public static function recordSheet(string $attendancePeriod, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        return DB::transaction(function () use ($attendancePeriod, $rows) {
            $existing = self::existingFor($attendancePeriod, $rows);

            foreach ($rows as $row) {
                $status = $row['status'];
                $date = self::normalizeDate($row['attendance_date']);

                $attributes = [
                    'status' => $status,
                    // A reason only ever describes an absence. Clearing it
                    // on Present stops a reason from outliving the absence
                    // it explained when a mark is corrected.
                    'absence_reason' => $status === self::STATUS_ABSENT
                        ? ($row['absence_reason'] ?? null)
                        : null,
                ];

                // Only touched when the caller sent it, so a sheet that has
                // no notes field does not wipe notes added elsewhere.
                if (array_key_exists('notes', $row)) {
                    $attributes['notes'] = $row['notes'];
                }

                $record = $existing->get(self::cellKey($row['student_academic_enrollment_id'], $date));

                if ($record !== null) {
                    $record->update($attributes);

                    continue;
                }

                self::create($attributes + [
                    'student_academic_enrollment_id' => $row['student_academic_enrollment_id'],
                    'attendance_date' => $date,
                    'attendance_period' => $attendancePeriod,
                ]);
            }

            return count($rows);
        });
    }

    /**
     * Load the rows a submission may be updating, in one query.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<string, self>
     */
    private static function existingFor(string $attendancePeriod, array $rows)
    {
        $enrollmentIds = array_values(array_unique(array_map(
            fn ($row) => (int) $row['student_academic_enrollment_id'],
            $rows
        )));

        $dates = array_values(array_unique(array_map(
            fn ($row) => self::normalizeDate($row['attendance_date']),
            $rows
        )));

        return self::query()
            ->whereIn('student_academic_enrollment_id', $enrollmentIds)
            ->whereIn('attendance_date', $dates)
            ->where('attendance_period', $attendancePeriod)
            ->get()
            ->keyBy(fn (self $attendance) => self::cellKey(
                $attendance->student_academic_enrollment_id,
                $attendance->attendance_date
            ));
    }

    /**
     * Order records the way a history is read.
     *
     * Newest day first, and within a day the periods in the order they are
     * sat rather than alphabetically: Morning, Afternoon, Evening. The
     * column is an enum of names, so the order has to be spelled out.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInHistoryOrder($query)
    {
        $cases = [];
        $bindings = [];

        foreach (self::ATTENDANCE_PERIODS as $position => $period) {
            $cases[] = 'when ? then '.($position + 1);
            $bindings[] = $period;
        }

        return $query
            ->orderByDesc('attendance_date')
            ->orderByRaw(
                'case attendance_period '.implode(' ', $cases).' else '.(count(self::ATTENDANCE_PERIODS) + 1).' end',
                $bindings
            );
    }

    /**
     * Count the teaching days in a range, both ends included.
     *
     * Saturdays and Sundays are off, so a range is mostly whole weeks of
     * five. Counted arithmetically rather than by walking every date: a
     * whole academic year is asked for once per student.
     */
    public static function teachingDaysBetween(mixed $start, mixed $end): int
    {
        $from = self::parseDate($start)?->startOfDay();
        $to = self::parseDate($end)?->startOfDay();

        if ($from === null || $to === null || $from->greaterThan($to)) {
            return 0;
        }

        $days = (int) $from->diffInDays($to) + 1;
        $wholeWeeks = intdiv($days, 7);

        $teachingDays = $wholeWeeks * (7 - count(self::OFF_DAYS));

        // Whatever is left over after the whole weeks, at most six days.
        $cursor = $from->copy()->addDays($wholeWeeks * 7);

        while ($cursor->lessThanOrEqualTo($to)) {
            if (! in_array($cursor->dayOfWeek, self::OFF_DAYS, true)) {
                $teachingDays++;
            }

            $cursor->addDay();
        }

        return $teachingDays;
    }

    /**
     * Turn teaching days into the number of registers that could be marked.
     *
     * A school day is one register; a madrassa day is three. This is what
     * keeps "twenty days" and "sixty attendance opportunities" from being
     * confused with each other.
     */
    public static function opportunitiesForTrack(int $teachingDays, ?string $track): int
    {
        return $teachingDays * count(self::periodsForTrack($track));
    }

    /**
     * Work out an attendance percentage from what was actually recorded.
     *
     * The denominator is the records on file, never the calendar. A month
     * is transcribed from paper over several sittings, so a day with no
     * row is a day nobody has entered yet: counting it as an absence would
     * punish the student for the office being behind, and counting it as a
     * present would invent attendance nobody witnessed.
     *
     * Null when nothing has been recorded, which the interface shows as
     * N/A rather than as zero.
     */
    public static function attendancePercentage(int $present, int $absent): ?float
    {
        $recorded = $present + $absent;

        if ($recorded === 0) {
            return null;
        }

        return round($present / $recorded * 100, 2);
    }

    /**
     * Format an attendance percentage for display.
     */
    public static function formatPercentage(int $present, int $absent): string
    {
        $percentage = self::attendancePercentage($present, $absent);

        return $percentage === null ? 'N/A' : number_format($percentage, 2).'%';
    }

    /**
     * Determine whether this record marks the student present.
     */
    public function isPresent(): bool
    {
        return $this->status === self::STATUS_PRESENT;
    }

    /**
     * Get the Tailwind badge classes for the attendance status.
     */
    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            self::STATUS_PRESENT => 'bg-green-100 text-green-800',
            self::STATUS_ABSENT => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
