<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SocialLoginController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

// Guests are throttled per IP; login is throttled per IP plus email (see AppServiceProvider).
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// The second step of a Google or GitHub sign-up (README "Social login"): the pending sign-in lives
// in the session, so only the frontend can use these. complete shares register's limit.
Route::get('/auth/pending', [SocialLoginController::class, 'pending']);
Route::post('/auth/complete', [SocialLoginController::class, 'complete'])->middleware('throttle:register');

// Email (README "Email"). The verification link is signed (checked in the controller, which
// redirects to the frontend) and works without a session. Forgot and reset are for guests.
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->whereUuid('id')->name('verification.verify');
Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:forgot-password');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:reset-password');

// Course content in Arabic or English, from the Accept-Language header (README "Content languages").
Route::get('/modules', [LessonController::class, 'modules']);
Route::get('/lessons/{slug}', [LessonController::class, 'show']);
Route::get('/challenges', [ChallengeController::class, 'index']);
Route::get('/challenges/{slug}', [ChallengeController::class, 'show']);
Route::get('/leaderboard', [LeaderboardController::class, 'index']);

// The session guard only: a bearer token never authenticates. not-banned refuses a banned user's
// session (banning deletes their sessions; this catches any left).
Route::middleware(['auth:web', 'not-banned'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/leaderboard/me', [LeaderboardController::class, 'me']);
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:verification-email');

    // Running code and earning points need a verified email (verified-email). throttle keys on the
    // authenticated user, so the limit is per user.
    Route::post('/problems/{problem}/submit', [SubmissionController::class, 'submit'])
        ->middleware(['verified-email', 'throttle:10,1']);
});

// The admin area. Guests get 401, users who are neither admin nor owner 403 (the admin middleware).
Route::middleware(['auth:web', 'not-banned', 'admin'])->prefix('admin')->group(function () {
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

    // Users. Admins may only ban, unban and log out normal users; the controller checks the target.
    Route::get('/users', [Admin\UserController::class, 'index']);
    Route::get('/users/{user}', [Admin\UserController::class, 'show']);
    Route::post('/users/{user}/ban', [Admin\UserController::class, 'ban']);
    Route::post('/users/{user}/unban', [Admin\UserController::class, 'unban']);
    Route::post('/users/{user}/logout-everywhere', [Admin\UserController::class, 'logoutEverywhere']);
    Route::get('/users/{user}/activity', [Admin\ActivityController::class, 'forUser']);

    // Owners only: other admins get 403 (the owner middleware, also checked before route binding).
    Route::middleware('owner')->group(function () {
        Route::put('/users/{user}/role', [Admin\UserController::class, 'updateRole']);
        Route::post('/users/{user}/points', [Admin\UserController::class, 'adjustPoints']);
        Route::post('/users/{user}/reset-progress', [Admin\UserController::class, 'resetProgress']);
        Route::delete('/users/{user}', [Admin\UserController::class, 'destroy']);
        Route::get('/audit-log', [Admin\AuditLogController::class, 'index']);
        Route::get('/activity', [Admin\ActivityController::class, 'index']);
    });
});
