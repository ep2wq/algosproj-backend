<?php

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

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/leaderboard/me', [LeaderboardController::class, 'me']);

    // throttle keys on the authenticated user, so the limit is per user.
    Route::post('/problems/{problem}/submit', [SubmissionController::class, 'submit'])
        ->middleware('throttle:10,1');
});
