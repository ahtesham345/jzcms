<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Accounts Routes
|--------------------------------------------------------------------------
|
| Here is where you can register accounts/finance routes for your application.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "accounts" middleware group.
|
*/

Route::middleware(['auth', 'role:accountant'])->prefix('accounts')->name('accounts.')->group(function () {
    // Dashboard
    // Route::get('/dashboard', [AccountsDashboardController::class, 'index'])->name('dashboard');

    // Fee Collection
    // Route::get('fee-collection', [FeeCollectionController::class, 'index'])->name('fee-collection.index');
    // Route::post('fee-collection', [FeeCollectionController::class, 'store'])->name('fee-collection.store');
    // Route::get('fee-collection/{receipt}', [FeeCollectionController::class, 'show'])->name('fee-collection.show');

    // Fee Structure
    // Route::resource('fee-structure', FeeStructureController::class);

    // Student Ledger
    // Route::get('ledger/student/{student}', [StudentLedgerController::class, 'show'])->name('ledger.student');

    // Payments History
    // Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    // Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

    // Pending Fees
    // Route::get('pending-fees', [PendingFeeController::class, 'index'])->name('pending-fees.index');

    // Expense Management
    // Route::resource('expenses', ExpenseController::class);

    // Income Management
    // Route::resource('income', IncomeController::class);

    // Financial Reports
    // Route::get('reports/daily', [AccountsReportController::class, 'daily'])->name('reports.daily');
    // Route::get('reports/monthly', [AccountsReportController::class, 'monthly'])->name('reports.monthly');
    // Route::get('reports/yearly', [AccountsReportController::class, 'yearly'])->name('reports.yearly');
    // Route::get('reports/fee-defaulters', [AccountsReportController::class, 'feeDefaulters'])->name('reports.fee-defaulters');
});
