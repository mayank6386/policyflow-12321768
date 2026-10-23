<?php

use App\Http\Controllers\Api\MockInsurerController;
use App\Http\Controllers\Api\PolicyRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::post('/policy-requests', [PolicyRequestController::class, 'store'])->name('api.policy-requests.store');
Route::get('/policy-requests', [PolicyRequestController::class, 'index'])->name('api.policy-requests.index');
Route::get('/policy-requests/{policyRequest}', [PolicyRequestController::class, 'show'])->name('api.policy-requests.show');
Route::post('/policy-requests/{policyRequest}/submit', [PolicyRequestController::class, 'submit'])->name('api.policy-requests.submit');

Route::post('/mock-insurer/callback', [MockInsurerController::class, 'callback'])->name('api.mock-insurer.callback');
Route::post('/mock-insurer/scenario', [MockInsurerController::class, 'setScenario'])->name('api.mock-insurer.scenario');
Route::get('/mock-insurer/scenario', [MockInsurerController::class, 'getScenario'])->name('api.mock-insurer.scenario.get');
Route::post('/mock-insurer/reconcile', [MockInsurerController::class, 'reconcile'])->name('api.mock-insurer.reconcile');
