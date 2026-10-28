<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

// Email verification (README "Email"). An unverified user can read everything but cannot submit
// code (the verified-email middleware).
class EmailVerificationController extends Controller
{
    // The signed link from the email. It works without a session (it may open in another browser)
    // and always ends on the frontend with status=success or status=invalid, nothing more.
    public function verify(Request $request, string $id, string $hash)
    {
        $user = $request->hasValidSignature() ? User::find($id) : null;

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->toFrontend('invalid');
        }

        // Opening the link again is fine: an already verified email stays as it is.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $this->toFrontend('success');
    }

    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('messages.already_verified')]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => __('messages.verification_sent')], 202);
    }

    private function toFrontend(string $status)
    {
        return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/verify-email?status='.$status);
    }
}
