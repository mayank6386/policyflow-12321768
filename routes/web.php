<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\PolicyRequestDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/policy-requests', [PolicyRequestDashboardController::class, 'index'])->name('policy-requests.index');
    Route::get('/policy-requests/export', [PolicyRequestDashboardController::class, 'export'])->name('policy-requests.export');
    Route::get('/policy-requests/create', [PolicyRequestDashboardController::class, 'create'])->name('policy-requests.create');
    Route::post('/policy-requests', [PolicyRequestDashboardController::class, 'store'])->name('policy-requests.store');
    Route::post('/policy-requests/{policyRequest}/submit', [PolicyRequestDashboardController::class, 'submit'])->name('policy-requests.submit');
    Route::post('/policy-requests/{policyRequest}/reconcile', [PolicyRequestDashboardController::class, 'reconcile'])->name('policy-requests.reconcile');
    Route::get('/policy-requests/{policyRequest}', [PolicyRequestDashboardController::class, 'show'])->name('policy-requests.show');
});
