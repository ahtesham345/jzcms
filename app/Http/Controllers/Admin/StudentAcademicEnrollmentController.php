<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentEnrollmentRequest;
use App\Http\Requests\Admin\UpdateStudentEnrollmentRequest;
use App\Models\AcademicClass;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;

/**
 * Records a student's academic placements.
 *
 * The history lives on the student profile, so there is no index action:
 * every route here is reached from that page.
 */
class StudentAcademicEnrollmentController extends Controller
{
    /**
     * Store a newly created enrollment.
     */
    public function store(StoreStudentEnrollmentRequest $request, string $student)
    {
        $studentRecord = Student::findOrFail($student);

        // Through the model rather than straight onto the relation: the
        // "one enrollment per session" rule the school track carries is
        // re-checked there under a row lock, inside the transaction that
        // writes the row. The form request checked it too, but outside any
        // transaction, so two submissions at once could both pass it.
        $studentRecord->addEnrollment($request->validated());

        return redirect()
            ->route('students.show', $studentRecord->id)
            ->with('success', 'Academic enrollment added successfully.');
    }

    /**
     * Show the form for editing an enrollment.
     */
    public function edit(string $student, string $enrollment)
    {
        $studentRecord = Student::findOrFail($student);

        return view('students.enrollments.edit', [
            'student' => $studentRecord,
            'enrollment' => $this->enrollmentFor($studentRecord, $enrollment),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the specified enrollment.
     */
    public function update(UpdateStudentEnrollmentRequest $request, string $student, string $enrollment)
    {
        $studentRecord = Student::findOrFail($student);

        $this->enrollmentFor($studentRecord, $enrollment)->update($request->validated());

        return redirect()
            ->route('students.show', $studentRecord->id)
            ->with('success', 'Academic enrollment updated successfully.');
    }

    /**
     * Remove the specified enrollment.
     *
     * Only the enrollment row goes: the student and all master data are
     * left alone. History is worth keeping, so the profile asks for
     * confirmation before this is reached.
     */
    public function destroy(string $student, string $enrollment)
    {
        $studentRecord = Student::findOrFail($student);

        $this->enrollmentFor($studentRecord, $enrollment)->delete();

        return redirect()
            ->route('students.show', $studentRecord->id)
            ->with('success', 'Academic enrollment deleted successfully.');
    }

    /**
     * Get one of this student's enrollments.
     *
     * Scoped to the student so an enrollment id belonging to somebody else
     * is a 404 rather than another student's record.
     */
    private function enrollmentFor(Student $student, string $enrollmentId): StudentAcademicEnrollment
    {
        return $student->academicEnrollments()->findOrFail($enrollmentId);
    }

    /**
     * Get the options the enrollment form selects are built from.
     *
     * Only active master data is offered, matching what validation accepts.
     * The classes and sections are keyed by their parent so the dependent
     * selects can narrow them without a request.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
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
            'academicTracks' => StudentAcademicEnrollment::ACADEMIC_TRACKS,
            'enrollmentStatuses' => StudentAcademicEnrollment::STATUSES,
        ];
    }

    /**
     * Get the form options for another controller's view.
     *
     * The add form lives on the student profile, which StudentController
     * renders, so it needs the same options this controller builds.
     *
     * @return array<string, mixed>
     */
    public static function enrollmentFormOptions(): array
    {
        return (new self)->formOptions();
    }
}
