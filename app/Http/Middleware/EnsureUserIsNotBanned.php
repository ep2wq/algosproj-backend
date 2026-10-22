<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBanned
{
    // Banning deletes the user's sessions; this also refuses any session that survived.
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->banned_at !== null) {
            return response()->json(['message' => __('messages.banned'), 'reason' => $user->ban_reason], 403);
        }

        return $next($request);
    }
}
