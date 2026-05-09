<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\AdminController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/learner/{id}/dashboard',  [LearnerController::class, 'dashboard']);
    Route::get('/learner/{id}/progress',   [LearnerController::class, 'progress']);
    Route::patch('/learner/{id}/exam-date',[LearnerController::class, 'updateExamDate']);
});


Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    Route::get('/learners',                [AdminController::class, 'learners']);
    Route::get('/learners/export',         [AdminController::class, 'exportCsv']);
    Route::get('/learners/{id}',           [AdminController::class, 'learnerProfile']);
    Route::post('/learners/{id}/message',  [AdminController::class, 'sendCoachMessage']);
    Route::get('/stats',                   [AdminController::class, 'stats']);
});