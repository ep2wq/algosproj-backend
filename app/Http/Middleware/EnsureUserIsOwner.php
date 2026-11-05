<?php

namespace App\Http\Middleware;

use App\Models\SecurityEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOwner
{
    // Guests get 401, everyone but an owner (admins included) gets 403.
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(! $user, 401, __('messages.unauthenticated'));
        if ($user->role !== 'owner') {
            // A logged-in user on a route above their role (README "Security events and IP bans").
            SecurityEvent::record('forbidden_admin_route', $request, ['required' => 'owner']);
            abort(403, __('admin.owner_only'));
        }

        return $next($request);
    }
}
