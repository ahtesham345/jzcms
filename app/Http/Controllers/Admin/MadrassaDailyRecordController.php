<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMadrassaDailyRecordRequest;
use App\Http\Requests\Admin\UpdateMadrassaDailyRecordRequest;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\MadrassaDailyRecord;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * The Hifz & Quran daily academic record.
 *
 * What a madrassa student actually did on a day: the Sabaq they read, the
 * Sabqi and Manzil they revised, or - for a Dars-e-Nizami student - the
 * kitab and lesson they covered. It is not attendance, which answers a
 * different question in its own module, and it is not a result.
 *
 * Every record hangs off a student_academic_enrollments row. That is the
 * source of truth for where the student was placed: nothing here reads
 * students.academic_class_id, which cannot describe a Hifz + School student
 * holding two active placements at once. Reading the placement back through
 * the stored enrollment is also what keeps an old record showing the class
 * it was made in after the student has been promoted out of it.
 *
 * The page is one workflow in two halves. Choose a date and a class and the
 * roster lists the madrassa students in it, each with the day's record or a
 * link to open one. Below it, the records already on file, searchable and
 * filtered.
 */
class MadrassaDailyRecordController extends Controller
{
    /**
     * How many daily records to show per page.
     */
    private const PER_PAGE = 20;

    /**
     * The filters that narrow the enrollment a record was written against.
     *
     * All conditions on the enrollment row, so they combine with AND: a
     * class from another department matches nothing rather than one filter
     * quietly winning over the other.
     *
     * @var array<int, string>
     */
    private const ENROLLMENT_FILTERS = [
        'academic_session_id',
        'department_id',
        'academic_class_id',
        'section_id',
    ];

    /** No date chosen yet, so there is nothing to load. */
    private const ROSTER_IDLE = 'idle';

    /** A date was chosen, but no class to draw the roster from. */
    private const ROSTER_NEEDS_CLASS = 'needs_class';

    /** A Sunday: the madrassa does not sit, so nothing is recorded. */
    private const ROSTER_OFF_DAY = 'off_day';

    /** A working day and a class: the roster is drawn. */
    private const ROSTER_LOADED = 'loaded';

    /**
     * Everything the index reads off the query string.
     *
     * Also the whitelist for the filters a record form carries back, so a
     * hand-edited form cannot smuggle anything else into the redirect.
     *
     * @var array<int, string>
     */
    private const INDEX_FILTERS = [
        'search',
        'academic_session_id',
        'department_id',
        'academic_class_id',
        'section_id',
        'record_type',
        'record_date',
        'student_id',
    ];

    /**
     * Display the daily records, and the roster for the chosen day.
     */
    public function index(Request $request)
    {
        $records = $this->filteredQuery($request)
            // Eager loaded: every row renders the student, the placement it
            // was recorded under and the teacher. Without this the page
            // would cost six queries per record.
            ->with([
                'studentAcademicEnrollment.student',
                'studentAcademicEnrollment.academicSession',
                'studentAcademicEnrollment.department',
                'studentAcademicEnrollment.academicClass',
                'studentAcademicEnrollment.section',
                'teacher',
            ])
            ->inDailyOrder()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $rosterState = $this->rosterState($request);

        return view('hifz.index', [
            'records' => $records,
            // Only queried once the state says there is a roster to draw.
            // Loading a date is a read: nothing on this page writes a
            // record, and a day with no records simply has none.
            'roster' => $rosterState === self::ROSTER_LOADED ? $this->roster($request) : null,
            // Says why the roster panel looks the way it does - idle,
            // waiting for a class, a weekend, or loaded - so the view never
            // has to infer it from an empty collection.
            'rosterState' => $rosterState,
            'rosterHeading' => $rosterState === self::ROSTER_IDLE ? null : $this->rosterHeading($request),
            'recordTypes' => MadrassaDailyRecord::RECORD_TYPES,
            'filters' => $this->indexFilters($request),
            'summary' => $this->summary(),
            // Only shown when the listing was narrowed to one student, so
            // the page can say whose records it is showing.
            'filteredStudent' => $request->filled('student_id')
                ? Student::find($request->input('student_id'))
                : null,
            ...$this->filterOptions(),
        ]);
    }

    /**
     * Show the form for creating a daily record.
     *
     * Opened for one enrollment, which the roster supplies. Reached without
     * one there is no student to record anything against, so the request
     * goes back to the roster rather than to an empty form.
     */
    public function create(Request $request)
    {
        $enrollment = $this->openableEnrollment(
            $request->input('student_academic_enrollment_id')
        );

        if (is_string($enrollment)) {
            return $this->backToIndex($request, $enrollment);
        }

        $recordType = MadrassaDailyRecord::recordTypeForEnrollment($enrollment);

        // A day already recorded is corrected, never recorded twice. The
        // unique index would refuse the second write anyway; sending the
        // administrator to the existing record is the useful version of
        // that refusal.
        $date = $request->input('record_date');
        $existing = $date === null
            ? null
            : MadrassaDailyRecord::where('student_academic_enrollment_id', $enrollment->id)
                ->whereDate('record_date', $date)
                ->first();

        if ($existing !== null) {
            return redirect()
                ->route('hifz.edit', $existing)
                ->with('error', 'This student already has a daily record for that date. Correct the existing record.');
        }

        return view('hifz.create', [
            'enrollment' => $enrollment,
            'recordType' => $recordType,
            'workFields' => MadrassaDailyRecord::workFieldsFor($recordType),
            'fieldLabels' => MadrassaDailyRecord::WORK_FIELD_LABELS,
            'teachers' => $this->selectableTeachers(),
            'recordDate' => $date,
            // Carried through the form so saving returns to the roster the
            // student was picked from rather than to an unfiltered page.
            'returnFilters' => $this->returnFilters($request),
        ]);
    }

    /**
     * Store a newly created daily record.
     *
     * Everything the request says about the student has already been
     * checked against the enrollment it names. The record type is taken
     * from the request object, which derived it from that enrollment's
     * student rather than from the form.
     */
    public function store(StoreMadrassaDailyRecordRequest $request)
    {
        $record = MadrassaDailyRecord::create(
            $this->recordAttributes($request->validated(), $request->resolvedRecordType())
        );

        return $this->backToIndex(
            $request,
            null,
            'Daily record saved for '.$record->studentAcademicEnrollment->student->full_name
                .' on '.$record->record_date->format('d M, Y').'.'
        );
    }

    /**
     * Display one daily record in full.
     */
    public function show(MadrassaDailyRecord $record)
    {
        $record->load([
            'studentAcademicEnrollment.student',
            'studentAcademicEnrollment.academicSession',
            'studentAcademicEnrollment.department',
            'studentAcademicEnrollment.academicClass',
            'studentAcademicEnrollment.section',
            'teacher',
        ]);

        return view('hifz.show', [
            'record' => $record,
            'enrollment' => $record->studentAcademicEnrollment,
        ]);
    }

    /**
     * Show the form for correcting a daily record.
     */
    public function edit(Request $request, MadrassaDailyRecord $record)
    {
        $record->load([
            'studentAcademicEnrollment.student',
            'studentAcademicEnrollment.academicSession',
            'studentAcademicEnrollment.department',
            'studentAcademicEnrollment.academicClass',
            'studentAcademicEnrollment.section',
            'teacher',
        ]);

        return view('hifz.edit', [
            'record' => $record,
            'enrollment' => $record->studentAcademicEnrollment,
            // The stored type, not a freshly derived one. A record keeps
            // the shape it was written in even if the student's programme
            // has changed since.
            'recordType' => $record->record_type,
            'workFields' => MadrassaDailyRecord::workFieldsFor($record->record_type),
            'fieldLabels' => MadrassaDailyRecord::WORK_FIELD_LABELS,
            'teachers' => $this->selectableTeachers($record->teacher),
            'returnFilters' => $this->returnFilters($request),
        ]);
    }

    /**
     * Update a daily record.
     *
     * The enrollment is never among the changes: the request reads it from
     * the stored row and refuses a request that names a different one, so a
     * correction can only ever change what the day says, not whose day it
     * was.
     */
    public function update(UpdateMadrassaDailyRecordRequest $request, MadrassaDailyRecord $record)
    {
        $record->update(
            $this->recordAttributes($request->validated(), $record->record_type, $record)
        );

        return $this->backToIndex(
            $request,
            null,
            'Daily record updated for '.$record->studentAcademicEnrollment->student->full_name
                .' on '.$record->record_date->format('d M, Y').'.'
        );
    }

    /* ------------------------------------------------------------------ */
    /* Writing */
    /* ------------------------------------------------------------------ */

    /**
     * Build the columns a save writes.
     *
     * The record type is decided here, from the enrollment or from the row
     * being corrected - never from the form. The other programme's columns
     * are written as null rather than left alone, so a record that changes
     * type can never keep a stale Sabaq underneath a Dars-e-Nizami lesson.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function recordAttributes(array $validated, string $recordType, ?MadrassaDailyRecord $record = null): array
    {
        $attributes = [
            'record_type' => $recordType,
            'record_date' => $validated['record_date'],
            'teacher_id' => $validated['teacher_id'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ];

        // Set only when creating. An update leaves the column untouched,
        // which is the other half of "a record cannot be moved".
        if ($record === null) {
            $attributes['student_academic_enrollment_id'] = $validated['student_academic_enrollment_id'];
        }

        foreach (MadrassaDailyRecord::workFieldsFor($recordType) as $field) {
            $attributes[$field] = $validated[$field] ?? null;
        }

        foreach (MadrassaDailyRecord::foreignWorkFieldsFor($recordType) as $field) {
            $attributes[$field] = null;
        }

        return $attributes;
    }

    /* ------------------------------------------------------------------ */
    /* The roster */
    /* ------------------------------------------------------------------ */

    /**
     * Work out what the roster panel should be showing.
     *
     * Four answers, in the order the administrator meets them: nothing
     * chosen yet, a weekend, a day without a class to load, or a roster.
     * The off day is decided before the class because a Sunday has no
     * roster whatever class is picked.
     */
    private function rosterState(Request $request): string
    {
        if (! $request->filled('record_date')) {
            return self::ROSTER_IDLE;
        }

        if (MadrassaDailyRecord::offDayName($request->input('record_date')) !== null) {
            return self::ROSTER_OFF_DAY;
        }

        return $request->filled('academic_class_id')
            ? self::ROSTER_LOADED
            : self::ROSTER_NEEDS_CLASS;
    }

    /**
     * Get the madrassa students a daily record can be opened for.
     *
     * Active enrollments only, and only on the Madrassa track. A Hifz +
     * School student appears once, through their madrassa enrollment; their
     * school one is not a row this module can see, here or anywhere else.
     *
     * Only called once rosterState() has said there is a roster to draw, so
     * the date and class are both known to be present and the date is known
     * to be a working day.
     *
     * @return Collection<int, StudentAcademicEnrollment>
     */
    private function roster(Request $request): Collection
    {
        $date = $request->input('record_date');

        $enrollments = StudentAcademicEnrollment::query()
            ->with(['student', 'academicClass', 'section'])
            // Qualified throughout: students is joined below for ordering
            // and carries columns of the same name.
            ->where('student_academic_enrollments.academic_track', MadrassaDailyRecord::ACADEMIC_TRACK)
            ->where('student_academic_enrollments.status', 'Active')
            ->where('student_academic_enrollments.academic_class_id', $request->input('academic_class_id'))
            ->when($request->filled('academic_session_id'), fn ($query) => $query->where('student_academic_enrollments.academic_session_id', $request->input('academic_session_id')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('student_academic_enrollments.department_id', $request->input('department_id')))
            ->when($request->filled('section_id'), fn ($query) => $query->where('student_academic_enrollments.section_id', $request->input('section_id')))
            ->join('students', 'students.id', '=', 'student_academic_enrollments.student_id')
            // Only programmes that have a daily record at all, which is
            // what keeps a school-only student off this page even if one
            // somehow held a madrassa enrollment.
            ->whereIn('students.student_type', array_keys(MadrassaDailyRecord::RECORD_TYPE_BY_STUDENT_TYPE))
            ->when($request->filled('record_type'), function ($query) use ($request) {
                // The programme filter names a record type; the students
                // table names a programme. Translate rather than compare.
                $query->whereIn('students.student_type', MadrassaDailyRecord::studentTypesFor($request->input('record_type')));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($student) use ($search) {
                    $student->where('students.full_name', 'like', "%{$search}%")
                        ->orWhere('students.registration_number', 'like', "%{$search}%")
                        ->orWhere('students.roll_number', 'like', "%{$search}%");
                });
            })
            // Roll number first, as the paper register is kept, then the
            // two columns that are always present so the order is total.
            ->orderBy('students.roll_number')
            ->orderBy('students.registration_number')
            ->orderBy('students.full_name')
            ->select('student_academic_enrollments.*')
            ->get();

        // One query for the whole roster rather than one per student, read
        // straight from madrassa_daily_records. Attendance is never
        // consulted: whether a student was present is a different question
        // from whether their day's work has been written down.
        //
        // The result decides which students show View + Edit and which
        // show Add, which is what keeps the roster from offering a second
        // record for a day that already has one.
        $existing = MadrassaDailyRecord::query()
            ->with('teacher')
            ->whereIn('student_academic_enrollment_id', $enrollments->pluck('id')->all())
            ->whereDate('record_date', $date)
            ->get()
            ->keyBy('student_academic_enrollment_id');

        // Attached rather than returned alongside, so the view reads one
        // list instead of pairing two.
        return $enrollments->each(function (StudentAcademicEnrollment $enrollment) use ($existing) {
            $enrollment->setRelation('dailyRecordForDate', $existing->get($enrollment->id));
        });
    }

    /**
     * Resolve the names the roster heading is built from.
     *
     * Looked up once for the heading, never per row.
     *
     * @return array<string, mixed>
     */
    private function rosterHeading(Request $request): array
    {
        // Each lookup is skipped when its filter was not chosen. Handing a
        // null key to find() is still a round trip that can only come back
        // empty, and the heading asks for four of them.
        $find = fn (string $model, string $filter) => $request->filled($filter)
            ? $model::find($request->input($filter))
            : null;

        return [
            'date' => $request->input('record_date'),
            'session' => $find(AcademicSession::class, 'academic_session_id'),
            'department' => $find(Department::class, 'department_id'),
            'academicClass' => $find(AcademicClass::class, 'academic_class_id'),
            'section' => $find(Section::class, 'section_id'),
            'offDay' => MadrassaDailyRecord::offDayName($request->input('record_date')),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* The listing */
    /* ------------------------------------------------------------------ */

    /**
     * Build the record query from the search and filters.
     *
     * Every filter is either a condition on the record or a condition on
     * the enrollment it belongs to, so they combine with AND. The
     * enrollment conditions are gathered into one whereHas rather than one
     * each: a class and a department from different branches must match
     * nothing, not two separate sets of rows.
     */
    private function filteredQuery(Request $request)
    {
        $needsEnrollment = $request->filled('search')
            || $request->filled('student_id')
            || collect(self::ENROLLMENT_FILTERS)->contains(fn ($filter) => $request->filled($filter));

        return MadrassaDailyRecord::query()
            ->when($request->filled('record_date'), fn ($query) => $query->whereDate('record_date', $request->input('record_date')))
            ->when($request->filled('record_type'), fn ($query) => $query->where('record_type', $request->input('record_type')))
            ->when($needsEnrollment, function ($query) use ($request) {
                $query->whereHas('studentAcademicEnrollment', function ($enrollment) use ($request) {
                    foreach (self::ENROLLMENT_FILTERS as $filter) {
                        $enrollment->when(
                            $request->filled($filter),
                            fn ($query) => $query->where($filter, $request->input($filter))
                        );
                    }

                    // Narrowing to one student's history, which is how the
                    // link on a student profile arrives here. The track is
                    // part of the condition so the school side of a
                    // Hifz + School student cannot appear even if a row
                    // somehow reached it.
                    $enrollment->when($request->filled('student_id'), function ($query) use ($request) {
                        $query->where('student_id', $request->input('student_id'))
                            ->where('academic_track', MadrassaDailyRecord::ACADEMIC_TRACK);
                    });

                    // Resolved in SQL, against the student the enrollment
                    // belongs to.
                    $enrollment->when($request->filled('search'), function ($query) use ($request) {
                        $search = $request->input('search');

                        $query->whereHas('student', function ($student) use ($search) {
                            $student->where('full_name', 'like', "%{$search}%")
                                ->orWhere('registration_number', 'like', "%{$search}%")
                                ->orWhere('roll_number', 'like', "%{$search}%");
                        });
                    });
                });
            });
    }

    /**
     * Count what the module holds, for the cards at the top of the page.
     *
     * @return array<string, int>
     */
    private function summary(): array
    {
        return [
            'total' => MadrassaDailyRecord::count(),
            'hifz' => MadrassaDailyRecord::where('record_type', MadrassaDailyRecord::TYPE_HIFZ)->count(),
            'dars_e_nizami' => MadrassaDailyRecord::where('record_type', MadrassaDailyRecord::TYPE_DARS_E_NIZAMI)->count(),
            'madrassa_students' => StudentAcademicEnrollment::where('status', 'Active')
                ->where('academic_track', MadrassaDailyRecord::ACADEMIC_TRACK)
                ->distinct()
                ->count('student_id'),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Shared helpers */
    /* ------------------------------------------------------------------ */

    /**
     * Load an enrollment a record may be opened for, or say why it may not.
     *
     * Returns the enrollment, or the reason as a string. The same three
     * questions the form request asks on save, asked here so the interface
     * never opens a form the save would refuse.
     *
     * @return StudentAcademicEnrollment|string
     */
    private function openableEnrollment(mixed $id)
    {
        if (! is_numeric($id)) {
            return 'Choose a student from the roster to open a daily record.';
        }

        $enrollment = StudentAcademicEnrollment::with([
            'student',
            'academicSession',
            'department',
            'academicClass',
            'section',
        ])->find($id);

        if ($enrollment === null) {
            return 'That academic enrollment no longer exists.';
        }

        if ($enrollment->academic_track !== MadrassaDailyRecord::ACADEMIC_TRACK) {
            return 'A daily academic record can only be recorded against a Madrassa enrollment.';
        }

        if ($enrollment->status !== 'Active') {
            return "A daily record cannot be created against a {$enrollment->status} enrollment.";
        }

        if (MadrassaDailyRecord::recordTypeForEnrollment($enrollment) === null) {
            $programme = $enrollment->student?->student_type ?? 'unknown';

            return "A daily academic record is not kept for a {$programme} student.";
        }

        return $enrollment;
    }

    /**
     * Get the teachers the form offers.
     *
     * Current staff, plus whoever the record already names so an edit does
     * not have to reassign a lesson that already happened.
     *
     * @return Collection<int, Teacher>
     */
    private function selectableTeachers(?Teacher $current = null): Collection
    {
        $teachers = Teacher::where('teacher_status', 'Active')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'teacher_id']);

        if ($current !== null && ! $teachers->contains('id', $current->id)) {
            $teachers->push($current);
        }

        return $teachers;
    }

    /**
     * Read the index filters off the request.
     *
     * @return array<string, mixed>
     */
    private function indexFilters(Request $request): array
    {
        $filters = [];

        foreach (self::INDEX_FILTERS as $filter) {
            $filters[$filter] = $request->filled($filter) ? $request->input($filter) : null;
        }

        return $filters;
    }

    /**
     * Read the filters a record form is carrying back to the roster.
     *
     * Whitelisted and reduced to scalars: this ends up in a redirect URL,
     * so nothing arbitrary from the form may reach it.
     *
     * @return array<string, string>
     */
    private function returnFilters(Request $request): array
    {
        $submitted = $request->input('filters', []);

        if (! is_array($submitted)) {
            return [];
        }

        $filters = [];

        foreach (self::INDEX_FILTERS as $filter) {
            $value = $submitted[$filter] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                $filters[$filter] = (string) $value;
            }
        }

        return $filters;
    }

    /**
     * Send the request back to the roster it came from.
     */
    private function backToIndex(Request $request, ?string $error = null, ?string $success = null)
    {
        $redirect = redirect()->route('hifz.index', $this->returnFilters($request));

        if ($error !== null) {
            return $redirect->with('error', $error);
        }

        return $success === null ? $redirect : $redirect->with('success', $success);
    }

    /**
     * Get the options the filter selects are built from.
     *
     * The same shape the academic and attendance pages use, so the class
     * and section selects narrow without a round trip.
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
