<?php

use App\Http\Controllers\SocialLoginController;
use Illuminate\Support\Facades\Route;

// No web pages: the app is the JSON API in routes/api.php, plus the /up health route
// (bootstrap/app.php). GET / answers 404.

// Social login (README "Social login"): full page visits, not API calls. They are here, in the web
// group, because it always starts the session: the callback comes from Google or GitHub, so
// Sanctum's Origin/Referer check would give it no session, and the OAuth state lives there.
Route::middleware('throttle:social')->whereIn('provider', SocialLoginController::PROVIDERS)->group(function () {
    Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect']);
    Route::get('/auth/{provider}/callback', [SocialLoginController::class, 'callback']);
});
