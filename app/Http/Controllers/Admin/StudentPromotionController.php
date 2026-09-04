<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromoteStudentRequest;
use App\Models\Student;

/**
 * Moves a student on to the next class.
 *
 * Promotion is always explicit: the admin opens the form, sees exactly what
 * the placement will become, and submits it. Nothing here promotes anyone
 * automatically or in bulk.
 */
class StudentPromotionController extends Controller
{
    /**
     * Show the promotion form.
     */
    public function create(string $student)
    {
        $studentRecord = Student::with([
            'activeAcademicEnrollments.academicSession',
            'activeAcademicEnrollments.department',
            'activeAcademicEnrollments.academicClass',
            'activeAcademicEnrollments.section',
        ])->findOrFail($student);

        if ($guard = $this->ineligible($studentRecord)) {
            return $guard;
        }

        return view('students.promote', [
            'student' => $studentRecord,
            // The same options, built the same way, as the enrollment form.
            ...StudentAcademicEnrollmentController::enrollmentFormOptions(),
        ]);
    }

    /**
     * Carry out the promotion.
     */
    public function store(PromoteStudentRequest $request, string $student)
    {
        $studentRecord = Student::findOrFail($student);

        if ($guard = $this->ineligible($studentRecord)) {
            return $guard;
        }

        $promoted = $studentRecord->promote($request->validated());

        return redirect()
            ->route('students.show', $studentRecord->id)
            ->with('success', sprintf(
                'Student promoted successfully. %s track is now %s in %s.',
                $promoted->academic_track,
                $promoted->academicClass?->name ?? 'the target class',
                $promoted->academicSession?->name ?? 'the target session'
            ));
    }

    /**
     * Turn an ineligible student into a redirect, or null when eligible.
     *
     * Mirrors the admission approval guards: a record that has reached an
     * outcome is refused before anything is written.
     */
    private function ineligible(Student $student)
    {
        if ($student->canBePromoted()) {
            return null;
        }

        return redirect()
            ->route('students.show', $student->id)
            ->with('error', "Only students with an Active status can be promoted. This student is marked {$student->student_status}.");
    }
}
