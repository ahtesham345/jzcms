<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateComputerCourseRequest;
use App\Http\Requests\Admin\UpdateComputerCourseSemesterRequest;
use App\Models\ComputerCourse;
use App\Models\ComputerCourseSemester;

/**
 * The Computer department's course and the semesters it is taught in.
 *
 * Read and edit only. The course and its six semesters are created by the
 * ComputerCourseSeeder when the department is set up, because the structure
 * is the business's - three years, six stages - and not something an admin
 * should have to assemble by hand or could accidentally assemble wrongly.
 *
 * What the admin does control is what the structure actually contains: when
 * each semester runs and what is taught in it. Neither is seeded, because
 * neither can be known in advance.
 *
 * There is no destroy action. A semester a student is standing in is part of
 * their academic record, and the course has exactly the number of stages the
 * business says it has - removing one would leave both wrong.
 */
class ComputerCourseController extends Controller
{
    /**
     * Show the course and its semesters.
     */
    public function index()
    {
        $course = ComputerCourse::current();

        return view('computer-course.index', [
            'course' => $course,
            'semesters' => $course?->semesters()->get() ?? collect(),
        ]);
    }

    /**
     * Show the form for editing the course itself.
     */
    public function edit(ComputerCourse $computerCourse)
    {
        return view('computer-course.edit', ['course' => $computerCourse]);
    }

    /**
     * Update the course.
     */
    public function update(UpdateComputerCourseRequest $request, ComputerCourse $computerCourse)
    {
        $computerCourse->update($request->validated());

        return redirect()
            ->route('computer-course.index')
            ->with('success', 'Computer course updated successfully.');
    }

    /**
     * Show the form for editing one semester.
     */
    public function editSemester(ComputerCourseSemester $semester)
    {
        return view('computer-course.semester-edit', [
            'semester' => $semester->load('computerCourse'),
        ]);
    }

    /**
     * Update one semester's dates and curriculum.
     */
    public function updateSemester(
        UpdateComputerCourseSemesterRequest $request,
        ComputerCourseSemester $semester
    ) {
        $semester->update($request->validated());

        return redirect()
            ->route('computer-course.index')
            ->with('success', "{$semester->name} updated successfully.");
    }
}
