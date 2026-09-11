<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class AdmissionApplication extends Model
{
    use HasFactory;

    /**
     * The selectable student types.
     *
     * @var array<int, string>
     */
    public const STUDENT_TYPES = [
        'Hifz',
        'Hifz + School',
        'School',
        'Dars-e-Nizami + Computer',
        'Dars-e-Nizami',
    ];

    /**
     * The selectable application statuses.
     *
     * @var array<int, string>
     */
    public const STATUSES = [
        'Pending',
        'Under Review',
        'Test Scheduled',
        'Test Completed',
        'Passed',
        'Failed',
        'Approved',
        'Rejected',
    ];

    /**
     * The selectable genders.
     *
     * @var array<int, string>
     */
    public const GENDERS = [
        'Male',
        'Female',
    ];

    /**
     * The selectable admission test results.
     *
     * @var array<int, string>
     */
    public const TEST_RESULTS = [
        'Passed',
        'Failed',
    ];

    /**
     * The status each status may move to.
     *
     * Approved is reachable only through the Approve Admission action, which
     * creates the student; it is never selectable from the status dropdown.
     * Approved and Rejected are terminal.
     *
     * @var array<string, array<int, string>>
     */
    public const STATUS_TRANSITIONS = [
        'Pending' => ['Under Review'],
        'Under Review' => ['Test Scheduled'],
        'Test Scheduled' => ['Test Completed'],
        'Test Completed' => ['Passed', 'Failed'],
        'Passed' => ['Approved'],
        'Failed' => ['Rejected'],
        'Approved' => [],
        'Rejected' => [],
    ];

    /**
     * The statuses during which an admission test result may be recorded.
     *
     * @var array<int, string>
     */
    public const TEST_RESULT_STATUSES = [
        'Test Scheduled',
        'Test Completed',
    ];

    /**
     * The department each student type draws its classes from.
     *
     * Only the department names are mapped here; the classes themselves are
     * always read from the academic_classes table via the department
     * relationship, never hardcoded.
     *
     * @var array<string, array<string, string>>
     */
    public const STUDENT_TYPE_DEPARTMENTS = [
        'Hifz' => ['madrassa' => 'Hifz'],
        'Hifz + School' => ['madrassa' => 'Hifz', 'school' => 'School'],
        'School' => ['school' => 'School'],
        'Dars-e-Nizami' => ['madrassa' => 'Dars-e-Nizami'],
        'Dars-e-Nizami + Computer' => ['madrassa' => 'Dars-e-Nizami', 'computer' => 'Computer'],
    ];

    /**
     * Get the department names a student type needs, keyed by side.
     *
     * @return array<string, string>
     */
    public static function departmentsForStudentType(?string $studentType): array
    {
        return self::STUDENT_TYPE_DEPARTMENTS[$studentType] ?? [];
    }

    /**
     * Get the department name for one side of a student type.
     */
    public static function departmentForSide(?string $studentType, string $side): ?string
    {
        return self::departmentsForStudentType($studentType)[$side] ?? null;
    }

    /**
     * The statuses eligible for bulk test scheduling.
     *
     * Everything else has either already been scheduled or has reached an
     * outcome, so a bulk schedule must not touch it.
     *
     * @var array<int, string>
     */
    public const BULK_SCHEDULABLE_STATUSES = [
        'Pending',
        'Under Review',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'application_number',
        'photo',
        'student_name',
        'father_name',
        'date_of_birth',
        'gender',
        'b_form_number',
        'father_mobile',
        'mother_mobile',
        'permanent_address',
        'current_address',
        'student_type',
        'academic_session_id',
        'madrassa_class_id',
        'school_class_id',
        'admission_date',
        'notes',
        'instructions_accepted',
        'instructions_accepted_at',
        'status',
        'test_date',
        'test_time',
        'test_marks',
        'test_result',
        'test_remarks',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admission_date' => 'date',
            'test_date' => 'date',
            'test_marks' => 'decimal:2',
            'instructions_accepted' => 'boolean',
            'instructions_accepted_at' => 'datetime',
        ];
    }

    /**
     * Create an application, assigning it the next unique application number.
     *
     * The number is allocated inside a transaction that locks the rows it
     * reads so two concurrent submissions cannot derive the same value. The
     * unique index is the final guard: if a collision still slips through, the
     * insert is retried with a freshly allocated number.
     *
     * Shared by the admin form and the public admission form.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createWithApplicationNumber(array $data): self
    {
        // The session is resolved here and never taken from the caller, so a
        // value posted by a browser cannot reach the column whatever route it
        // arrived on. This is the second of two guards: neither admission
        // FormRequest declares a rule for academic_session_id either, so it
        // is already dropped by validated() before it ever gets this far.
        //
        // Both creation paths - the public form and the admin form - go
        // through this method, which is why the stamp lives here rather than
        // in either controller: there is no third way to file an application
        // and so no way to file one into the wrong year.
        unset($data['academic_session_id']);

        $data['academic_session_id'] = AcademicSession::currentId();

        $attempts = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($data) {
                    $data['application_number'] = self::nextApplicationNumber();

                    return self::create($data);
                });
            } catch (UniqueConstraintViolationException $e) {
                if (++$attempts >= 3) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Work out the next application number for the current year.
     */
    public static function nextApplicationNumber(): string
    {
        $year = date('Y');
        $prefix = "APP-{$year}-";

        $lastNumber = self::where('application_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('application_number')
            ->value('application_number');

        $nextNumber = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the student created from this application, if it has been approved.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the academic session this application is applying for.
     *
     * Null on every application filed before the session was recorded. That
     * is a genuine "not known", not a default, and the session-specific
     * notices treat it as such by leaving those applications off.
     */
    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * Get the madrassa class (Hifz or Dars-e-Nizami) applied for.
     */
    public function madrassaClass()
    {
        return $this->belongsTo(AcademicClass::class, 'madrassa_class_id');
    }

    /**
     * Get the school class applied for.
     */
    public function schoolClass()
    {
        return $this->belongsTo(AcademicClass::class, 'school_class_id');
    }

    /**
     * Determine whether this application has already produced a student.
     */
    public function isApproved(): bool
    {
        return $this->student_id !== null;
    }

    /**
     * Determine whether this application is eligible for approval.
     *
     * Only a candidate who passed the test can be admitted, and an
     * application that already has a student must not be approved twice.
     */
    public function canBeApproved(): bool
    {
        return ! $this->isApproved()
            && $this->status === 'Passed'
            && $this->test_result === 'Passed';
    }

    /**
     * Get the statuses a brand new application may be created with.
     *
     * Passed and Failed require a matching admission test result, and Approved
     * requires a student record. None of those can exist at creation time.
     *
     * @return array<int, string>
     */
    public static function creatableStatuses(): array
    {
        return array_values(array_diff(self::STATUSES, ['Passed', 'Failed', 'Approved']));
    }

    /**
     * Determine whether the application is locked against status changes.
     *
     * Once a student has been created from an application its outcome is
     * final; only the descriptive fields may still be corrected.
     */
    public function isLocked(): bool
    {
        return $this->student_id !== null;
    }

    /**
     * Determine whether an admission test result may be recorded right now.
     */
    public function canRecordTestResult(): bool
    {
        return ! $this->isLocked()
            && in_array($this->status, self::TEST_RESULT_STATUSES, true);
    }

    /**
     * Get the statuses an admin may choose in the status dropdown.
     *
     * Always includes the current status (so unrelated fields can be edited
     * without touching it) and excludes Approved, which only the Approve
     * Admission action may set. Passed and Failed are offered only once a
     * matching test result exists, since they must agree with it.
     *
     * @return array<int, string>
     */
    public function selectableStatuses(): array
    {
        if ($this->isLocked()) {
            return [$this->status];
        }

        $next = collect(self::STATUS_TRANSITIONS[$this->status] ?? [])
            ->reject(fn (string $status) => $status === 'Approved')
            ->reject(function (string $status) {
                // Passed/Failed must match the recorded test result.
                return in_array($status, self::TEST_RESULTS, true)
                    && $this->test_result !== $status;
            });

        // Recording a result moves the application straight to the matching
        // status, even while it is still only Test Scheduled.
        if ($this->canRecordTestResult() && $this->test_result !== null) {
            $next->push($this->test_result);
        }

        return $next->prepend($this->status)->unique()->values()->all();
    }

    /**
     * Determine whether an admission test has been scheduled.
     */
    public function hasTestSchedule(): bool
    {
        return $this->test_date !== null || $this->test_time !== null;
    }

    /**
     * Get the test time in the H:i format an <input type="time"> expects.
     *
     * The column is a SQL TIME, which comes back as "09:00:00".
     */
    public function testTimeForInput(): ?string
    {
        return $this->test_time
            ? Carbon::parse($this->test_time)->format('H:i')
            : null;
    }

    /**
     * Get the test time formatted for display, e.g. "09:00 AM".
     */
    public function formattedTestTime(): ?string
    {
        return $this->test_time
            ? Carbon::parse($this->test_time)->format('h:i A')
            : null;
    }

    /**
     * Get the scheduled test date and time as a single display string.
     */
    public function formattedTestSchedule(): ?string
    {
        if (! $this->hasTestSchedule()) {
            return null;
        }

        $date = $this->test_date?->format('d M, Y');
        $time = $this->formattedTestTime();

        return match (true) {
            $date && $time => "{$date} at {$time}",
            (bool) $date => $date,
            default => "Time set: {$time}",
        };
    }

    /**
     * Normalise a mobile number into the digits-only form wa.me expects.
     *
     * Strips spaces, dashes, brackets and plus signs, resolves the Pakistani
     * dialling conventions, then validates the result. A Pakistani mobile is
     * the country code 92 followed by a 3XXXXXXXXX subscriber number, so the
     * normalised value must be exactly 12 digits beginning 923.
     *
     * Returns null for anything that is not a valid Pakistani mobile, so
     * callers can fall back to "No valid mobile".
     */
    public static function normalizeWhatsappNumber(?string $mobile): ?string
    {
        if (blank($mobile)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if (blank($digits)) {
            return null;
        }

        $normalised = match (true) {
            // 0092… international prefix
            str_starts_with($digits, '0092') => substr($digits, 2),
            // Already in country-code form: 923001234567
            str_starts_with($digits, '92') => $digits,
            // Local format: 03001234567
            str_starts_with($digits, '0') => '92'.substr($digits, 1),
            // Bare mobile without the trunk zero: 3001234567
            str_starts_with($digits, '3') => '92'.$digits,
            default => $digits,
        };

        // 92 + 3XXXXXXXXX. Rejects landlines, truncated numbers such as
        // 0300123, and junk such as 023232 or 12345.
        return preg_match('/^923\d{9}$/', $normalised) === 1
            ? $normalised
            : null;
    }

    /**
     * Get the father's mobile number formatted for WhatsApp.
     */
    public function whatsappNumber(): ?string
    {
        return self::normalizeWhatsappNumber($this->father_mobile);
    }

    /**
     * Determine whether a WhatsApp message can be composed for this application.
     *
     * Needs a reachable number and a fully scheduled test, since the message
     * quotes both the date and the time.
     */
    public function canContactOnWhatsapp(): bool
    {
        return $this->whatsappNumber() !== null
            && $this->test_date !== null
            && $this->test_time !== null;
    }

    /**
     * Build the pre-filled admission test message.
     */
    public function whatsappMessage(): string
    {
        // The institution signs its own message. This is the one piece of
        // branding in this file and the only line of it that changes: the
        // dates, the times and the application number below are untouched.
        $school = Setting::current()->brandName();

        return implode("\n", [
            'Assalam-o-Alaikum,',
            '',
            "Admission test details for {$this->student_name}:",
            '',
            "Application Number: {$this->application_number}",
            "Test Date: {$this->test_date?->format('d M, Y')}",
            "Test Time: {$this->formattedTestTime()}",
            '',
            'Please bring the student to the school on the test date at the given time, and quote the application number on arrival.',
            '',
            $school,
        ]);
    }

    /**
     * Get the wa.me link that opens WhatsApp with the message pre-filled.
     *
     * Nothing is sent: WhatsApp opens the chat with the text ready and the
     * admin presses Send.
     */
    public function whatsappUrl(): ?string
    {
        if (! $this->canContactOnWhatsapp()) {
            return null;
        }

        return 'https://wa.me/'.$this->whatsappNumber().'?text='.rawurlencode($this->whatsappMessage());
    }

    /**
     * The filters the admission listing may be narrowed by.
     *
     * Named here rather than restated in each view, so the "Clear Filters"
     * link and the empty state cannot fall out of step with what scopeFilter
     * below actually reads.
     *
     * @var array<int, string>
     */
    public const FILTER_KEYS = [
        'search',
        'status',
        'student_type',
        'test_result',
        'gender',
        'department_id',
        'academic_class_id',
        'academic_session_id',
    ];

    /**
     * Narrow a query by the admission listing's filters.
     *
     * One definition of what each filter means, shared by the listing and by
     * every document printed from it. A report that carries the page's
     * filters therefore covers exactly the rows the page is showing: there
     * is no second copy of this logic to drift.
     *
     * Every filter is optional and every one of them only narrows. An absent
     * or empty value is skipped rather than matched against, so a blank
     * dropdown means "all" and never "none".
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                // Grouped, so the OR branches cannot widen the filters
                // applied either side of them.
                $query->where(function (Builder $query) use ($search) {
                    $query->where('application_number', 'like', "%{$search}%")
                        ->orWhere('student_name', 'like', "%{$search}%")
                        ->orWhere('father_name', 'like', "%{$search}%")
                        ->orWhere('father_mobile', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['student_type'] ?? null, fn (Builder $query, string $type) => $query->where('student_type', $type))
            ->when($filters['test_result'] ?? null, fn (Builder $query, string $result) => $query->where('test_result', $result))
            ->when($filters['gender'] ?? null, fn (Builder $query, string $gender) => $query->where('gender', $gender))
            // An application holds a madrassa class, a school class or both,
            // so a department or class filter matches either side. Resolved
            // as subqueries rather than by loading the classes, so the filter
            // costs one query however many applications match.
            ->when($filters['department_id'] ?? null, function (Builder $query, $departmentId) {
                $query->where(function (Builder $query) use ($departmentId) {
                    $query->whereHas('madrassaClass', fn (Builder $class) => $class->where('department_id', $departmentId))
                        ->orWhereHas('schoolClass', fn (Builder $class) => $class->where('department_id', $departmentId));
                });
            })
            ->when($filters['academic_class_id'] ?? null, function (Builder $query, $academicClassId) {
                $query->where(function (Builder $query) use ($academicClassId) {
                    $query->where('madrassa_class_id', $academicClassId)
                        ->orWhere('school_class_id', $academicClassId);
                });
            })
            // An equality test, so an application whose session is not known
            // matches no session at all rather than matching every one. That
            // is the intended behaviour and not an oversight: a legacy row is
            // not evidence that somebody applied for the year being filtered.
            ->when($filters['academic_session_id'] ?? null, fn (Builder $query, $sessionId) => $query->where('academic_session_id', $sessionId));
    }

    /**
     * Narrow a query to the applicants who passed the admission test.
     *
     * The recorded result, and nothing else. Not the status, which an
     * approval later moves on to Approved, and not the presence of a
     * student: an applicant who passed is a passed applicant whether or not
     * anybody has admitted them yet.
     */
    public function scopePassedTest(Builder $query): Builder
    {
        return $query->where('test_result', 'Passed');
    }

    /**
     * Get the classes this application actually applied for.
     *
     * Madrassa first, then school, so a Hifz + School applicant reads in the
     * order the student type names them.
     *
     * @return array<int, AcademicClass>
     */
    public function appliedClasses(): array
    {
        return array_values(array_filter([$this->madrassaClass, $this->schoolClass]));
    }

    /**
     * Get the departments applied for, as one display string.
     *
     * Null when the application carries no class at all, which is the case
     * for applications entered before the class selection existed. Callers
     * decide what to print instead; nothing is invented here.
     */
    public function departmentLabel(): ?string
    {
        $names = collect($this->appliedClasses())
            ->map(fn (AcademicClass $class) => $class->department?->name)
            ->filter()
            ->unique()
            ->values();

        return $names->isEmpty() ? null : $names->implode(' / ');
    }

    /**
     * Get the classes applied for, as one display string.
     */
    public function classLabel(): ?string
    {
        $names = collect($this->appliedClasses())
            ->map(fn (AcademicClass $class) => $class->name)
            ->filter()
            ->unique()
            ->values();

        return $names->isEmpty() ? null : $names->implode(' / ');
    }

    /**
     * Get the label key describing whether this admission was approved.
     *
     * Two outcomes and no third: the admission has been approved, or it has
     * not been approved yet. Nothing here reads Rejected or Failed, because
     * this answers one narrow question - has this candidate been admitted -
     * and a candidate who has not been admitted yet has not been refused.
     *
     * Decided by isApproved(), which is the project's existing definition of
     * an approved admission: a student record exists. That is the same
     * condition canBeApproved() guards against and the same one the approval
     * sets, inside the transaction that creates the student, so the answer
     * cannot disagree with the status column. No new status is introduced
     * and none is invented for display.
     *
     * A key rather than a word, so the wording stays in one place per
     * language and this model stays out of the translation business.
     */
    public function admissionStatusKey(): string
    {
        return $this->isApproved() ? 'approved' : 'not_approved';
    }

    /**
     * Get the Tailwind badge classes for the recorded test result.
     */
    public function testResultBadgeClasses(): string
    {
        return match ($this->test_result) {
            'Passed' => 'bg-green-100 text-green-800',
            'Failed' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get the Tailwind badge classes for the application's current status.
     */
    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'Approved', 'Passed' => 'bg-green-100 text-green-800',
            'Rejected', 'Failed' => 'bg-red-100 text-red-800',
            'Test Scheduled' => 'bg-blue-100 text-blue-800',
            'Test Completed' => 'bg-indigo-100 text-indigo-800',
            'Under Review' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
