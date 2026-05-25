<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\IncomingEmailController;
use App\Http\Controllers\Web\TaskDraftController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::post('/emails', [IncomingEmailController::class, 'store'])->name('emails.store');
Route::get('/task-drafts/{taskDraft}', [TaskDraftController::class, 'show'])->name('task-drafts.show');
Route::post('/task-drafts/{taskDraft}/approve', [TaskDraftController::class, 'approve'])->name('task-drafts.approve');
Route::post('/task-drafts/{taskDraft}/reject', [TaskDraftController::class, 'reject'])->name('task-drafts.reject');
Route::post('/task-drafts/{taskDraft}/override', [TaskDraftController::class, 'override'])->name('task-drafts.override');
