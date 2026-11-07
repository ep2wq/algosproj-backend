<?php

namespace App\Http\Controllers;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            // Accepting the Terms of use and Privacy policy: true, 1 or "1" only ("yes" and "on" fail boolean).
            'accepted_terms' => ['bail', 'required', 'accepted', 'boolean'],
        ]);

        // The hidden field was filled (a bot): the same 422 as an email that is already taken, and
        // no account (README "Security events and IP bans").
        if (SecurityEvent::honeypotFilled($request)) {
            $validator = Validator::make([], []);
            $validator->errors()->add('email', __('validation.unique', ['attribute' => $validator->getDisplayableAttribute('email')]));

            throw new ValidationException($validator);
        }

        // The acceptance is recorded with the server's time and the current legal version
        // (config/legal.php); nothing from the request is stored but the three fields.
        $user = new User(Arr::only($data, ['name', 'email', 'password']));
        $user->forceFill(['terms_accepted_at' => now(), 'terms_version' => config('legal.terms_version')])->save();
        $user->refresh();
        UserActivityLog::record($user, $user->email, 'register', $request);
        $this->startSession($request, $user, false);
        // New accounts start unverified; the email goes out after the response, and a mail failure
        // never fails the registration (User::notifyAfterResponse).
        $user->sendEmailVerificationNotification();

        return response()->json(['user' => $this->userJson($user)], 201);
    }

    public function login(Request $request)
    {
        $this->requireSession($request);
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        // The hidden field was filled (a bot): the usual wrong-credentials answer, nothing checked.
        if (SecurityEvent::honeypotFilled($request)) {
            return response()->json(['message' => __('messages.invalid_credentials')], 401);
        }

        $user = User::where('email', $data['email'])->first();

        // An unknown email (or an account without a password) is checked against a dummy hash of the
        // same cost, so the answer takes as long as a wrong password and does not show the email exists.
        // Made once per cost and cached: making it on every request would itself take a hash's time.
        $hash = $user?->password ?? Cache::rememberForever(
            'login-dummy-hash:'.config('hashing.bcrypt.rounds'),
            fn () => Hash::make(Str::random(40)),
        );
        $passwordOk = Hash::check($data['password'], $hash);

        if (! $user || ! $passwordOk) {
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

        return response()->json(['user' => $this->userJson($user)]);
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
        return response()->json($this->userJson($request->user()));
    }

    // The user's own JSON plus email_verified, a boolean only (email_verified_at stays hidden).
    private function userJson(User $user): array
    {
        return $user->toArray() + ['email_verified' => $user->hasVerifiedEmail()];
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
