<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Http\Request;

/**
 * The central view of every student's academic record.
 *
 * Reads the existing student_academic_enrollments table: there is no second
 * academic record store. One row per enrollment, so a Hifz + School student
 * appears once per active track rather than having one of them hidden.
 *
 * Managing an individual record still happens on the student profile, and
 * the actions here link to those existing workflows.
 */
class AcademicController extends Controller
{
    /**
     * How many academic records to show per page.
     */
    private const PER_PAGE = 20;

    /**
     * Display the academic records.
     */
    public function index(Request $request)
    {
        $records = $this->filteredQuery($request)
            // A student's tracks stay next to each other, newest first.
            ->orderByDesc('start_date')
            ->orderBy('student_id')
            ->orderBy('academic_track')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('academics.index', [
            'records' => $records,
            'summary' => $this->summary(),
            // The same option lists the enrollment and promotion forms use.
            ...StudentAcademicEnrollmentController::enrollmentFormOptions(),
        ]);
    }

    /**
     * Display one enrollment as an academic record.
     *
     * The record itself is the source for the current placement: the
     * student's own placement columns are not consulted, since they cannot
     * represent both tracks of a dual-track student.
     */
    public function showEnrollment(string $enrollment)
    {
        $record = StudentAcademicEnrollment::with([
            'student',
            'academicSession',
            'department',
            'academicClass',
            'section',
        ])->findOrFail($enrollment);

        return view('academics.enrollments.show', [
            'record' => $record,
            // The same student on the same track only. A dual-track
            // student's other track is a separate academic record and is
            // never folded into this history.
            'trackHistory' => $this->recordsFor($record->student_id)
                ->where('academic_track', $record->academic_track)
                ->orderBy('start_date')
                ->orderBy('id')
                ->get(),
            // Shown only when it exists.
            'otherActiveTrack' => $this->recordsFor($record->student_id)
                ->where('academic_track', '!=', $record->academic_track)
                ->where('status', 'Active')
                ->first(),
        ]);
    }

    /**
     * Display one academic group.
     *
     * A group is a session, track, department, class and section taken
     * together. It is a filtered view of the enrollments, not a stored
     * entity: nothing is grouped in the database.
     */
    public function group(Request $request)
    {
        $base = $this->groupQuery($request);

        // Defaults to the current members; the other statuses stay
        // reachable through the filter so the counts below are not dead
        // ends.
        $status = $request->input('status', 'Active');

        $records = (clone $base)
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->with(['student', 'academicSession', 'department', 'academicClass', 'section'])
            ->orderBy('student_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('academics.group', [
            'records' => $records,
            'statistics' => $this->groupStatistics($base),
            'status' => $status,
            'enrollmentStatuses' => StudentAcademicEnrollment::STATUSES,
            // Resolved for the heading only. A filter naming something that
            // no longer exists still filters; it just cannot be named.
            'session' => AcademicSession::find($request->input('session')),
            'department' => Department::find($request->input('department')),
            'academicClass' => AcademicClass::find($request->input('class')),
            'section' => Section::find($request->input('section')),
            'track' => $request->input('track'),
        ]);
    }

    /**
     * Build the group query from the drill-down parameters.
     *
     * Each parameter is an AND condition on the enrollment row, so a class
     * from another department matches nothing rather than one filter
     * quietly winning over the other.
     */
    private function groupQuery(Request $request)
    {
        return StudentAcademicEnrollment::query()
            ->when($request->filled('session'), fn ($query) => $query->where('academic_session_id', $request->input('session')))
            ->when($request->filled('track'), fn ($query) => $query->where('academic_track', $request->input('track')))
            ->when($request->filled('department'), fn ($query) => $query->where('department_id', $request->input('department')))
            ->when($request->filled('class'), fn ($query) => $query->where('academic_class_id', $request->input('class')))
            ->when($request->filled('section'), fn ($query) => $query->where('section_id', $request->input('section')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->whereHas('student', function ($student) use ($search) {
                    $student->where('full_name', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%")
                        ->orWhere('roll_number', 'like', "%{$search}%");
                });
            });
    }

    /**
     * Count the students in a group, by status.
     *
     * Distinct on student_id rather than rows. Within one fully specified
     * group the unique index already allows a student only one enrollment,
     * but a partly specified group spans sessions, and a student who was in
     * the same class twice must still count once.
     *
     * @return array<string, int>
     */
    private function groupStatistics($base): array
    {
        return [
            'total' => (clone $base)->distinct()->count('student_id'),
            'active' => (clone $base)->where('status', 'Active')->distinct()->count('student_id'),
            'completed' => (clone $base)->where('status', 'Completed')->distinct()->count('student_id'),
            'left' => (clone $base)->where('status', 'Left')->distinct()->count('student_id'),
        ];
    }

    /**
     * Start a query for one student's records, with the display relations.
     */
    private function recordsFor(int $studentId)
    {
        return StudentAcademicEnrollment::query()
            ->with(['academicSession', 'department', 'academicClass', 'section'])
            ->where('student_id', $studentId);
    }

    /**
     * Build the record query from the search and filters.
     *
     * Every filter is a condition on the enrollment row itself, so they
     * combine with AND: a department and a class from another department
     * simply match nothing rather than one quietly winning.
     *
     * Nothing here filters on whether a department, class or section is
     * still active. A record made when a class was open must stay visible
     * after that class is retired.
     */
    private function filteredQuery(Request $request)
    {
        return StudentAcademicEnrollment::query()
            // Eager loaded: the table renders all five per row.
            ->with([
                'student',
                'academicSession',
                'department',
                'academicClass',
                'section',
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                // Resolved in SQL, against the student the record belongs to.
                $query->whereHas('student', function ($student) use ($search) {
                    $student->where('full_name', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%")
                        ->orWhere('roll_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('academic_session_id'), fn ($query) => $query->where('academic_session_id', $request->input('academic_session_id')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->input('department_id')))
            ->when($request->filled('academic_class_id'), fn ($query) => $query->where('academic_class_id', $request->input('academic_class_id')))
            ->when($request->filled('section_id'), fn ($query) => $query->where('section_id', $request->input('section_id')))
            ->when($request->filled('academic_track'), fn ($query) => $query->where('academic_track', $request->input('academic_track')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')));
    }

    /**
     * Count the active academic records.
     *
     * The student counts are distinct on student_id, not row counts: a
     * Hifz + School student holds two active rows but is one madrassa
     * student, one school student and one dual-track student.
     *
     * @return array<string, int>
     */
    private function summary(): array
    {
        $active = fn () => StudentAcademicEnrollment::where('status', 'Active');

        return [
            'active_records' => $active()->count(),
            'madrassa_students' => $active()->where('academic_track', 'Madrassa')->distinct()->count('student_id'),
            'school_students' => $active()->where('academic_track', 'School')->distinct()->count('student_id'),
            'dual_track_students' => $active()
                ->where('academic_track', 'Madrassa')
                ->whereIn('student_id', function ($query) {
                    $query->select('student_id')
                        ->from('student_academic_enrollments')
                        ->where('status', 'Active')
                        ->where('academic_track', 'School');
                })
                ->distinct()
                ->count('student_id'),
        ];
    }
}
