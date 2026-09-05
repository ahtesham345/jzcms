<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One student's attendance at one of the five daily prayers.
 *
 * A separate register from student_attendances, kept in its own table. The
 * academic one answers whether the student was in class; this one answers
 * whether they stood for Fajr. Neither module reads the other's rows, and
 * nothing here changes how academic attendance works.
 *
 * Madrassa only. The prayers are the madrassa's register, so every row
 * belongs to a Madrassa enrollment and the write path refuses anything
 * else. A Hifz + School student is reached through their madrassa
 * enrollment; their school one is a different row and can never be named
 * here.
 *
 * The row belongs to a student_academic_enrollments record rather than to a
 * student, for the same reason academic attendance does: the session,
 * department, class and section are read back through that one id, so a
 * record keeps the placement it was made under after a promotion.
 *
 * One row is still one student, one day and one prayer, because that is
 * what the paper register holds. The monthly sheet in the interface is only
 * how a month of those rows is transcribed at once; nothing is stored per
 * month.
 */
class StudentPrayerAttendance extends Model
{
    use HasFactory;

    public const PRAYER_FAJR = 'Fajr';

    public const PRAYER_ZUHR = 'Zuhr';

    public const PRAYER_ASR = 'Asr';

    public const PRAYER_MAGHRIB = 'Maghrib';

    public const PRAYER_ISHA = 'Isha';

    /**
     * The five daily prayers, in the order they are prayed.
     *
     * Spelled out rather than read from the enum column, which would sort
     * them alphabetically and put Asr before Fajr.
     *
     * @var array<int, string>
     */
    public const PRAYERS = [
        self::PRAYER_FAJR,
        self::PRAYER_ZUHR,
        self::PRAYER_ASR,
        self::PRAYER_MAGHRIB,
        self::PRAYER_ISHA,
    ];

    /**
     * The single letter each prayer is shown as on the monthly grid.
     *
     * A month of five prayers is too many columns for full names, and the
     * paper register uses initials too.
     *
     * @var array<string, string>
     */
    public const PRAYER_INITIALS = [
        self::PRAYER_FAJR => 'F',
        self::PRAYER_ZUHR => 'Z',
        self::PRAYER_ASR => 'A',
        self::PRAYER_MAGHRIB => 'M',
        self::PRAYER_ISHA => 'I',
    ];

    public const STATUS_PRESENT = 'Present';

    public const STATUS_ABSENT = 'Absent';

    /**
     * The statuses the column can store.
     *
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
    ];

    /**
     * The instruction a sheet sends to clear a prayer it had marked.
     *
     * Never stored. "Unmarked" is the absence of a row, not a third status:
     * a prayer nobody has transcribed yet must not be storable as a
     * judgement about the student. Sending it removes whatever was on file,
     * which is how a mark entered against the wrong row is taken back.
     */
    public const STATUS_UNMARKED = 'Unmarked';

    /**
     * Everything a sheet may submit as a cell's status.
     *
     * @var array<int, string>
     */
    public const SUBMITTABLE_STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
        self::STATUS_UNMARKED,
    ];

    /**
     * The only academic track that keeps a prayer register.
     */
    public const ACADEMIC_TRACK = 'Madrassa';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_academic_enrollment_id',
        'attendance_date',
        'prayer',
        'status',
        'absence_reason',
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
     * unique index has been hit. The same normalisation the academic
     * register does, for the same reason.
     */
    protected function attendanceDate(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null ? null : self::normalizeDate($value),
        );
    }

    /**
     * Get the enrollment this prayer was recorded against.
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

    /* ------------------------------------------------------------------ */
    /* Reading a history */
    /* ------------------------------------------------------------------ */

    /**
     * Order records the way a prayer history is read.
     *
     * Newest day first, and within a day the prayers in the order they are
     * prayed rather than alphabetically: Fajr, Zuhr, Asr, Maghrib, Isha.
     * The column is an enum of names, so the order has to be spelled out -
     * left to the database, Asr would come before Fajr.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInPrayerOrder($query)
    {
        $cases = [];
        $bindings = [];

        foreach (self::PRAYERS as $position => $prayer) {
            $cases[] = 'when ? then '.($position + 1);
            $bindings[] = $prayer;
        }

        return $query
            ->orderByDesc('attendance_date')
            ->orderByRaw(
                'case prayer '.implode(' ', $cases).' else '.(count(self::PRAYERS) + 1).' end',
                $bindings
            );
    }

    /**
     * Restrict a query to one student's madrassa prayer records.
     *
     * The track is part of the condition, not an assumption. Prayers can
     * only be written against a madrassa enrollment in the first place, but
     * a student's history must be provably free of the school side rather
     * than free of it by luck.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForStudent($query, int $studentId)
    {
        return $query->whereHas(
            'studentAcademicEnrollment',
            fn ($enrollment) => $enrollment
                ->where('student_id', $studentId)
                ->where('academic_track', self::ACADEMIC_TRACK)
        );
    }

    /* ------------------------------------------------------------------ */
    /* The calendar */
    /* ------------------------------------------------------------------ */

    /**
     * Determine whether a date is a day prayers are recorded on.
     *
     * Sunday is the weekly off day across the institution. The rule is read
     * from StudentAttendance rather than restated here: the madrassa runs
     * one weekend, and two copies of that definition would eventually
     * disagree. Nothing about a student's academic attendance is read -
     * only which days of the week the institution sits.
     */
    public static function isPrayerDay(mixed $date): bool
    {
        return StudentAttendance::isAttendanceDay($date);
    }

    /**
     * Get the name of the off day a date falls on, or null on a working day.
     */
    public static function offDayName(mixed $date): ?string
    {
        return StudentAttendance::offDayName($date);
    }

    /**
     * Reduce a date to the Y-m-d form the column stores.
     */
    public static function normalizeDate(mixed $date): ?string
    {
        return StudentAttendance::normalizeDate($date);
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
        return StudentAttendance::monthDays($year, $month);
    }

    /**
     * Count the days in a month prayers can be recorded on.
     */
    public static function prayerDaysInMonth(int $year, int $month): int
    {
        return StudentAttendance::teachingDaysInMonth($year, $month);
    }

    /**
     * Count the prayers a month has room for, per student.
     *
     * Five a day on every working day. The weekly off day is excluded
     * before the multiplication, so a Sunday is never an opportunity the
     * student failed to take - the madrassa simply does not sit.
     */
    public static function expectedPrayersInMonth(int $year, int $month): int
    {
        return self::prayerDaysInMonth($year, $month) * count(self::PRAYERS);
    }

    /**
     * Count the working days in a range, both ends included.
     *
     * Read from the academic register's own arithmetic rather than walked
     * day by day here: the institution runs one weekend, and a session is a
     * whole year of dates to count.
     */
    public static function workingDaysBetween(mixed $start, mixed $end): int
    {
        return StudentAttendance::teachingDaysBetween($start, $end);
    }

    /**
     * Count the working days covered by a set of periods, without repeats.
     *
     * A calendar day counts once however many placements cover it. The
     * periods are merged where they overlap before anything is counted, so
     * a student who somehow held two madrassa placements at the same time
     * is not credited with ten prayers a day.
     *
     * Merged arithmetically rather than by collecting every date: a session
     * is a year, and a set of a few periods should not become a list of
     * three hundred days.
     *
     * @param  array<int, array{0: mixed, 1: mixed}>  $periods
     */
    public static function workingDaysAcrossPeriods(array $periods): int
    {
        $ranges = [];

        foreach ($periods as $period) {
            $start = self::normalizeDate($period[0] ?? null);
            $end = self::normalizeDate($period[1] ?? null);

            // A period that cannot be read, or that ends before it starts,
            // covers nothing at all.
            if ($start === null || $end === null || $start > $end) {
                continue;
            }

            $ranges[] = [$start, $end];
        }

        if ($ranges === []) {
            return 0;
        }

        // Y-m-d sorts and compares correctly as plain text, so no date
        // objects are needed to put these in order.
        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);

        $merged = [];

        foreach ($ranges as $range) {
            $last = count($merged) - 1;

            // Overlaps what is already open, so it extends it rather than
            // starting a new period. Touching-but-not-overlapping ranges
            // are left separate: counted apart they come to the same total.
            if ($last >= 0 && $range[0] <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $range[1]);

                continue;
            }

            $merged[] = $range;
        }

        $days = 0;

        foreach ($merged as [$start, $end]) {
            $days += self::workingDaysBetween($start, $end);
        }

        return $days;
    }

    /**
     * Turn working days into the prayers they offer.
     */
    public static function expectedPrayersForDays(int $workingDays): int
    {
        return $workingDays * count(self::PRAYERS);
    }

    /**
     * Narrow a period to the part of it inside another.
     *
     * Used to clamp a student's enrollment to the session, and then to a
     * month within it. Null when the two do not overlap at all, which is
     * how a placement that ended before the session is excluded rather than
     * counted as zero-length.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function overlappingPeriod(
        mixed $start,
        mixed $end,
        mixed $boundStart,
        mixed $boundEnd
    ): ?array {
        $start = self::normalizeDate($start);
        $end = self::normalizeDate($end);
        $boundStart = self::normalizeDate($boundStart);
        $boundEnd = self::normalizeDate($boundEnd);

        if ($boundStart === null || $boundEnd === null) {
            return null;
        }

        // An open-ended placement runs to the end of whatever bounds it;
        // one with no start began before the bound opened.
        $from = $start === null ? $boundStart : max($start, $boundStart);
        $to = $end === null ? $boundEnd : min($end, $boundEnd);

        return $from > $to ? null : [$from, $to];
    }

    /**
     * Work out an attendance percentage from what was actually recorded.
     *
     * The denominator is the rows on file, never the calendar. A month is
     * transcribed from paper over several sittings, so a prayer with no row
     * is one nobody has entered yet: counting it as an absence would punish
     * the student for the office being behind, and counting it as a present
     * would invent attendance nobody witnessed. Weekends never appear in
     * either column because no row can exist for them.
     *
     * Null when nothing has been recorded, which the interface shows as N/A
     * rather than as zero.
     *
     * Deliberately its own copy of the arithmetic rather than a call into
     * the academic register: the two modules are kept apart, and five lines
     * of division is a smaller price than coupling prayer reports to how
     * class attendance is scored.
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
     * Determine whether a date falls inside a month.
     *
     * The sheet may only write the month it was drawn for, so a row naming
     * any other date is rejected rather than quietly saved elsewhere.
     */
    public static function isWithinMonth(mixed $date, int $year, int $month): bool
    {
        return StudentAttendance::isWithinMonth($date, $year, $month);
    }

    /* ------------------------------------------------------------------ */
    /* The sheet */
    /* ------------------------------------------------------------------ */

    /**
     * Build the key one cell of the monthly sheet is held under.
     *
     * Enrollment, date and prayer together: one sheet holds a month of days
     * for every student on it, and five prayers within every day.
     */
    public static function cellKey(mixed $enrollmentId, mixed $date, string $prayer): string
    {
        return ((int) $enrollmentId).'|'.self::normalizeDate($date).'|'.$prayer;
    }

    /**
     * Get a month of prayer attendance already on file, keyed by cell.
     *
     * One query for the whole month rather than one per student, per day or
     * per prayer.
     *
     * @param  array<int, int|string>  $enrollmentIds
     * @return Collection<string, self>
     */
    public static function forMonth(array $enrollmentIds, int $year, int $month)
    {
        if ($enrollmentIds === []) {
            return new Collection;
        }

        $days = self::monthDays($year, $month);

        return self::query()
            ->whereIn('student_academic_enrollment_id', $enrollmentIds)
            ->whereBetween('attendance_date', [
                $days[0]['date'],
                $days[count($days) - 1]['date'],
            ])
            ->get()
            ->keyBy(fn (self $prayer) => self::cellKey(
                $prayer->student_academic_enrollment_id,
                $prayer->attendance_date,
                $prayer->prayer
            ));
    }

    /**
     * Save the changed cells of a monthly sheet.
     *
     * One transaction for the submission: the paper sheet is transcribed as
     * a unit, so a row that cannot be written leaves the month as it was
     * rather than partly rewritten.
     *
     * Only the cells handed in are touched. A month is digitised over
     * several sittings, so prayers the administrator has not reached yet
     * are simply absent from the rows and stay unmarked; nothing here fills
     * them in, and nothing is ever defaulted to Present. Cells already on
     * file are updated in place, which is what keeps re-saving an edit
     * rather than a duplicate.
     *
     * A cell submitted as Unmarked removes the row it names. That is the
     * only way back from a mark typed against the wrong student, and it
     * leaves the prayer as what it genuinely is - not transcribed - rather
     * than as a judgement nobody made.
     *
     * The caller is responsible for having validated the rows. This method
     * is the writer, not the gate.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{saved: int, cleared: int}
     */
    public static function recordSheet(array $rows): array
    {
        if ($rows === []) {
            return ['saved' => 0, 'cleared' => 0];
        }

        return DB::transaction(function () use ($rows) {
            $existing = self::existingFor($rows);

            $saved = 0;
            $cleared = 0;

            foreach ($rows as $row) {
                $date = self::normalizeDate($row['attendance_date']);
                $key = self::cellKey($row['student_academic_enrollment_id'], $date, $row['prayer']);
                $record = $existing->get($key);
                $status = $row['status'];

                if ($status === self::STATUS_UNMARKED) {
                    // Nothing on file is already unmarked, so this is a
                    // no-op rather than an error.
                    if ($record !== null) {
                        $record->delete();
                        $cleared++;
                    }

                    continue;
                }

                $attributes = [
                    'status' => $status,
                    // A reason only ever describes an absence. Clearing it
                    // on Present is what stops a reason from outliving the
                    // absence it explained when a mark is corrected.
                    'absence_reason' => $status === self::STATUS_ABSENT
                        ? ($row['absence_reason'] ?? null)
                        : null,
                ];

                if ($record !== null) {
                    $record->update($attributes);
                } else {
                    self::create($attributes + [
                        'student_academic_enrollment_id' => $row['student_academic_enrollment_id'],
                        'attendance_date' => $date,
                        'prayer' => $row['prayer'],
                    ]);
                }

                $saved++;
            }

            return ['saved' => $saved, 'cleared' => $cleared];
        });
    }

    /**
     * Load the rows a submission may be updating, in one query.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<string, self>
     */
    private static function existingFor(array $rows)
    {
        $enrollmentIds = array_values(array_unique(array_map(
            fn ($row) => (int) $row['student_academic_enrollment_id'],
            $rows
        )));

        $dates = array_values(array_unique(array_map(
            fn ($row) => self::normalizeDate($row['attendance_date']),
            $rows
        )));

        $prayers = array_values(array_unique(array_map(
            fn ($row) => $row['prayer'],
            $rows
        )));

        return self::query()
            ->whereIn('student_academic_enrollment_id', $enrollmentIds)
            ->whereIn('attendance_date', $dates)
            ->whereIn('prayer', $prayers)
            ->get()
            ->keyBy(fn (self $prayer) => self::cellKey(
                $prayer->student_academic_enrollment_id,
                $prayer->attendance_date,
                $prayer->prayer
            ));
    }

    /* ------------------------------------------------------------------ */
    /* Display */
    /* ------------------------------------------------------------------ */

    /**
     * Get the single letter this prayer is shown as.
     */
    public function initial(): string
    {
        return self::PRAYER_INITIALS[$this->prayer] ?? '?';
    }

    /**
     * Determine whether this record marks the student present.
     */
    public function isPresent(): bool
    {
        return $this->status === self::STATUS_PRESENT;
    }

    /**
     * Get the Tailwind badge classes for the prayer status.
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
