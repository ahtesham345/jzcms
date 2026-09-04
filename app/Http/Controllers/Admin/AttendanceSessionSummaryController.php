<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentAttendance;
use App\Support\SessionAttendanceSummary;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A whole academic session's attendance, for one track.
 *
 * A reading page. It answers two questions at once: how the students who
 * were marked are doing, and how much of the paper register has not been
 * transcribed yet. The second is why attendance opportunities are counted
 * from the session and enrollment dates rather than from the rows on file.
 *
 * The arithmetic lives in SessionAttendanceSummary, so this page, its
 * printout and its export cannot drift apart.
 */
class AttendanceSessionSummaryController extends Controller
{
    /**
     * How many students to show per page.
     */
    private const PER_PAGE = 25;

    /**
     * Display the session summary.
     */
    public function index(Request $request)
    {
        [$session, $filters] = $this->resolve($request);

        if ($session === null) {
            return view('attendance.session-summary', $this->emptyState($filters));
        }

        $summary = new SessionAttendanceSummary($session, $filters);

        return view('attendance.session-summary', [
            ...$this->common($session, $filters, $summary),
            'students' => $this->paginate($this->decorate($summary->students()), $request),
        ]);
    }

    /**
     * Show the printable version.
     *
     * Every matching student rather than one page of them.
     */
    public function printSummary(Request $request)
    {
        [$session, $filters] = $this->resolve($request);

        if ($session === null) {
            return redirect()
                ->route('attendance.session-summary', array_filter($filters, fn ($value) => $value !== null && $value !== ''))
                ->with('error', 'Choose an academic session before printing the summary.');
        }

        $summary = new SessionAttendanceSummary($session, $filters);

        return view('attendance.session-summary-print', [
            ...$this->common($session, $filters, $summary),
            'students' => collect($this->decorate($summary->students())),
        ]);
    }

    /**
     * Stream the summary as a CSV.
     *
     * Every matching student, whatever the page size.
     */
    public function export(Request $request): StreamedResponse
    {
        [$session, $filters] = $this->resolve($request);

        abort_if($session === null, 404, 'No academic session to export.');

        $summary = new SessionAttendanceSummary($session, $filters);
        $students = $this->decorate($summary->students());

        $filename = sprintf(
            'session-attendance-%s-%s.csv',
            str_replace([' ', '/'], '-', strtolower($session->name)),
            strtolower($filters['academic_track'])
        );

        return response()->streamDownload(function () use ($students, $session, $filters) {
            $handle = fopen('php://output', 'w');

            // A byte order mark, so Excel reads the Urdu and Arabic class
            // names as UTF-8 rather than as mojibake.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Student Name',
                'Registration Number',
                'Roll Number',
                'Academic Session',
                'Department',
                'Class',
                'Section',
                'Track',
                'Teaching Days',
                'Attendance Opportunities',
                'Recorded',
                'Unrecorded',
                'Present',
                'Absent',
                'Attendance Percentage',
            ]);

            foreach ($students as $student) {
                fputcsv($handle, [
                    $student['full_name'],
                    $student['registration_number'],
                    $student['roll_number'],
                    $session->name,
                    $student['department'],
                    $student['class'],
                    $student['section'],
                    $filters['academic_track'],
                    $student['teaching_days'],
                    $student['opportunities'],
                    $student['recorded'],
                    $student['unrecorded'],
                    $student['present'],
                    $student['absent'],
                    // N/A rather than a zero: nothing recorded is not nought
                    // percent attendance.
                    $student['percentage'] === null ? 'N/A' : number_format($student['percentage'], 2),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * The data the page, the printout and the export all share.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function common(AcademicSession $session, array $filters, SessionAttendanceSummary $summary): array
    {
        return [
            'session' => $session,
            'filters' => $filters,
            'totals' => $summary->totals(),
            'months' => $summary->months(),
            'periods' => $summary->periods(),
            'sessionStart' => $summary->start(),
            'sessionEnd' => $summary->end(),
            'group' => $this->groupHeading($filters),
            'academicTracks' => StudentAcademicEnrollment::ACADEMIC_TRACKS,
            ...$this->filterOptions(),
        ];
    }

    /**
     * What the page shows when there is no session to report on.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function emptyState(array $filters): array
    {
        return [
            'session' => null,
            'filters' => $filters,
            'totals' => null,
            'months' => [],
            'periods' => StudentAttendance::periodsForTrack($filters['academic_track']),
            'sessionStart' => null,
            'sessionEnd' => null,
            'group' => $this->groupHeading($filters),
            'academicTracks' => StudentAcademicEnrollment::ACADEMIC_TRACKS,
            'students' => $this->paginate(collect(), request()),
            ...$this->filterOptions(),
        ];
    }

    /**
     * Read the filters off the request.
     *
     * The track has no "all" option: school and madrassa sit different
     * registers, and one percentage covering both would mean nothing.
     *
     * @return array{0: AcademicSession|null, 1: array<string, mixed>}
     */
    private function resolve(Request $request): array
    {
        $track = $request->input('academic_track');
        $track = in_array($track, StudentAcademicEnrollment::ACADEMIC_TRACKS, true)
            ? $track
            : StudentAcademicEnrollment::ACADEMIC_TRACKS[0];

        // Validated against the table rather than trusted: an unknown id
        // falls back to the session being worked in.
        $session = AcademicSession::find($this->cleaned($request->input('academic_session_id')))
            ?? AcademicSession::where('is_current', true)->first()
            ?? AcademicSession::orderByDesc('start_date')->first();

        return [$session, [
            'academic_session_id' => $session?->id,
            'academic_track' => $track,
            'department_id' => $this->cleaned($request->input('department_id')),
            'academic_class_id' => $this->cleaned($request->input('academic_class_id')),
            'section_id' => $this->cleaned($request->input('section_id')),
            'search' => $this->cleaned($request->input('search')),
        ]];
    }

    /**
     * Put the placement names onto the computed student rows.
     *
     * Resolved from lookup maps rather than one query per student, and from
     * the enrollment rather than from the student's current placement: a
     * promoted student's session still reports the class they sat it in.
     *
     * @param  array<int, array<string, mixed>>  $students
     * @return Collection<int, array<string, mixed>>
     */
    private function decorate(array $students): Collection
    {
        $rows = collect($students);

        $departments = Department::whereIn('id', $rows->pluck('department_id')->filter()->unique())->pluck('name', 'id');
        $classes = AcademicClass::whereIn('id', $rows->pluck('academic_class_id')->filter()->unique())->pluck('name', 'id');
        $sections = Section::whereIn('id', $rows->pluck('section_id')->filter()->unique())->pluck('name', 'id');

        return $rows->map(function (array $student) use ($departments, $classes, $sections) {
            $student['department'] = $departments[$student['department_id']] ?? '—';
            $student['class'] = $classes[$student['academic_class_id']] ?? '—';
            $student['section'] = $student['section_id'] ? ($sections[$student['section_id']] ?? '—') : 'No section';

            return $student;
        });
    }

    /**
     * Page the computed rows.
     *
     * The rows are already in hand: they come from enrollment records, one
     * per student per placement, and the windows have to be merged in PHP
     * before anything can be counted. Slicing them here keeps the page a
     * page without a second trip to the database.
     *
     * @param  Collection<int, array<string, mixed>>  $students
     */
    private function paginate(Collection $students, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $students->forPage($page, self::PER_PAGE)->values(),
            $students->count(),
            self::PER_PAGE,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    /**
     * Resolve the names the heading is built from.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function groupHeading(array $filters): array
    {
        return [
            'track' => $filters['academic_track'],
            'department' => Department::find($filters['department_id']),
            'academicClass' => AcademicClass::find($filters['academic_class_id']),
            'section' => $filters['section_id'] ? Section::find($filters['section_id']) : null,
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
     * Get the options the filter selects are built from.
     *
     * Every session, department, class and section, active or not: a session
     * that has ended still has to be reportable.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'academicSessions' => AcademicSession::orderByDesc('start_date')->get(),
            'departments' => Department::orderBy('name')->get(),
            'classesByDepartment' => AcademicClass::orderBy('name')
                ->get(['id', 'name', 'department_id'])
                ->groupBy('department_id')
                ->map(fn ($classes) => $classes->map->only(['id', 'name'])->values()),
            'sectionsByClass' => Section::orderBy('name')
                ->get(['id', 'name', 'academic_class_id'])
                ->groupBy('academic_class_id')
                ->map(fn ($sections) => $sections->map->only(['id', 'name'])->values()),
        ];
    }
}
