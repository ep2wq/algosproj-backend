<?php

namespace App\Http\Middleware;

use App\Models\SecurityEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    // Guests get 401, logged-in users who are neither admin nor owner get 403.
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(! $user, 401, __('messages.unauthenticated'));
        if (! in_array($user->role, ['admin', 'owner'], true)) {
            // A logged-in user on a route above their role (README "Security events and IP bans").
            SecurityEvent::record('forbidden_admin_route', $request, ['required' => 'admin']);
            abort(403, __('admin.forbidden'));
        }

        return $next($request);
    }
}
