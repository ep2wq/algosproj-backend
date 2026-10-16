<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOwner
{
    // Guests get 401, everyone but an owner (admins included) gets 403.
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(! $user, 401, 'Unauthenticated.');
        abort_if($user->role !== 'owner', 403, 'Only an owner can do this.');

        return $next($request);
    }
}
