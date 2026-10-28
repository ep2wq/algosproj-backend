<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Throwable;

use function Illuminate\Support\defer;

// Forgot and reset password with Laravel's password broker (README "Email"): tokens are stored
// hashed in password_reset_tokens, expire after 60 minutes and work once.
class PasswordResetController extends Controller
{
    // Always the same 202 and body, whether or not the account exists. The lookup, the token and
    // the email all happen after the response, so the response time says nothing either.
    public function forgot(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);
        $locale = app()->getLocale();

        defer(function () use ($data, $locale) {
            try {
                Password::sendResetLink(['email' => $data['email']], function (User $user, string $token) use ($locale) {
                    $user->notify((new ResetPassword($token))->locale($locale));
                });
            } catch (Throwable $e) {
                User::logMailFailure('ResetPassword', $e);
            }
        });

        return response()->json(['message' => __('messages.reset_link_sent')], 202);
    }

    // Sets the new password and ends every session of the account (README "Email"). The user is
    // not logged in. A banned user can reset the password and stays banned.
    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            // The register rules, plus the confirmation field.
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($data, function (User $user, string $password) use ($request) {
            $user->password = $password;
            $user->endSessions();

            // Resetting from a browser logged in to this account: end this session too, or it would
            // be written back at the end of the request.
            if ($request->hasSession() && Auth::guard('web')->id() === $user->id) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        });

        if ($status !== Password::PASSWORD_RESET) {
            // Wrong, expired or used token, or an unknown email: one message for all of them.
            return response()->json(['message' => __('messages.reset_invalid')], 422);
        }

        return response()->json(['message' => __('messages.password_reset')]);
    }
}
