<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Parent Routes
|--------------------------------------------------------------------------
|
| Here is where you can register parent routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "parent" middleware group.
|
*/

Route::middleware(['auth', 'role:parent'])->prefix('parent')->name('parent.')->group(function () {
    // Dashboard
    // Route::get('/dashboard', [ParentDashboardController::class, 'index'])->name('dashboard');

    // My Children
    // Route::get('children', [ParentChildrenController::class, 'index'])->name('children.index');
    // Route::get('children/{student}', [ParentChildrenController::class, 'show'])->name('children.show');

    // Attendance
    // Route::get('attendance/{student}', [ParentAttendanceController::class, 'show'])->name('attendance.show');

    // Academic Records
    // Route::get('academics/{student}', [ParentAcademicsController::class, 'show'])->name('academics.show');

    // Fee Management
    // Route::get('fees', [ParentFeeController::class, 'index'])->name('fees.index');
    // Route::get('fees/{student}', [ParentFeeController::class, 'show'])->name('fees.show');
    // Route::post('fees/payment', [ParentFeeController::class, 'payment'])->name('fees.payment');

    // Notifications
    // Route::get('notifications', [ParentNotificationController::class, 'index'])->name('notifications.index');

    // Profile
    // Route::get('profile', [ParentProfileController::class, 'edit'])->name('profile.edit');
    // Route::put('profile', [ParentProfileController::class, 'update'])->name('profile.update');
});
