<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\MadrassaDailyRecord;
use App\Models\Section;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hifz & Quran reports and progress.
 *
 * Reports over what was written down, and nothing more. Every figure on
 * this page is a count of recorded days or a value copied verbatim from a
 * record; nothing is added up, converted or extrapolated. The quantities
 * are free text - "1 page", "half page", "1/2 para" - and turning them into
 * numbers would need a Quran unit system this project has not designed, so
 * "1 page" and "1/2 page" are never quietly resolved into "1.5 pages".
 *
 * The page has two halves, and they answer different questions on purpose.
 *
 * The student table is driven by the records. Its rows are placements as
 * they were at the time - the class and section come from the enrollment
 * each latest record was written against - so a report over August still
 * reads Nazra / Section A for a student who moved to Hifz / Section B in
 * September.
 *
 * The "no records" panel is driven by the current enrollments, because a
 * question about who still has to be chased is a question about who is in
 * the class today.
 *
 * Only the Madrassa track is ever read. A Hifz + School student is counted
 * through their madrassa enrollment; their school one is not a row this
 * page can reach.
 */
class MadrassaDailyRecordReportController extends Controller
{
    /**
     * How many students to show per page.
     */
    private const PER_PAGE = 20;

    /**
     * How many names the "no records" panel lists before summarising.
     *
     * The count is always exact; the list is capped so an unfiltered report
     * cannot try to name every student in the madrassa.
     */
    private const MISSING_LIST_LIMIT = 50;

    /**
     * Display the report.
     */
    public function index(Request $request)
    {
        // Read from the enum, never passed through from the query string.
        // It decides which columns the report is shaped for; what a student
        // actually has is decided by the record rows themselves.
        $recordType = MadrassaDailyRecord::reportableRecordType($request->input('record_type'));

        $filters = $this->filters($request, $recordType);

        $students = $this->studentReportQuery($filters)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('hifz.reports', [
            'filters' => $filters,
            'recordType' => $recordType,
            'recordTypes' => MadrassaDailyRecord::RECORD_TYPES,
            'students' => $students,
            // The latest record behind each row on this page, in one query
            // rather than one per student.
            'latestRecords' => $this->latestRecordsFor($students->getCollection(), $filters),
            'workFields' => MadrassaDailyRecord::workFieldsFor($recordType),
            'fieldLabels' => MadrassaDailyRecord::WORK_FIELD_LABELS,
            'summaryLabels' => array_keys(MadrassaDailyRecord::summaryFieldsFor($recordType)),
            'groupSummary' => $this->groupSummary($filters),
            'missing' => $this->studentsWithoutRecords($filters),
            ...$this->filterOptions(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* The student table */
    /* ------------------------------------------------------------------ */

    /**
     * Build the student-wise report.
     *
     * One row per student, aggregated in SQL. The alternative - reading
     * every daily record into PHP and grouping there - would load a term's
     * worth of rows to print twenty lines.
     *
     * The student columns are grouped as well as selected, so the query is
     * valid under MySQL's ONLY_FULL_GROUP_BY and the table needs no second
     * lookup to name anybody.
     *
     * @param  array<string, mixed>  $filters
     */
    private function studentReportQuery(array $filters)
    {
        $query = $this->baseRecordQuery($filters)
            ->groupBy(
                'sae.student_id',
                'students.full_name',
                'students.registration_number',
                'students.roll_number'
            )
            ->select([
                'sae.student_id',
                'students.full_name',
                'students.registration_number',
                'students.roll_number',
                DB::raw('count(*) as recorded_days'),
                DB::raw('max(madrassa_daily_records.record_date) as last_recorded_date'),
            ])
            // Roll number first, as the paper register is kept, then the
            // two columns that are always present so the order is total.
            ->orderBy('students.roll_number')
            ->orderBy('students.registration_number')
            ->orderBy('students.full_name');

        // One "days with X" column per summary line, counted in SQL. These
        // count days, never quantities.
        foreach (MadrassaDailyRecord::summaryFieldsFor($filters['record_type']) as $label => $fields) {
            $query->addSelect(DB::raw(
                MadrassaDailyRecord::recordedDaysExpression($fields).' as '.$this->countColumn($label)
            ));
        }

        return $query;
    }

    /**
     * The query every figure on this page is derived from.
     *
     * Records of the selected type, on the madrassa track, narrowed by the
     * filters. The placement conditions are on the enrollment each record
     * was written against, not on the student's current one: a report over
     * a past month asks where the student was then.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<MadrassaDailyRecord>
     */
    private function baseRecordQuery(array $filters)
    {
        return MadrassaDailyRecord::query()
            ->join('student_academic_enrollments as sae', 'sae.id', '=', 'madrassa_daily_records.student_academic_enrollment_id')
            ->join('students', 'students.id', '=', 'sae.student_id')
            // The record type is a stored column, written from the student's
            // programme when the record was made. Reading it here is what
            // keeps Hifz and Dars-e-Nizami from ever being mixed into one
            // figure.
            ->where('madrassa_daily_records.record_type', $filters['record_type'])
            ->where('sae.academic_track', MadrassaDailyRecord::ACADEMIC_TRACK)
            ->when($filters['academic_session_id'], fn ($query, $id) => $query->where('sae.academic_session_id', $id))
            ->when($filters['department_id'], fn ($query, $id) => $query->where('sae.department_id', $id))
            ->when($filters['academic_class_id'], fn ($query, $id) => $query->where('sae.academic_class_id', $id))
            ->when($filters['section_id'], fn ($query, $id) => $query->where('sae.section_id', $id))
            ->when($filters['date_from'], fn ($query, $from) => $query->whereDate('madrassa_daily_records.record_date', '>=', $from))
            ->when($filters['date_to'], fn ($query, $to) => $query->whereDate('madrassa_daily_records.record_date', '<=', $to))
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($match) use ($search) {
                    $match->where('students.full_name', 'like', "%{$search}%")
                        ->orWhere('students.registration_number', 'like', "%{$search}%")
                        ->orWhere('students.roll_number', 'like', "%{$search}%");
                });
            });
    }

    /**
     * Load the latest record behind each row of the current page.
     *
     * The aggregate above already knows each student's last recorded date,
     * so this asks for exactly those rows rather than for every record in
     * the range. One query for the page, plus its eager loads, however many
     * students are on it.
     *
     * @param  Collection<int, object>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MadrassaDailyRecord>
     */
    private function latestRecordsFor($rows, array $filters)
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        return $this->baseRecordQuery($filters)
            ->where(function ($query) use ($rows) {
                foreach ($rows as $row) {
                    $query->orWhere(function ($pair) use ($row) {
                        $pair->where('sae.student_id', $row->student_id)
                            ->whereDate('madrassa_daily_records.record_date', $row->last_recorded_date);
                    });
                }
            })
            ->with([
                'studentAcademicEnrollment.academicSession',
                'studentAcademicEnrollment.department',
                'studentAcademicEnrollment.academicClass',
                'studentAcademicEnrollment.section',
                'teacher',
            ])
            // student_id is not on the record - it is reached through the
            // enrollment - so the join is asked to carry it out.
            ->select('madrassa_daily_records.*', 'sae.student_id as report_student_id')
            ->orderByDesc('madrassa_daily_records.record_date')
            ->orderByDesc('madrassa_daily_records.id')
            ->get()
            // A student promoted mid-year can hold a record on the same day
            // under two placements, so the order above decides which one
            // stands rather than leaving it to the database.
            ->groupBy('report_student_id')
            ->map(fn ($records) => $records->first());
    }

    /* ------------------------------------------------------------------ */
    /* The group summary */
    /* ------------------------------------------------------------------ */

    /**
     * Summarise the whole filtered group, not just the page.
     *
     * The per-student aggregate is reused as a subquery and rolled up once,
     * so "students with Sabaq" costs one query rather than one per student.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function groupSummary(array $filters): array
    {
        $labels = array_keys(MadrassaDailyRecord::summaryFieldsFor($filters['record_type']));

        $columns = [
            DB::raw('count(*) as students_with_records'),
            DB::raw('coalesce(sum(recorded_days), 0) as recorded_days'),
        ];

        foreach ($labels as $label) {
            $column = $this->countColumn($label);

            $columns[] = DB::raw("sum(case when {$column} > 0 then 1 else 0 end) as students_with_{$column}");
        }

        $rolled = DB::query()
            ->fromSub($this->studentReportQuery($filters)->reorder(), 'per_student')
            ->select($columns)
            ->first();

        $totalStudents = $this->currentMadrassaStudentQuery($filters)->distinct()->count('sae.student_id');
        $withRecords = (int) ($rolled->students_with_records ?? 0);

        $byLabel = [];

        foreach ($labels as $label) {
            $column = 'students_with_'.$this->countColumn($label);
            $byLabel[$label] = (int) ($rolled->{$column} ?? 0);
        }

        return [
            // Every current madrassa student the placement filters cover,
            // recorded or not.
            'total_students' => $totalStudents,
            'students_with_records' => $withRecords,
            // Never negative: a student who was recorded and has since left
            // the class is in the numerator but not the denominator.
            'students_without_records' => max(0, $totalStudents - $withRecords),
            'recorded_days' => (int) ($rolled->recorded_days ?? 0),
            'students_by_label' => $byLabel,
        ];
    }

    /**
     * Name the students the period holds no record for.
     *
     * Driven by the current enrollments rather than by the records: this is
     * the list of who still has to be entered, and that is a question about
     * who is in the class now.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function studentsWithoutRecords(array $filters): array
    {
        $base = $this->currentMadrassaStudentQuery($filters)
            ->whereNotIn('sae.student_id', $this->studentIdsWithRecords($filters));

        $count = (clone $base)->distinct()->count('sae.student_id');

        $listed = (clone $base)
            ->with(['student', 'academicClass', 'section'])
            ->select('sae.*')
            ->orderBy('students.roll_number')
            ->orderBy('students.registration_number')
            ->orderBy('students.full_name')
            ->limit(self::MISSING_LIST_LIMIT)
            ->get();

        return [
            'count' => $count,
            'listed' => $listed,
            // True when the panel is naming only some of them, so the view
            // can say so rather than implying the list is complete.
            'truncated' => $count > $listed->count(),
        ];
    }

    /**
     * The students who do have a record in the period.
     *
     * Returned as a subquery so the "no records" list stays one statement.
     *
     * @param  array<string, mixed>  $filters
     */
    private function studentIdsWithRecords(array $filters)
    {
        return $this->baseRecordQuery($filters)
            ->reorder()
            ->select('sae.student_id')
            ->distinct()
            ->getQuery();
    }

    /**
     * Every current madrassa student the placement filters cover.
     *
     * Active enrollments on the Madrassa track only, narrowed to the
     * programmes the selected report covers. A school-only student is not
     * reachable here, and neither is the school side of a Hifz + School
     * student.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<StudentAcademicEnrollment>
     */
    private function currentMadrassaStudentQuery(array $filters)
    {
        return StudentAcademicEnrollment::query()
            ->from('student_academic_enrollments as sae')
            ->join('students', 'students.id', '=', 'sae.student_id')
            ->where('sae.academic_track', MadrassaDailyRecord::ACADEMIC_TRACK)
            ->where('sae.status', 'Active')
            // The report names a record type; the students table names a
            // programme. Translated rather than compared.
            ->whereIn('students.student_type', MadrassaDailyRecord::studentTypesFor($filters['record_type']))
            ->when($filters['academic_session_id'], fn ($query, $id) => $query->where('sae.academic_session_id', $id))
            ->when($filters['department_id'], fn ($query, $id) => $query->where('sae.department_id', $id))
            ->when($filters['academic_class_id'], fn ($query, $id) => $query->where('sae.academic_class_id', $id))
            ->when($filters['section_id'], fn ($query, $id) => $query->where('sae.section_id', $id))
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($match) use ($search) {
                    $match->where('students.full_name', 'like', "%{$search}%")
                        ->orWhere('students.registration_number', 'like', "%{$search}%")
                        ->orWhere('students.roll_number', 'like', "%{$search}%");
                });
            });
    }

    /* ------------------------------------------------------------------ */
    /* Shared helpers */
    /* ------------------------------------------------------------------ */

    /**
     * Turn a summary label into a safe SQL column alias.
     *
     * The labels are ours - Sabaq, Sabqi, Manzil, Tomorrow - but they are
     * written for people, so they are reduced to something a database will
     * accept before being put into a statement.
     */
    private function countColumn(string $label): string
    {
        return 'days_with_'.strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $label));
    }

    /**
     * Read the report filters off the request.
     *
     * Anything absent is null rather than an empty string, so every filter
     * below reads as a question about whether a choice was made. All of
     * them are AND conditions: a class from another department matches
     * nothing rather than one filter quietly winning over the other.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request, string $recordType): array
    {
        return [
            'record_type' => $recordType,
            'academic_session_id' => $this->cleaned($request->input('academic_session_id')),
            'department_id' => $this->cleaned($request->input('department_id')),
            'academic_class_id' => $this->cleaned($request->input('academic_class_id')),
            'section_id' => $this->cleaned($request->input('section_id')),
            'date_from' => $this->cleanedDate($request->input('date_from')),
            'date_to' => $this->cleanedDate($request->input('date_to')),
            'search' => $this->cleaned($request->input('search')),
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
     * Reduce a date filter to Y-m-d, or to null when it is not a date.
     */
    private function cleanedDate(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== ''
            ? MadrassaDailyRecord::normalizeRecordDate($value)
            : null;
    }

    /**
     * Get the options the filter selects are built from.
     *
     * The same shape the roster and the academic pages use, so the class
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
