<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

// Guests are throttled per IP; login is throttled per IP plus email (see AppServiceProvider).
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::get('/modules', [LessonController::class, 'modules']);
Route::get('/lessons/{slug}', [LessonController::class, 'show']);
Route::get('/leaderboard', [LeaderboardController::class, 'index']);
Route::get('/challenges', [ChallengeController::class, 'index']);
Route::get('/challenges/{slug}', [ChallengeController::class, 'show']);

// not-banned refuses a banned user's token (banning deletes their tokens; this catches any left).
Route::middleware(['auth:sanctum', 'not-banned'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/leaderboard/me', [LeaderboardController::class, 'me']);

    // throttle keys on the authenticated user, so the limit is per user.
    Route::post('/problems/{problem}/submit', [SubmissionController::class, 'submit'])
        ->middleware('throttle:10,1');
});

// Content editing. Guests get 401, users who are not admins 403 (the admin middleware).
Route::middleware(['auth:sanctum', 'not-banned', 'admin'])->prefix('admin')->group(function () {
    Route::get('/modules', [Admin\ModuleController::class, 'index']);
    Route::post('/modules', [Admin\ModuleController::class, 'store']);
    Route::put('/modules/{module}', [Admin\ModuleController::class, 'update']);
    Route::delete('/modules/{module}', [Admin\ModuleController::class, 'destroy']);

    Route::get('/modules/{module}/lessons', [Admin\LessonController::class, 'index']);
    Route::post('/lessons', [Admin\LessonController::class, 'store']);
    Route::get('/lessons/{lesson}', [Admin\LessonController::class, 'show']);
    Route::put('/lessons/{lesson}', [Admin\LessonController::class, 'update']);
    Route::delete('/lessons/{lesson}', [Admin\LessonController::class, 'destroy']);

    Route::get('/problems', [Admin\ProblemController::class, 'index']);
    Route::post('/problems', [Admin\ProblemController::class, 'store']);
    Route::get('/problems/{problem}', [Admin\ProblemController::class, 'show']);
    Route::put('/problems/{problem}', [Admin\ProblemController::class, 'update']);
    Route::delete('/problems/{problem}', [Admin\ProblemController::class, 'destroy']);

    // Scoped: {testCase} must belong to {problem}, or the route returns 404.
    Route::scopeBindings()->group(function () {
        Route::get('/problems/{problem}/test-cases', [Admin\TestCaseController::class, 'index']);
        Route::post('/problems/{problem}/test-cases', [Admin\TestCaseController::class, 'store']);
        Route::put('/problems/{problem}/test-cases/{testCase}', [Admin\TestCaseController::class, 'update']);
        Route::delete('/problems/{problem}/test-cases/{testCase}', [Admin\TestCaseController::class, 'destroy']);
    });
});
