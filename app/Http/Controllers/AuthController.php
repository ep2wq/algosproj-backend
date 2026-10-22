<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

// Cookie sessions (README "Sessions"): login and register start a session, logout ends it.
// No token is ever issued or returned.
class AuthController extends Controller
{
    public function register(Request $request)
    {
        $this->requireSession($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create($data)->refresh();
        UserActivityLog::record($user, $user->email, 'register', $request);
        $this->startSession($request, $user, false);

        return response()->json(['user' => $user], 201);
    }

    public function login(Request $request)
    {
        $this->requireSession($request);
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            // Only the attempted email is kept, never the password.
            UserActivityLog::record($user, $user->email ?? $data['email'], 'login_failed', $request);

            return response()->json(['message' => __('messages.invalid_credentials')], 401);
        }

        // Checked after the password, so the ban (and its reason) is only shown to the account holder.
        if ($user->banned_at !== null) {
            UserActivityLog::record($user, $user->email, 'login_blocked_banned', $request);

            return response()->json(['message' => __('messages.banned'), 'reason' => $user->ban_reason], 403);
        }

        UserActivityLog::record($user, $user->email, 'login', $request);
        $this->startSession($request, $user, $request->boolean('remember'));

        return response()->json(['user' => $user]);
    }

    public function logout(Request $request)
    {
        $this->requireSession($request);
        UserActivityLog::record($request->user(), $request->user()->email, 'logout', $request);

        // Also gives the user a new remember token, so a "remember me" cookie on any device stops working.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => __('messages.logged_out')]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // Only requests from the frontend (an origin in sanctum.stateful) get a session. Any other
    // client gets the same 419 as a missing CSRF token, never a login without a cookie.
    private function requireSession(Request $request): void
    {
        if (! $request->hasSession()) {
            throw new TokenMismatchException('CSRF token mismatch.');
        }
    }

    private function startSession(Request $request, User $user, bool $remember): void
    {
        Auth::guard('web')->login($user, $remember);
        // A new session id once logged in, so an id set before login is useless (session fixation).
        $request->session()->regenerate();
    }
}
