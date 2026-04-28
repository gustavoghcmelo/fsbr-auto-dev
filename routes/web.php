<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReopenRequestController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\RequirementDocumentController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\TestCaseController;
use App\Http\Controllers\TestPlanController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');

    Route::resource('projects', ProjectController::class)
        ->except(['destroy']);

    Route::post('projects/{project}/requirement-documents', [RequirementDocumentController::class, 'store'])
        ->name('projects.requirement-documents.store');

    Route::get('projects/{project}/requirement-documents/{document}/download', [RequirementDocumentController::class, 'download'])
        ->name('projects.requirement-documents.download');

    Route::post('projects/{project}/requirement-documents/{document}/retry', [RequirementDocumentController::class, 'retry'])
        ->name('projects.requirement-documents.retry');

    Route::put('projects/{project}/requirements/{requirement}', [RequirementController::class, 'update'])
        ->name('projects.requirements.update');

    Route::delete('projects/{project}/requirements/{requirement}', [RequirementController::class, 'destroy'])
        ->name('projects.requirements.destroy');

    Route::post('projects/{project}/requirements/{requirement}/qa-approve', [RequirementController::class, 'qaApprove'])
        ->name('projects.requirements.qa-approve');
    Route::delete('projects/{project}/requirements/{requirement}/qa-approve', [RequirementController::class, 'qaRevoke'])
        ->name('projects.requirements.qa-revoke');

    // Planos de teste
    Route::post('projects/{project}/test-plans', [TestPlanController::class, 'store'])
        ->name('projects.test-plans.store');
    Route::get('projects/{project}/test-plans/{plan}', [TestPlanController::class, 'show'])
        ->name('projects.test-plans.show');
    Route::put('projects/{project}/test-plans/{plan}', [TestPlanController::class, 'update'])
        ->name('projects.test-plans.update');
    Route::delete('projects/{project}/test-plans/{plan}', [TestPlanController::class, 'destroy'])
        ->name('projects.test-plans.destroy');
    Route::post('projects/{project}/test-plans/{plan}/generate-cases', [TestPlanController::class, 'generateCases'])
        ->name('projects.test-plans.generate-cases');

    // Casos de teste (manuais e edição dos gerados)
    Route::post('projects/{project}/test-plans/{plan}/test-cases', [TestCaseController::class, 'store'])
        ->name('projects.test-plans.test-cases.store');
    Route::put('projects/{project}/test-plans/{plan}/test-cases/{case}', [TestCaseController::class, 'update'])
        ->name('projects.test-plans.test-cases.update');
    Route::delete('projects/{project}/test-plans/{plan}/test-cases/{case}', [TestCaseController::class, 'destroy'])
        ->name('projects.test-plans.test-cases.destroy');

    // Pedidos de reabertura (esteira retroativa)
    Route::post('projects/{project}/requirements/{requirement}/reopen-requests', [ReopenRequestController::class, 'store'])
        ->name('projects.requirements.reopen-requests.store');
    Route::get('projects/{project}/reopen-requests/{reopenRequest}', [ReopenRequestController::class, 'show'])
        ->name('projects.reopen-requests.show');
    Route::post('projects/{project}/reopen-requests/{reopenRequest}/decide', [ReopenRequestController::class, 'decide'])
        ->name('projects.reopen-requests.decide');

    // Sprints (organização do backlog pelo PM)
    Route::post('projects/{project}/sprints', [SprintController::class, 'store'])
        ->name('projects.sprints.store');
    Route::put('projects/{project}/sprints/{sprint}', [SprintController::class, 'update'])
        ->name('projects.sprints.update');
    Route::delete('projects/{project}/sprints/{sprint}', [SprintController::class, 'destroy'])
        ->name('projects.sprints.destroy');
});

require __DIR__.'/auth.php';
