<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDisciplineRecordRequest;
use App\Http\Requests\Admin\UpdateDisciplineRecordRequest;
use App\Models\DisciplineRecord;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * The Discipline module.
 *
 * An incident that has been recorded against a student: what happened, how
 * serious it was, and what the office did about it. It is not attendance,
 * which answers a different question in its own module, and it is not a
 * result.
 *
 * Every record belongs to a student directly rather than to one of their
 * academic enrollments. That is the module's one structural decision and it
 * is deliberate: a fight in April stays on the student's record after they
 * are promoted in July, and no promotion, transfer or new session rewrites
 * an incident that has already been written down.
 *
 * There is no destroy route, following Hifz and Results. A mistake is
 * corrected on the record it was made on; a student's discipline history is
 * not something an admin deletes.
 */
class DisciplineRecordController extends Controller
{
    /**
     * How many discipline records to show per page.
     */
    private const PER_PAGE = 20;

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
        'category',
        'severity',
        'date_from',
        'date_to',
    ];

    /**
     * Display the discipline records.
     */
    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $records = $this->query($filters)
            // Eager loaded: every row renders the student and the recorder.
            // Without this the page would cost two queries per record and
            // the count would grow with the number of records rather than
            // staying flat.
            ->with(['student', 'recorder'])
            ->inIncidentOrder()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('discipline.index', [
            'records' => $records,
            'filters' => $filters,
            'categories' => DisciplineRecord::CATEGORIES,
            'severities' => DisciplineRecord::SEVERITIES,
            // One aggregate query over the same filtered set, so the cards
            // describe what the table is showing rather than the whole
            // table.
            'summary' => DisciplineRecord::summarise($this->query($filters)),
            'hasFilters' => $this->hasFilters($filters),
        ]);
    }

    /**
     * Show the form for recording a discipline record.
     */
    public function create(Request $request)
    {
        return view('discipline.create', [
            'record' => null,
            'students' => $this->selectableStudents(),
            'categories' => DisciplineRecord::CATEGORIES,
            'severities' => DisciplineRecord::SEVERITIES,
            // Pre-selected when the form was opened from a student's
            // profile or history. Only ever a default for the select; the
            // request that follows is validated on its own terms.
            'selectedStudentId' => $request->filled('student_id')
                ? (int) $request->input('student_id')
                : null,
            // Carried through the form so saving returns to the list the
            // form was opened from rather than to an unfiltered page.
            'returnFilters' => $this->returnFilters($request),
        ]);
    }

    /**
     * Store a newly recorded discipline record.
     *
     * The recorder is stamped here, from the authenticated user. It is not
     * in the validated data because it is not a field the request may
     * carry, and it is not in the model's fillable list either, so this is
     * the only way a record gets one.
     */
    public function store(StoreDisciplineRecordRequest $request)
    {
        $record = new DisciplineRecord($request->validated());
        $record->recorded_by = $request->user()->id;
        $record->save();

        return $this->backToIndex(
            $request,
            'Discipline record saved for '.$record->student->full_name
                .' on '.$record->date->format('d M, Y').'.'
        );
    }

    /**
     * Display one discipline record in full.
     */
    public function show(DisciplineRecord $discipline)
    {
        $discipline->load(['student', 'recorder']);

        return view('discipline.show', [
            'record' => $discipline,
            'student' => $discipline->student,
        ]);
    }

    /**
     * Show the form for correcting a discipline record.
     */
    public function edit(Request $request, DisciplineRecord $discipline)
    {
        $discipline->load(['student', 'recorder']);

        return view('discipline.edit', [
            'record' => $discipline,
            'students' => $this->selectableStudents(),
            'categories' => DisciplineRecord::CATEGORIES,
            'severities' => DisciplineRecord::SEVERITIES,
            'selectedStudentId' => $discipline->student_id,
            'returnFilters' => $this->returnFilters($request),
        ]);
    }

    /**
     * Update a discipline record.
     *
     * recorded_by is not among the changes. It says who first wrote the
     * incident down, and correcting the text of a record does not make the
     * corrector its author.
     */
    public function update(UpdateDisciplineRecordRequest $request, DisciplineRecord $discipline)
    {
        $discipline->update($request->validated());

        return $this->backToIndex(
            $request,
            'Discipline record updated for '.$discipline->student->full_name
                .' on '.$discipline->date->format('d M, Y').'.'
        );
    }

    /* ------------------------------------------------------------------ */
    /* The listing */
    /* ------------------------------------------------------------------ */

    /**
     * Build the record query from the filters.
     *
     * Every filter is a separate where on the same builder, so all of them
     * combine with AND: a High severity in the Uniform category means both,
     * never either. Anything the filters reduced to null is skipped rather
     * than compared against, so an unrecognised category narrows nothing
     * instead of narrowing to nothing.
     *
     * @param  array<string, mixed>  $filters
     */
    private function query(array $filters)
    {
        return DisciplineRecord::query()
            ->when($filters['category'], fn ($query, $category) => $query->where('category', $category))
            ->when($filters['severity'], fn ($query, $severity) => $query->where('severity', $severity))
            ->when($filters['date_from'], fn ($query, $from) => $query->whereDate('date', '>=', $from))
            ->when($filters['date_to'], fn ($query, $to) => $query->whereDate('date', '<=', $to))
            // Resolved in SQL, against the student the record belongs to.
            // Loading the students to search them in PHP would grow with
            // the roll rather than with the page.
            ->when($filters['search'], function ($query, $search) {
                $query->whereHas('student', function ($student) use ($search) {
                    $student->where('full_name', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%")
                        ->orWhere('roll_number', 'like', "%{$search}%");
                });
            });
    }

    /* ------------------------------------------------------------------ */
    /* Shared helpers */
    /* ------------------------------------------------------------------ */

    /**
     * Get the students the form offers.
     *
     * Every student on the roll, newest registration first is not useful
     * here - the select is read by name - so they are ordered by name with
     * the registration number shown beside it. Only the four columns the
     * option needs are loaded.
     *
     * @return Collection<int, Student>
     */
    private function selectableStudents()
    {
        return Student::orderBy('full_name')
            ->get(['id', 'full_name', 'registration_number', 'roll_number']);
    }

    /**
     * Read the index filters off the request.
     *
     * Anything absent or unrecognised is null rather than an empty string.
     * The category and the severity are narrowed to the module's own lists
     * here, so a hand-edited query string cannot reach the query at all.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'search' => $request->filled('search') ? trim((string) $request->input('search')) : null,
            'category' => DisciplineRecord::normalizeCategory($request->input('category')),
            'severity' => DisciplineRecord::normalizeSeverity($request->input('severity')),
            'date_from' => DisciplineRecord::normalizeDate($request->input('date_from')),
            'date_to' => DisciplineRecord::normalizeDate($request->input('date_to')),
        ];
    }

    /**
     * Determine whether any filter is actually in effect.
     *
     * @param  array<string, mixed>  $filters
     */
    private function hasFilters(array $filters): bool
    {
        return array_filter($filters, fn ($value) => $value !== null && $value !== '') !== [];
    }

    /**
     * Read the filters a record form is carrying back to the list.
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
     * Send the request back to the list it came from.
     */
    private function backToIndex(Request $request, string $success)
    {
        return redirect()
            ->route('discipline.index', $this->returnFilters($request))
            ->with('success', $success);
    }
}
