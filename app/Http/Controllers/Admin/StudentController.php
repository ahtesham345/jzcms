<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\DisciplineRecord;
use App\Models\MadrassaDailyRecord;
use App\Models\ParentGuardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentResult;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Student::with(['academicSession', 'department', 'academicClass', 'section']);

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

        // Filter by Department
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Filter by Academic Class
        if ($request->filled('academic_class_id')) {
            $query->where('academic_class_id', $request->academic_class_id);
        }

        // Filter by Section
        if ($request->filled('section_id')) {
            $query->where('section_id', $request->section_id);
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

        // Get filter options
        $academicSessions = AcademicSession::where('status', true)->orderBy('name')->get();
        $departments = Department::where('status', true)->orderBy('name')->get();
        $academicClasses = AcademicClass::where('status', true)->orderBy('name')->get();
        $sections = Section::where('status', true)->orderBy('name')->get();

        return view('students.index', compact('students', 'academicSessions', 'departments', 'academicClasses', 'sections'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $academicSessions = AcademicSession::where('status', true)->orderBy('name')->get();
        $departments = Department::where('status', true)->orderBy('name')->get();
        $academicClasses = AcademicClass::where('status', true)->orderBy('name')->get();
        $sections = Section::where('status', true)->orderBy('name')->get();

        return view('students.create', compact('academicSessions', 'departments', 'academicClasses', 'sections'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudentRequest $request)
    {
        $data = $request->validated();

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('students', 'public');
        }

        Student::create($data);

        return redirect()
            ->route('students.index')
            ->with('success', 'Student created successfully.');
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
        $student = Student::findOrFail($id);
        $academicSessions = AcademicSession::where('status', true)->orderBy('name')->get();
        $departments = Department::where('status', true)->orderBy('name')->get();
        $academicClasses = AcademicClass::where('status', true)->orderBy('name')->get();
        $sections = Section::where('status', true)->orderBy('name')->get();

        return view('students.edit', compact('student', 'academicSessions', 'departments', 'academicClasses', 'sections'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentRequest $request, string $id)
    {
        $student = Student::findOrFail($id);
        $data = $request->validated();

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($student->photo && \Storage::disk('public')->exists($student->photo)) {
                \Storage::disk('public')->delete($student->photo);
            }

            $data['photo'] = $request->file('photo')->store('students', 'public');
        }

        $student->update($data);

        return redirect()
            ->route('students.index')
            ->with('success', 'Student updated successfully.');
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
