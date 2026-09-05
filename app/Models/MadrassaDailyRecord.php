<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * What one madrassa student did on one day.
 *
 * Not attendance and not a result. Attendance answers whether the student
 * was in the room; this answers what they read, memorised and revised while
 * they were there. The two modules are kept apart on purpose: neither reads
 * the other's rows.
 *
 * The record belongs to a student_academic_enrollments row rather than to a
 * student, for the same reason attendance does. A Hifz + School student
 * holds one madrassa and one school enrollment at once, and only the
 * madrassa one has a daily record. Everything about the placement -
 * session, department, class, section - is read back through that id, so a
 * record stays historically correct after the student is promoted.
 *
 * The rules about what may be recorded live here, so the controller, the
 * form requests and the tests all read them from one place.
 */
class MadrassaDailyRecord extends Model
{
    use HasFactory;

    public const TYPE_HIFZ = 'Hifz';

    public const TYPE_DARS_E_NIZAMI = 'Dars-e-Nizami';

    /**
     * The kinds of daily record this table holds.
     *
     * @var array<int, string>
     */
    public const RECORD_TYPES = [
        self::TYPE_HIFZ,
        self::TYPE_DARS_E_NIZAMI,
    ];

    /**
     * The only track that has a daily academic record.
     */
    public const ACADEMIC_TRACK = 'Madrassa';

    /**
     * Which daily record a student's programme is kept on.
     *
     * Keyed by the students table's student_type enum. "School" is
     * deliberately absent: a school-only student has no madrassa day to
     * record, and a missing key is what makes that a rejection rather than
     * a guess.
     *
     * @var array<string, string>
     */
    public const RECORD_TYPE_BY_STUDENT_TYPE = [
        'Hifz' => self::TYPE_HIFZ,
        'Hifz + School' => self::TYPE_HIFZ,
        'Dars-e-Nizami' => self::TYPE_DARS_E_NIZAMI,
        'Dars-e-Nizami + Computer' => self::TYPE_DARS_E_NIZAMI,
    ];

    /**
     * The work columns each record type fills in.
     *
     * A record only ever writes its own type's columns. The other type's
     * stay null, which is what lets one table serve both programmes
     * without either one having to pretend its day looks like the other's.
     *
     * @var array<string, array<int, string>>
     */
    public const WORK_FIELDS_BY_TYPE = [
        self::TYPE_HIFZ => [
            'sabaq',
            'sabaq_quantity',
            'sabqi',
            'sabqi_quantity',
            'manzil',
            'manzil_quantity',
            'next_sabaq',
        ],
        self::TYPE_DARS_E_NIZAMI => [
            'subject_book',
            'todays_lesson',
            'lesson_topic_covered',
            'revision',
            'next_lesson',
        ],
    ];

    /**
     * How each work column is labelled in the interface.
     *
     * Held here rather than repeated across the form, the detail page and
     * the listing, so the three can never drift apart.
     *
     * @var array<string, string>
     */
    public const WORK_FIELD_LABELS = [
        'sabaq' => 'Sabaq',
        'sabaq_quantity' => 'Sabaq Quantity',
        'sabqi' => 'Sabqi / Revision',
        'sabqi_quantity' => 'Sabqi Quantity',
        'manzil' => 'Manzil',
        'manzil_quantity' => 'Manzil Quantity',
        'next_sabaq' => "Tomorrow's Sabaq",
        'subject_book' => 'Subject / Book',
        'todays_lesson' => "Today's Lesson",
        'lesson_topic_covered' => 'Lesson / Topic Covered',
        'revision' => 'Revision',
        'next_lesson' => "Tomorrow's Lesson",
    ];

    /**
     * The four lines a listing row shows, and where each one reads from.
     *
     * Ordered, and each line names its columns in preference order: a Hifz
     * row leads with the quantity because that is what a register is
     * scanned for, and falls back to the lesson itself when no quantity was
     * entered. The labels are shorter than the form's on purpose - a table
     * cell is not a form field.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    public const SUMMARY_FIELDS_BY_TYPE = [
        self::TYPE_HIFZ => [
            'Sabaq' => ['sabaq_quantity', 'sabaq'],
            'Sabqi' => ['sabqi_quantity', 'sabqi'],
            'Manzil' => ['manzil_quantity', 'manzil'],
            'Tomorrow' => ['next_sabaq'],
        ],
        self::TYPE_DARS_E_NIZAMI => [
            'Subject' => ['subject_book'],
            'Lesson' => ['todays_lesson', 'lesson_topic_covered'],
            'Revision' => ['revision'],
            'Tomorrow' => ['next_lesson'],
        ],
    ];

    /**
     * The attributes that are mass assignable.
     *
     * record_type is on the list because the write paths resolve it from
     * the enrollment's student rather than from the form. What arrives in
     * the request is only ever used to be checked against that.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_academic_enrollment_id',
        'record_date',
        'record_type',
        'teacher_id',
        'sabaq',
        'sabaq_quantity',
        'sabqi',
        'sabqi_quantity',
        'manzil',
        'manzil_quantity',
        'next_sabaq',
        'subject_book',
        'todays_lesson',
        'lesson_topic_covered',
        'revision',
        'next_lesson',
        'remarks',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'record_date' => 'date',
        ];
    }

    /**
     * Keep the stored record date a plain calendar date.
     *
     * Without this the date cast hands the driver a full timestamp, which
     * MySQL truncates to a DATE and SQLite stores verbatim. The two would
     * then disagree about whether a lookup matches and whether the unique
     * index has been hit. The same normalisation StudentAttendance does,
     * for the same reason.
     */
    protected function recordDate(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null ? null : StudentAttendance::normalizeDate($value),
        );
    }

    /**
     * Get the enrollment this day's work was recorded against.
     */
    public function studentAcademicEnrollment()
    {
        return $this->belongsTo(StudentAcademicEnrollment::class);
    }

    /**
     * Get the teacher who recorded the day, if one was named.
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the student, through the enrollment.
     *
     * Convenience for a single record. Listings eager load
     * studentAcademicEnrollment.student instead, which reaches the same
     * row without a second query per record.
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
     * Work out which daily record a student's programme is kept on.
     *
     * Null for a programme that has none, which every write path treats as
     * a rejection. Nothing is guessed from the department name: the
     * student_type column is a fixed enum, department names are master
     * data an administrator may rename.
     */
    public static function recordTypeForStudentType(?string $studentType): ?string
    {
        return self::RECORD_TYPE_BY_STUDENT_TYPE[$studentType] ?? null;
    }

    /**
     * Work out which daily record an enrollment's student is kept on.
     *
     * The track is checked first: a school enrollment has no daily record
     * whatever its student's programme says, which is what keeps the
     * madrassa side of a Hifz + School student separate from the school
     * side.
     */
    public static function recordTypeForEnrollment(?StudentAcademicEnrollment $enrollment): ?string
    {
        if ($enrollment === null || $enrollment->academic_track !== self::ACADEMIC_TRACK) {
            return null;
        }

        return self::recordTypeForStudentType($enrollment->student?->student_type);
    }

    /**
     * Get the work columns a record type fills in.
     *
     * An unrecognised type fills in nothing, so a bad value can never widen
     * what is accepted.
     *
     * @return array<int, string>
     */
    public static function workFieldsFor(?string $recordType): array
    {
        return self::WORK_FIELDS_BY_TYPE[$recordType] ?? [];
    }

    /**
     * Get the programmes a record type covers.
     *
     * The reverse of RECORD_TYPE_BY_STUDENT_TYPE: one record type serves
     * both the single-track and the combined programme, so Hifz covers
     * "Hifz" and "Hifz + School". An unrecognised type covers nothing,
     * which is what stops a hand-edited filter from widening a report.
     *
     * @return array<int, string>
     */
    public static function studentTypesFor(?string $recordType): array
    {
        return array_keys(array_filter(
            self::RECORD_TYPE_BY_STUDENT_TYPE,
            fn ($type) => $type === $recordType
        ));
    }

    /**
     * Get the record type a report should be built for.
     *
     * Falls back to Hifz rather than to nothing: the module is the Hifz &
     * Quran module, and a report with no shape at all would be a blank page
     * rather than a sensible default. A value the enum does not know is
     * never passed through.
     */
    public static function reportableRecordType(mixed $requested): string
    {
        return in_array($requested, self::RECORD_TYPES, true)
            ? $requested
            : self::TYPE_HIFZ;
    }

    /**
     * Get the work columns a record type must leave alone.
     *
     * @return array<int, string>
     */
    public static function foreignWorkFieldsFor(?string $recordType): array
    {
        $own = self::workFieldsFor($recordType);

        $all = array_merge(...array_values(self::WORK_FIELDS_BY_TYPE));

        return array_values(array_diff($all, $own));
    }

    /**
     * Determine whether a date may hold a daily record.
     *
     * Sunday is the weekly off day, so there is no day's work to record on
     * them. The rule is read from StudentAttendance rather than restated
     * here: the institution runs one week, and two copies of it would
     * eventually disagree. Nothing about a student's attendance is read -
     * only which days of the week the institution sits.
     */
    public static function isRecordableDay(mixed $date): bool
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
     * Reduce a date to the Y-m-d form the column stores, or null.
     *
     * Used by the filters, so that rubbish in a query string narrows
     * nothing rather than throwing.
     */
    public static function normalizeRecordDate(mixed $date): ?string
    {
        return StudentAttendance::normalizeDate($date);
    }

    /**
     * Determine whether a date falls inside an enrollment's period.
     *
     * Nothing may be recorded before the placement started, nor after it
     * ended when an end date exists. An open-ended enrollment has no upper
     * bound, which is the normal case for a current placement.
     */
    public static function isWithinEnrollmentPeriod(mixed $date, StudentAcademicEnrollment $enrollment): bool
    {
        $normalized = StudentAttendance::normalizeDate($date);

        if ($normalized === null) {
            return false;
        }

        $start = $enrollment->start_date?->format('Y-m-d');
        $end = $enrollment->end_date?->format('Y-m-d');

        if ($start !== null && $normalized < $start) {
            return false;
        }

        return $end === null || $normalized <= $end;
    }

    /**
     * Get the day's work as label => value pairs, skipping what is blank.
     *
     * Only the record's own type's columns are read, so a Dars-e-Nizami
     * record can never display an empty Sabaq row.
     *
     * @return array<string, string>
     */
    public function workEntries(): array
    {
        $entries = [];

        foreach (self::workFieldsFor($this->record_type) as $field) {
            $value = $this->{$field};

            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $entries[self::WORK_FIELD_LABELS[$field] ?? $field] = $value;
        }

        return $entries;
    }

    /**
     * Get the day's work reduced to the four lines a listing shows.
     *
     * Each line takes the first of its columns that was filled in, so a
     * Hifz row reads "Sabaq: 1 page" from the quantity when there is one
     * and falls back to the lesson itself when there is not. A blank line
     * is left out rather than shown empty.
     *
     * Nothing is added up or converted. "1 page", "half page" and
     * "1/2 para" are shown exactly as the teacher wrote them.
     *
     * @return array<string, string>
     */
    public function workHighlights(): array
    {
        $highlights = [];

        foreach (self::SUMMARY_FIELDS_BY_TYPE[$this->record_type] ?? [] as $label => $fields) {
            foreach ($fields as $field) {
                $value = $this->{$field};

                if ($value !== null && trim((string) $value) !== '') {
                    $highlights[$label] = $value;

                    break;
                }
            }
        }

        return $highlights;
    }

    /**
     * Summarise the day's work as one line of text.
     *
     * The full record is a page of its own; this is the compact form, for
     * places that cannot lay out the highlights as separate lines.
     */
    public function workSummary(): string
    {
        $highlights = $this->workHighlights();

        if ($highlights === []) {
            return 'No work recorded';
        }

        return implode(', ', array_map(
            fn ($label, $value) => "{$label}: {$value}",
            array_keys($highlights),
            $highlights
        ));
    }

    /**
     * Build the SQL that counts the days one kind of work was recorded on.
     *
     * A day counts when any of the columns behind a summary label carries
     * something, so "days with Sabaq" includes a day where only the
     * quantity was written down. Blank strings do not count: an untouched
     * text box is not a day's work.
     *
     * This is a count of days, never a sum of what was written on them. The
     * quantities are free text - "1 page", "half page", "1/2 para" - and
     * this module deliberately performs no arithmetic on them.
     *
     * The column names come from WORK_FIELDS_BY_TYPE, never from a request,
     * so there is nothing here for a query string to reach.
     *
     * @param  array<int, string>  $fields
     */
    public static function recordedDaysExpression(array $fields): string
    {
        $candidates = array_map(
            fn ($field) => "nullif(trim(madrassa_daily_records.{$field}), '')",
            $fields
        );

        // coalesce() needs at least two arguments in SQLite, and a label
        // backed by a single column has only one. Wrapping it anyway would
        // work on MySQL and fail in the test suite.
        $filled = count($candidates) === 1
            ? $candidates[0]
            : 'coalesce('.implode(', ', $candidates).')';

        return "sum(case when {$filled} is not null then 1 else 0 end)";
    }

    /**
     * Get the summary labels a record type is counted by.
     *
     * The same four lines the listings show, reused so a report and a table
     * row can never disagree about what "has a Sabaq" means.
     *
     * @return array<string, array<int, string>>
     */
    public static function summaryFieldsFor(?string $recordType): array
    {
        return self::SUMMARY_FIELDS_BY_TYPE[$recordType] ?? [];
    }

    /**
     * Get the Tailwind badge classes for the record type.
     */
    public function recordTypeBadgeClasses(): string
    {
        return match ($this->record_type) {
            self::TYPE_HIFZ => 'bg-emerald-100 text-emerald-800',
            self::TYPE_DARS_E_NIZAMI => 'bg-indigo-100 text-indigo-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Order records the way a daily register is read: newest day first.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInDailyOrder($query)
    {
        return $query->orderByDesc('record_date')->orderByDesc('id');
    }

    /**
     * Restrict a query to one student's madrassa records.
     *
     * The track is part of the condition, not an assumption. Records can
     * only be written against a madrassa enrollment in the first place, but
     * a student's history must be provably free of the school side rather
     * than free of it by luck.
     *
     * Written as a condition on the enrollment rather than a join, so it
     * composes with the other filters and can only ever narrow them.
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

    /**
     * Summarise a student's daily records for their profile.
     *
     * Null when the student has no current madrassa placement or is on a
     * programme this module keeps no record for, which is what decides
     * whether the profile shows the section at all. A school-only student
     * gets nothing rather than an empty panel.
     *
     * The counts span every madrassa enrollment the student has ever held,
     * not just the current one: being promoted does not start the history
     * over. The school side is excluded by the scope above.
     *
     * @return array<string, mixed>|null
     */
    public static function summaryForStudent(Student $student): ?array
    {
        $enrollment = $student->activeEnrollmentForTrack(self::ACADEMIC_TRACK);

        if ($enrollment === null) {
            return null;
        }

        // Handed the student we already have rather than letting the
        // relation fetch the same row again.
        $enrollment->setRelation('student', $student);

        $recordType = self::recordTypeForEnrollment($enrollment);

        if ($recordType === null) {
            return null;
        }

        // One row of aggregates rather than a count and a max separately.
        $totals = self::forStudent($student->id)
            ->selectRaw('count(*) as total, max(record_date) as latest_date')
            ->first();

        return [
            'enrollment' => $enrollment,
            'record_type' => $recordType,
            'total' => (int) ($totals->total ?? 0),
            'latest_date' => $totals?->latest_date === null
                ? null
                : Carbon::parse($totals->latest_date),
        ];
    }
}
