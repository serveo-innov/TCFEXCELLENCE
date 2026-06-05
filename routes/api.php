<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\AdminController;

// AUTH
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login',    [AuthController::class, 'login'])->name('auth.login');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me',      [AuthController::class, 'me'])->name('auth.me');
    });
});

// APPRENANT
Route::middleware(['auth:sanctum', 'role:SOLO|COACHED'])
    ->prefix('learner')
    ->group(function () {
        Route::get('/{id}/dashboard',     [LearnerController::class, 'dashboard']);
        Route::get('/{id}/progress',      [LearnerController::class, 'progress']);
        Route::patch('/{id}/exam-date',   [LearnerController::class, 'updateExamDate']);
        Route::patch('/{id}/hide-banner', [LearnerController::class, 'hideBanner']);
    });

// ADMIN — statiques avant {id}
Route::middleware(['auth:sanctum', 'role:ADMIN'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/learners/export',             [AdminController::class, 'exportCsv']);
        Route::get('/learners/kpis',               [AdminController::class, 'kpis']);
        Route::get('/stats',                       [AdminController::class, 'stats']);
        Route::get('/learners',                    [AdminController::class, 'learners']);
        Route::get('/learners/{id}',               [AdminController::class, 'learnerProfile']);
        Route::post('/learners/{id}/message',      [AdminController::class, 'sendCoachMessage']);
        Route::patch('/learner/{id}/coach-message',[AdminController::class, 'updateCoachMessage']);
        Route::patch('/learner/{id}/banner',       [AdminController::class, 'toggleBanner']);
        Route::post('/learner/{id}/note-privee',   [AdminController::class, 'addPrivateNote']);
    });
