<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MadrassaDailyRecord;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One student's Hifz or Dars-e-Nizami progress.
 *
 * A viewing page. It reports what the daily records already say: the most
 * recent work, counts of the days each kind of work was recorded on, and
 * the records themselves. Nothing is calculated from the quantities - they
 * are free text, and "1 page" plus "half page" is not something this module
 * is entitled to turn into a number.
 *
 * The page belongs to the student named in the route, and every record it
 * shows is reached through that student's own madrassa enrollments. A
 * filter can narrow the history but never widen it past the student, and
 * never past the Madrassa track: a Hifz + School student's school
 * enrollment is not a row this page can reach.
 *
 * Which report it draws is decided here, from the student's programme and
 * their records, never from the query string.
 */
class StudentMadrassaProgressController extends Controller
{
    /**
     * How many records to show per page.
     */
    private const PER_PAGE = 20;

    /**
     * Display the student's progress.
     */
    public function show(Request $request, Student $student)
    {
        // Every madrassa enrollment the student has ever held. Being
        // promoted does not start the history over, and the old rows are
        // what keep each record showing the class it was made in.
        $enrollmentIds = $student->academicEnrollments()
            ->where('academic_track', MadrassaDailyRecord::ACADEMIC_TRACK)
            ->pluck('id')
            ->all();

        $recordType = $this->recordTypeFor($student, $enrollmentIds);

        // The gate. A student with no madrassa placement and no madrassa
        // record has no progress in this module, and the URL must not
        // become a way to read a school-only student's academic record.
        if ($recordType === null) {
            abort(404);
        }

        $filters = $this->filters($request);

        $records = $this->query($enrollmentIds, $recordType, $filters)
            // Eager loaded: every row renders the placement it was recorded
            // under and the teacher who took the lesson.
            ->with([
                'studentAcademicEnrollment.academicSession',
                'studentAcademicEnrollment.department',
                'studentAcademicEnrollment.academicClass',
                'studentAcademicEnrollment.section',
                'teacher',
            ])
            ->inDailyOrder()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $current = $student->activeEnrollmentForTrack(MadrassaDailyRecord::ACADEMIC_TRACK);

        return view('students.hifz-progress', [
            'student' => $student,
            'recordType' => $recordType,
            'records' => $records,
            'filters' => $filters,
            'workFields' => MadrassaDailyRecord::workFieldsFor($recordType),
            'fieldLabels' => MadrassaDailyRecord::WORK_FIELD_LABELS,
            // The student's placement today, for the header. Null for a
            // student who has left, whose history is still readable.
            'currentEnrollment' => $current?->loadMissing(['academicSession', 'department', 'academicClass', 'section']),
            // Over the whole history, so "first recorded" means first.
            'overall' => $this->overallSummary($enrollmentIds, $recordType),
            // Over the chosen period only.
            'period' => $this->periodSummary($enrollmentIds, $recordType, $filters),
            'periodTeachers' => $this->teachersInPeriod($enrollmentIds, $recordType, $filters),
            'latest' => $this->latestRecord($enrollmentIds, $recordType),
            'hasFilters' => $filters['date_from'] !== null || $filters['date_to'] !== null,
        ]);
    }

    /**
     * Work out which progress report this student has.
     *
     * The current programme decides it while the student is enrolled. A
     * student who has left, or whose programme has been changed on their
     * record, falls back to what their most recent record was actually
     * written as - the records are the source of truth, and rebuilding a
     * Hifz history as a Dars-e-Nizami one would be a fiction.
     *
     * Null when there is neither, which is what turns this URL into a 404
     * for a school-only student.
     *
     * @param  array<int, int>  $enrollmentIds
     */
    private function recordTypeFor(Student $student, array $enrollmentIds): ?string
    {
        if ($enrollmentIds !== []) {
            $fromProgramme = MadrassaDailyRecord::recordTypeForStudentType($student->student_type);

            if ($fromProgramme !== null) {
                return $fromProgramme;
            }
        }

        return MadrassaDailyRecord::query()
            ->whereIn('student_academic_enrollment_id', $enrollmentIds)
            ->inDailyOrder()
            ->value('record_type');
    }

    /**
     * Build the record query.
     *
     * The enrollment ids come first and are not optional: they are what
     * bounds the page to this student. The record type is not optional
     * either, so a Hifz progress page can never show a Dars-e-Nizami day.
     *
     * @param  array<int, int>  $enrollmentIds
     * @param  array<string, mixed>  $filters
     */
    private function query(array $enrollmentIds, string $recordType, array $filters)
    {
        return MadrassaDailyRecord::query()
            ->whereIn('student_academic_enrollment_id', $enrollmentIds)
            ->where('record_type', $recordType)
            ->when($filters['date_from'], fn ($query, $from) => $query->whereDate('record_date', '>=', $from))
            ->when($filters['date_to'], fn ($query, $to) => $query->whereDate('record_date', '<=', $to));
    }

    /**
     * Count the student's whole recorded history.
     *
     * Deliberately unfiltered: "first recorded date" means the first, not
     * the first inside whatever range is being viewed.
     *
     * @param  array<int, int>  $enrollmentIds
     * @return array<string, mixed>
     */
    private function overallSummary(array $enrollmentIds, string $recordType): array
    {
        $totals = $this->query($enrollmentIds, $recordType, ['date_from' => null, 'date_to' => null])
            ->selectRaw('count(*) as total, min(record_date) as first_date, max(record_date) as last_date')
            ->first();

        return [
            'total' => (int) ($totals->total ?? 0),
            'first_date' => $totals?->first_date === null ? null : Carbon::parse($totals->first_date),
            'last_date' => $totals?->last_date === null ? null : Carbon::parse($totals->last_date),
        ];
    }

    /**
     * Count the days each kind of work was recorded on, in the period.
     *
     * Counts of days, in SQL. A day counts towards "Sabaq" when anything
     * behind that label was filled in - the lesson, the quantity, or both.
     * What was written is never added up.
     *
     * @param  array<int, int>  $enrollmentIds
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function periodSummary(array $enrollmentIds, string $recordType, array $filters): array
    {
        $query = $this->query($enrollmentIds, $recordType, $filters)
            ->selectRaw('count(*) as recorded_days')
            ->addSelect(DB::raw('count(distinct teacher_id) as teachers'));

        $labels = MadrassaDailyRecord::summaryFieldsFor($recordType);

        foreach ($labels as $label => $fields) {
            $query->addSelect(DB::raw(
                MadrassaDailyRecord::recordedDaysExpression($fields).' as '.$this->countColumn($label)
            ));
        }

        $row = $query->first();

        $byLabel = [];

        foreach (array_keys($labels) as $label) {
            $column = $this->countColumn($label);
            $byLabel[$label] = (int) ($row->{$column} ?? 0);
        }

        return [
            'recorded_days' => (int) ($row->recorded_days ?? 0),
            'teachers' => (int) ($row->teachers ?? 0),
            'days_by_label' => $byLabel,
        ];
    }

    /**
     * Name the teachers who took lessons in the period.
     *
     * A short list by construction - one madrassa class has a handful of
     * teachers - so it is loaded rather than counted only.
     *
     * @param  array<int, int>  $enrollmentIds
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Teacher>
     */
    private function teachersInPeriod(array $enrollmentIds, string $recordType, array $filters)
    {
        $ids = $this->query($enrollmentIds, $recordType, $filters)
            ->whereNotNull('teacher_id')
            ->distinct()
            ->pluck('teacher_id')
            ->all();

        return Teacher::whereIn('id', $ids)->orderBy('full_name')->get(['id', 'full_name', 'teacher_id']);
    }

    /**
     * Load the student's most recent record, whatever the period shows.
     *
     * "Most recent Sabaq" is a fact about the student, not about the range
     * being viewed, so this ignores the date filters.
     *
     * @param  array<int, int>  $enrollmentIds
     */
    private function latestRecord(array $enrollmentIds, string $recordType): ?MadrassaDailyRecord
    {
        return $this->query($enrollmentIds, $recordType, ['date_from' => null, 'date_to' => null])
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
     * Turn a summary label into a safe SQL column alias.
     */
    private function countColumn(string $label): string
    {
        return 'days_with_'.strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $label));
    }

    /**
     * Read the period filters off the request.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'date_from' => $this->cleanedDate($request->input('date_from')),
            'date_to' => $this->cleanedDate($request->input('date_to')),
        ];
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
}
