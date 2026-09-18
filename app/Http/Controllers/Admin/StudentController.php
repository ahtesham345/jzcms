<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\DisciplineRecord;
use App\Models\MadrassaDailyRecord;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\StudentResult;
use App\Support\AcademicPlacement;
use App\Support\StudentRegistrationNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Student::with([
            'academicSession',
            'department',
            'academicClass',
            'section',
            // The Class column reads every current placement, so a
            // Hifz + School student shows both of theirs. Loaded with
            // the enrollments rather than per row: two more queries for
            // the page, not two per student.
            'activeAcademicEnrollments.academicClass',
            'activeAcademicEnrollments.computerCourseSemester',
        ]);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('registration_number', 'like', "%{$search}%")
                    ->orWhere('roll_number', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('father_name', 'like', "%{$search}%");
            });
        }

        // Filter by Academic Session
        if ($request->filled('academic_session_id')) {
            $query->where('academic_session_id', $request->academic_session_id);
        }

        // Placement filters read the enrollments, never the student row.
        // The row holds a single placement - the madrassa one for a
        // dual-track student - so filtering it hid every Hifz + School
        // student from the School department, its classes and its sections.
        //
        // All three conditions go inside one whereHas, so they have to be
        // satisfied by the same enrollment. "Hifz + Class 7" therefore means
        // one placement that is both, never a student who is in Hifz on one
        // track and in Class 7 on the other.
        //
        // activeAcademicEnrollments() carries the project's own definition
        // of a current placement, so a completed enrollment cannot make a
        // student answer a filter about where they are now.
        $placement = array_filter(
            [
                'department_id' => $request->input('department_id'),
                'academic_class_id' => $request->input('academic_class_id'),
                'section_id' => $request->input('section_id'),
            ],
            fn ($value) => $value !== null && $value !== ''
        );

        if ($placement !== []) {
            $query->whereHas('activeAcademicEnrollments', function (Builder $enrollment) use ($placement) {
                foreach ($placement as $column => $value) {
                    $enrollment->where($column, $value);
                }
            });
        }

        // Filter by Student Status
        if ($request->filled('student_status')) {
            $query->where('student_status', $request->student_status);
        }

        // Filter by Student Type
        if ($request->filled('student_type')) {
            $query->where('student_type', $request->student_type);
        }

        $students = $query->latest()->paginate(10)->withQueryString();

        return view('students.index', [
            'students' => $students,
            'academicSessions' => AcademicSession::where('status', true)->orderBy('name')->get(),
            // Department -> class -> section, the same maps the Add Student
            // form narrows its placement selects with. The filter row narrows
            // the same way rather than keeping a second copy of them, so a
            // class can never be offered under a department it does not
            // belong to.
            ...AcademicPlacement::formOptions(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('students.create', [
            'academicSessions' => AcademicSession::where('status', true)->orderBy('name')->get(),
            // Pre-selected in the form. The admin may still file a student
            // into another open session, but the year the institution is
            // actually running is what the form offers first.
            'currentSessionId' => AcademicSession::currentId(),
            // Only parents that already exist. Linking one is optional and
            // never creates a parent record, so nothing offered here can
            // duplicate a family that is already on file.
            'linkableParents' => ParentGuardian::orderBy('full_name')->get(),
            'relationshipTypes' => ParentGuardian::RELATIONSHIP_TYPES,
            'studentTypes' => AdmissionApplication::STUDENT_TYPES,
            // Department -> class -> section, from the same Master Data and
            // the same student-type mapping Admission Management places
            // applicants with.
            ...AcademicPlacement::formOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudentRequest $request)
    {
        $data = $request->validated();

        // validated() hands back the UploadedFile itself, which must never
        // reach the column. Drop it, then store only the resulting path.
        unset($data['photo']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('students', 'public');
        }

        // Registration number generation can collide under concurrent
        // creation, so retry the whole creation on a unique constraint
        // violation. The same strategy admission approval uses.
        $attempts = 0;

        while (true) {
            try {
                $student = $this->createWithPlacement($data);

                return redirect()
                    ->route('students.index')
                    ->with('success', 'Student created successfully.');
            } catch (UniqueConstraintViolationException $e) {
                if (++$attempts >= 3) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Create the student, their enrollments and their parent link together.
     *
     * One transaction, so a student can never be left behind without the
     * academic placement that says where they are - which is exactly what
     * manual creation used to produce. The rows written here are the ones an
     * admission approval writes, through the same helpers.
     *
     * @param  array<string, mixed>  $data
     */
    private function createWithPlacement(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            // Validation has already confirmed each department belongs to the
            // student type's track, each class to its department and each
            // section to its class.
            $placements = AcademicPlacement::fromValidated($data);
            $primary = AcademicPlacement::primary($placements);

            $session = AcademicSession::findOrFail($data['academic_session_id']);

            $student = Student::create([
                ...$this->studentAttributes($data),
                'registration_number' => StudentRegistrationNumber::next($session),
                // The students table holds one placement. A Hifz + School
                // student is recorded against the madrassa side here and
                // keeps the school side on its own enrollment, which is the
                // rule the admission approval already follows.
                'department_id' => $primary['department_id'],
                'academic_class_id' => $primary['academic_class_id'],
                'section_id' => $primary['section_id'],
            ]);

            foreach ($placements as $side => $placement) {
                AcademicPlacement::recordEnrollment(
                    $student,
                    AcademicPlacement::track($side),
                    $placement['department_id'],
                    $placement['academic_class_id'],
                    $placement['section_id'],
                    $data['admission_date'],
                    (int) $data['academic_session_id'],
                    $placement['computer_course_semester_id'] ?? null,
                );
            }

            $this->linkParent($student, $data);

            return $student;
        });
    }

    /**
     * Link the student to a parent.
     *
     * An existing parent chosen by the admin is linked as they described it,
     * through the same pivot the profile pages use. Otherwise the father is
     * recorded from the student's own father_name and father_mobile, exactly
     * as an admission approval does, so a manually created student is never
     * left with no family on file.
     *
     * @param  array<string, mixed>  $data
     */
    private function linkParent(Student $student, array $data): void
    {
        if (empty($data['parent_id'])) {
            ParentGuardian::createFatherFor($student);

            return;
        }

        ParentGuardian::findOrFail($data['parent_id'])->linkStudent(
            $student->id,
            $data['parent_relationship_type'],
            (bool) ($data['parent_is_primary'] ?? false),
        );
    }

    /**
     * Strip the fields that are not columns on the students table.
     *
     * The placement fields are resolved into the student's own department,
     * class and section by the caller; the parent fields describe a link,
     * not the student.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function studentAttributes(array $data): array
    {
        return Arr::except($data, [
            'department_id', 'academic_class_id', 'section_id',
            'madrassa_department_id', 'madrassa_class_id', 'madrassa_section_id',
            'school_department_id', 'school_class_id', 'school_section_id',
            'parent_id', 'parent_relationship_type', 'parent_is_primary',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $student = Student::with([
            'academicSession',
            'department',
            'academicClass',
            'section',
            'academicEnrollments.academicSession',
            'academicEnrollments.department',
            'academicEnrollments.academicClass',
            'academicEnrollments.section',
            'parents',
        ])->findOrFail($id);

        return view('students.show', [
            'student' => $student,
            // Null unless the student has a current madrassa placement on a
            // programme the daily record module covers, which is what
            // decides whether the profile shows that section at all. A
            // school-only student gets nothing rather than a dead link.
            'madrassaDailyRecordSummary' => MadrassaDailyRecord::summaryForStudent($student),
            // Null unless the student has held a madrassa enrollment, which
            // is what decides whether the profile shows the Results section
            // at all. A school-only student gets nothing rather than a
            // panel implying madrassa results exist for them.
            'madrassaResultSummary' => StudentResult::summaryForStudent($student),
            // Counts and the latest incident date, resolved by one
            // aggregate query rather than by loading the student's
            // records. Always present, for every programme: unlike the
            // madrassa sections above, discipline applies to the whole
            // school, and a student with nothing on file is shown as Good
            // rather than shown nothing.
            'disciplineSummary' => DisciplineRecord::summaryForStudent($student),
            // The "Add Enrollment" form lives on this page, so it needs the
            // same options the enrollment controller builds for its edit form.
            ...StudentAcademicEnrollmentController::enrollmentFormOptions(),
            // Only parents that are not linked to this student already.
            'linkableParents' => ParentGuardian::whereNotIn('id', $student->parents->pluck('id'))
                ->orderBy('full_name')
                ->get(),
            'relationshipTypes' => ParentGuardian::RELATIONSHIP_TYPES,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $student = Student::with('activeAcademicEnrollments')->findOrFail($id);

        return view('students.edit', [
            'student' => $student,
            'academicSessions' => AcademicSession::where('status', true)->orderBy('name')->get(),
            'studentTypes' => AdmissionApplication::STUDENT_TYPES,
            // What the two Hifz + School placements start out showing: the
            // student's current enrollment on each track, so editing one
            // begins from where the student actually is.
            'placementByTrack' => $student->activeAcademicEnrollments->keyBy('academic_track'),
            ...AcademicPlacement::formOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentRequest $request, string $id)
    {
        $student = Student::findOrFail($id);
        $data = $request->validated();

        unset($data['photo']);

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($student->photo && \Storage::disk('public')->exists($student->photo)) {
                \Storage::disk('public')->delete($student->photo);
            }

            $data['photo'] = $request->file('photo')->store('students', 'public');
        }

        $this->updateWithPlacement($student, $data);

        return redirect()
            ->route('students.index')
            ->with('success', 'Student updated successfully.');
    }

    /**
     * Save the edited student and keep their enrollments in step.
     *
     * The placement columns on the student and the enrollment rows describe
     * the same thing, so they are written together: moving a student's class
     * on this form moves the enrollment that records it, and a student who
     * never had one - created before this form wrote enrollments - gets the
     * enrollment they were missing.
     *
     * Enrollment history is not rewritten. Only the current enrollment of
     * each track the student type uses is touched; completed enrollments,
     * and the other track's rows when a student type no longer spans it, are
     * left exactly as recorded and stay managed from the profile.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateWithPlacement(Student $student, array $data): void
    {
        DB::transaction(function () use ($student, $data) {
            $placements = AcademicPlacement::fromValidated($data);
            $primary = AcademicPlacement::primary($placements);

            $student->update([
                ...$this->studentAttributes($data),
                'department_id' => $primary['department_id'],
                'academic_class_id' => $primary['academic_class_id'],
                'section_id' => $primary['section_id'],
            ]);

            foreach ($placements as $side => $placement) {
                $this->syncEnrollment($student, AcademicPlacement::track($side), $placement, $data);
            }
        });
    }

    /**
     * Bring one track's current enrollment in line with the edited placement.
     *
     * @param  array{department_id: int, academic_class_id: int, section_id: int|null}  $placement
     * @param  array<string, mixed>  $data
     */
    private function syncEnrollment(Student $student, string $track, array $placement, array $data): void
    {
        $sessionId = (int) $data['academic_session_id'];

        // The active enrollment is the one this form describes. Falling back
        // to any enrollment already held for the chosen session keeps a
        // student whose current enrollment was completed from gaining a
        // second row for a session that already has one.
        $enrollment = $student->activeEnrollmentForTrack($track)
            ?? $student->academicEnrollments()
                ->where('academic_track', $track)
                ->where('academic_session_id', $sessionId)
                ->first();

        if ($enrollment === null) {
            AcademicPlacement::recordEnrollment(
                $student,
                $track,
                $placement['department_id'],
                $placement['academic_class_id'],
                $placement['section_id'],
                $data['admission_date'],
                $sessionId,
                $placement['computer_course_semester_id'] ?? null,
            );

            return;
        }

        // One enrollment per student per session per track, so the session is
        // only moved when nothing else on this track already holds it. A
        // promotion is how a student is moved between sessions; this only
        // avoids leaving the two records disagreeing when it safely can.
        $sessionTaken = $student->academicEnrollments()
            ->where('academic_track', $track)
            ->where('academic_session_id', $sessionId)
            ->where('id', '!=', $enrollment->id)
            ->exists();

        $enrollment->update([
            'department_id' => $placement['department_id'],
            'academic_class_id' => $placement['academic_class_id'],
            'section_id' => $placement['section_id'],
            // Only a Computer placement carries one. The key is absent
            // on every other side, so nothing else is touched.
            ...(array_key_exists('computer_course_semester_id', $placement)
                ? ['computer_course_semester_id' => $placement['computer_course_semester_id']]
                : []),
            // A semester-track placement keeps the session it began in. The
            // Computer course runs across about three academic sessions, so
            // its session is the year the student started the course and not
            // the year the placement belongs to - moving it when the student
            // is filed into a new session would erase when they began. Where
            // the student stands in the course is the semester above, which
            // no session change touches.
            ...(($sessionTaken || $enrollment->usesSemesters()) ? [] : ['academic_session_id' => $sessionId]),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $student = Student::findOrFail($id);

        // Delete photo if exists
        if ($student->photo && \Storage::disk('public')->exists($student->photo)) {
            \Storage::disk('public')->delete($student->photo);
        }

        $student->delete();

        return redirect()
            ->route('students.index')
            ->with('success', 'Student deleted successfully.');
    }
}
