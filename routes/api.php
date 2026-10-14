<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/modules', [LessonController::class, 'modules']);
Route::get('/lessons/{slug}', [LessonController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // throttle keys on the authenticated user, so the limit is per user.
    Route::post('/problems/{problem}/submit', [SubmissionController::class, 'submit'])
        ->middleware('throttle:10,1');
});
