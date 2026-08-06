<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Teacher Routes
|--------------------------------------------------------------------------
|
| Here is where you can register teacher routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "teacher" middleware group.
|
*/

Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    // Dashboard
    // Route::get('/dashboard', [TeacherDashboardController::class, 'index'])->name('dashboard');

    // My Classes
    // Route::get('classes', [TeacherClassController::class, 'index'])->name('classes.index');
    // Route::get('classes/{class}', [TeacherClassController::class, 'show'])->name('classes.show');

    // Attendance Management
    // Route::get('attendance', [TeacherAttendanceController::class, 'index'])->name('attendance.index');
    // Route::post('attendance', [TeacherAttendanceController::class, 'store'])->name('attendance.store');

    // Student Marks
    // Route::get('marks', [TeacherMarksController::class, 'index'])->name('marks.index');
    // Route::post('marks', [TeacherMarksController::class, 'store'])->name('marks.store');

    // Assignments
    // Route::resource('assignments', TeacherAssignmentController::class);

    // My Students
    // Route::get('students', [TeacherStudentController::class, 'index'])->name('students.index');
    // Route::get('students/{student}', [TeacherStudentController::class, 'show'])->name('students.show');

    // Profile
    // Route::get('profile', [TeacherProfileController::class, 'edit'])->name('profile.edit');
    // Route::put('profile', [TeacherProfileController::class, 'update'])->name('profile.update');
});
