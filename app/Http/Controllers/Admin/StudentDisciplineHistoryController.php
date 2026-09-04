<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DisciplineRecord;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Http\Request;

/**
 * One student's complete discipline history.
 *
 * A viewing page. Records are written and corrected in the Discipline
 * module, which is the module's only writer; nothing here creates or edits
 * one.
 *
 * The student comes from the route binding and every record shown is drawn
 * with that student's id as the leading condition. There is deliberately no
 * student filter: a query string can narrow this page by category, severity
 * or date, but it names nothing that could widen it, so no edited parameter
 * reaches another student's records.
 *
 * The header shows the student's placement as it stands today. That is all
 * it is: no row in the table below claims the incident happened in that
 * class, because a discipline record is attached to the student and never
 * carried a class in the first place.
 */
class StudentDisciplineHistoryController extends Controller
{
    /**
     * How many discipline records to show per page.
     */
    private const PER_PAGE = 20;

    /**
     * Display the student's discipline history.
     */
    public function index(Request $request, Student $student)
    {
        $filters = $this->filters($request);

        $records = $this->query($student, $filters)
            // Eager loaded: every row names its recorder. Without this the
            // page would cost one query per record.
            ->with('recorder')
            ->inIncidentOrder()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Two summaries, deliberately. The cards describe what the filters
        // have narrowed to; the status describes the student, so it is
        // taken from their whole history and does not move when a filter
        // hides the High incident behind it.
        $filteredSummary = DisciplineRecord::summarise($this->query($student, $filters));
        $overall = DisciplineRecord::summaryForStudent($student);

        return view('students.discipline', [
            'student' => $student,
            'records' => $records,
            'filters' => $filters,
            'categories' => DisciplineRecord::CATEGORIES,
            'severities' => DisciplineRecord::SEVERITIES,
            'summary' => $filteredSummary,
            'status' => $overall['status'],
            'overallTotal' => $overall['total'],
            // The student's current placement, for the header only. It is
            // never used to describe an incident.
            'currentEnrollment' => $this->currentEnrollment($student),
            'hasFilters' => array_filter(
                $filters,
                fn ($value) => $value !== null && $value !== ''
            ) !== [],
        ]);
    }

    /**
     * Build the record query from the filters.
     *
     * The student is the first condition and is not optional: it is what
     * bounds the page to the student the route named. Everything else only
     * narrows, and all of it combines with AND.
     *
     * @param  array<string, mixed>  $filters
     */
    private function query(Student $student, array $filters)
    {
        return DisciplineRecord::query()
            ->forStudent($student->id)
            ->when($filters['category'], fn ($query, $category) => $query->where('category', $category))
            ->when($filters['severity'], fn ($query, $severity) => $query->where('severity', $severity))
            ->when($filters['date_from'], fn ($query, $from) => $query->whereDate('date', '>=', $from))
            ->when($filters['date_to'], fn ($query, $to) => $query->whereDate('date', '<=', $to));
    }

    /**
     * Get the student's current placement, for the page header.
     *
     * The active enrollment, or the most recent one when the student no
     * longer holds an active placement. Null for a student who has never
     * been enrolled, in which case the header simply shows fewer facts -
     * the discipline history itself does not depend on it.
     */
    private function currentEnrollment(Student $student): ?StudentAcademicEnrollment
    {
        $enrollment = $student->activeAcademicEnrollment()->first()
            ?? $student->academicEnrollments()
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();

        return $enrollment?->loadMissing([
            'academicSession',
            'department',
            'academicClass',
            'section',
        ]);
    }

    /**
     * Read the history filters off the request.
     *
     * Anything absent or unrecognised is null. There is deliberately no
     * student filter here: the student is the route, which is what makes
     * another student's records unreachable from this page however the
     * query string is edited.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'category' => DisciplineRecord::normalizeCategory($request->input('category')),
            'severity' => DisciplineRecord::normalizeSeverity($request->input('severity')),
            'date_from' => DisciplineRecord::normalizeDate($request->input('date_from')),
            'date_to' => DisciplineRecord::normalizeDate($request->input('date_to')),
        ];
    }
}
