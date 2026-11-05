<?php

namespace App\Http\Middleware;

use App\Models\BannedIp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// Global, after TrustProxies and before any route middleware (so before the session starts): a
// banned IP gets a plain 403 with no detail. If the ban list cannot be read, the request goes on.
class BlockBannedIps
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = BannedIp::normalize((string) $request->ip());

        try {
            $banned = $ip !== '' && BannedIp::isBanned($ip);
            if ($banned) {
                BannedIp::noteBlocked($ip);
            }
        } catch (Throwable $e) {
            Log::warning('Could not check the IP ban list', ['error' => $e::class]);
            $banned = false;
        }

        if ($banned) {
            return response()->json(['message' => __('messages.forbidden')], 403);
        }

        return $next($request);
    }
}
