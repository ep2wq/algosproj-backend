<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    // Submitting code (and so earning points) needs a verified email. Same shape as the ban 403,
    // with a fixed reason the frontend checks.
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasVerifiedEmail()) {
            return response()->json(['message' => __('messages.email_unverified'), 'reason' => 'email_unverified'], 403);
        }

        return $next($request);
    }
}
