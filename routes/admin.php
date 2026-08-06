<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "admin" middleware group.
|
*/

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    // Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // User Management
    // Route::resource('users', AdminUserController::class);

    // Student Management
    // Route::resource('students', AdminStudentController::class);

    // Teacher Management
    // Route::resource('teachers', AdminTeacherController::class);

    // Parent Management
    // Route::resource('parents', AdminParentController::class);

    // Fee Management
    // Route::resource('fees', AdminFeeController::class);

    // Attendance Management
    // Route::resource('attendance', AdminAttendanceController::class);

    // Reports
    // Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');

    // Settings
    // Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');
    // Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
});
