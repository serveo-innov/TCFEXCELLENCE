<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\ExerciseController;

// ── AUTH ──────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login',    [AuthController::class, 'login'])->name('auth.login');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me',      [AuthController::class, 'me'])->name('auth.me');
    });
});

// ── APPRENANT ─────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:SOLO|COACHED'])
    ->prefix('learner')
    ->group(function () {
        Route::get('/{id}/dashboard',     [LearnerController::class, 'dashboard']);
        Route::get('/{id}/progress',      [LearnerController::class, 'progress']);
        Route::patch('/{id}/exam-date',   [LearnerController::class, 'updateExamDate']);
        Route::patch('/{id}/hide-banner', [LearnerController::class, 'hideBanner']);
    });

// ── SOUMISSIONS ───────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:SOLO|COACHED'])
    ->group(function () {
        Route::post('/submissions',                    [SubmissionController::class, 'store']);
        Route::get('/submissions/{id}/correction',     [SubmissionController::class, 'correction']);
    });

// ── EXERCICES (apprenants + admin) ────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:SOLO|COACHED|ADMIN'])
    ->group(function () {
        Route::get('/exercises',      [ExerciseController::class, 'index']);
        Route::get('/exercises/{id}', [ExerciseController::class, 'show']);
    });

// ── CHAT ──────────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:SOLO|COACHED|ADMIN'])
    ->prefix('groups')
    ->group(function () {
        Route::get('/',                                       [GroupController::class, 'index']);
        Route::get('/{id}/messages',                         [GroupController::class, 'messages']);
        Route::post('/{id}/messages',                        [GroupController::class, 'sendMessage']);
        Route::patch('/{groupId}/messages/{messageId}/pin',  [GroupController::class, 'pinMessage']);
        Route::patch('/{groupId}/messages/{messageId}/hide', [GroupController::class, 'hideMessage']);
        Route::post('/{groupId}/messages/{messageId}/react', [GroupController::class, 'reactToMessage']);
    });

// ── ADMIN ─────────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:ADMIN'])
    ->prefix('admin')
    ->group(function () {

        // Apprenants
        Route::get('/learners/export',              [AdminController::class, 'exportCsv']);
        Route::get('/learners/kpis',                [AdminController::class, 'kpis']);
        Route::get('/stats',                        [AdminController::class, 'stats']);
        Route::get('/learners',                     [AdminController::class, 'learners']);
        Route::get('/learners/{id}',                [AdminController::class, 'learnerProfile']);
        Route::post('/learners/{id}/message',       [AdminController::class, 'sendCoachMessage']);
        Route::patch('/learner/{id}/coach-message', [AdminController::class, 'updateCoachMessage']);
        Route::patch('/learner/{id}/banner',        [AdminController::class, 'toggleBanner']);
        Route::post('/learner/{id}/note-privee',    [AdminController::class, 'addPrivateNote']);
        
        Route::get('/competences', function () {
            return response()->json(App\Models\Competence::all(['id', 'code', 'name']));
        });

        // Corrections IA
        Route::get('/submissions/pending',         [SubmissionController::class, 'pendingCorrections']);
        Route::patch('/submissions/{id}/validate', [SubmissionController::class, 'validateCorrection']);
        Route::post('/submissions/{id}/trigger-ai', [SubmissionController::class, 'triggerAiCorrection']);

        // Exercices — CRUD complet
        Route::post('/exercises',                                    [ExerciseController::class, 'store']);
        Route::put('/exercises/{id}',                                [ExerciseController::class, 'update']);
        Route::delete('/exercises/{id}',                             [ExerciseController::class, 'destroy']);
        Route::patch('/exercises/{id}/toggle',                       [ExerciseController::class, 'toggleActive']);

        // Questions QCM
        Route::post('/exercises/{id}/questions',                     [ExerciseController::class, 'storeQuestion']);
        Route::put('/exercises/{id}/questions/{questionId}',         [ExerciseController::class, 'updateQuestion']);
        Route::delete('/exercises/{id}/questions/{questionId}',      [ExerciseController::class, 'destroyQuestion']);

        // Groupes
        Route::post('/groups',                            [GroupController::class, 'store']);
        Route::patch('/groups/{id}',                      [GroupController::class, 'update']);
        Route::delete('/groups/{id}',                     [GroupController::class, 'destroy']);
        Route::post('/groups/{id}/members',               [GroupController::class, 'addMember']);
        Route::delete('/groups/{id}/members/{learnerId}', [GroupController::class, 'removeMember']);
    });
