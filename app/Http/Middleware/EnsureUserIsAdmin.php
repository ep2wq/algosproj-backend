<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    // Guests get 401, logged-in users who are not admins get 403.
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(! $user, 401, 'Unauthenticated.');
        abort_if($user->role !== 'admin', 403, 'Forbidden.');

        return $next($request);
    }
}
