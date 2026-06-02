<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\AdminController;

// Auth — routes publiques
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login',    [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me',      [AuthController::class, 'me'])->name('auth.me');
    });
});

// Apprenant — routes protégées
Route::middleware(['auth:sanctum', 'role:SOLO|COACHED'])
    ->prefix('learner')
    ->group(function () {
        Route::get('/{id}/dashboard',  [LearnerController::class, 'dashboard']);
        Route::get('/{id}/progress',   [LearnerController::class, 'progress']);
        Route::patch('/{id}/exam-date', [LearnerController::class, 'updateExamDate']);
    });

// Admin — routes protégées — export AVANT {id}
Route::middleware(['auth:sanctum', 'role:ADMIN'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/learners/export',        [AdminController::class, 'exportCsv']);   // ← avant {id}
        Route::get('/learners',               [AdminController::class, 'learners']);
        Route::get('/learners/{id}',          [AdminController::class, 'learnerProfile']);
        Route::post('/learners/{id}/message', [AdminController::class, 'sendCoachMessage']);
        Route::get('/stats',                  [AdminController::class, 'stats']);
    });