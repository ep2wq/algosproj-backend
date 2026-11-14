<?php

namespace App\Http\Middleware;

use App\Models\SecurityEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// Cloudflare Turnstile on login, register, forgot-password and guest bug reports (README "Captcha").
// Runs after the route's throttle (a failed captcha still counts) and before the controller, so no
// password is checked and no email is sent without a valid token. Off while TURNSTILE_ENABLED is false.
// Fails closed: if Cloudflare cannot answer, the request is refused. The token and the secret are
// never logged or stored.
class VerifyTurnstile
{
    private const URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private const FIELD = 'turnstile_token';

    // Cloudflare's own limit for a token.
    private const MAX_LENGTH = 2048;

    // `turnstile:guests` (bug reports) skips logged-in users. Plain `turnstile` checks everyone, so a
    // session of one's own does not open login or register to scripts.
    public function handle(Request $request, Closure $next, string $only = ''): Response
    {
        // The honeypot stays first: the controller answers a filled one with its decoy response and
        // does nothing real.
        $honeypot = $request->input(SecurityEvent::HONEYPOT_FIELD);
        $honeypotFilled = $honeypot !== null && $honeypot !== '' && $honeypot !== [];
        $exempt = $only === 'guests' && Auth::guard('web')->check();
        if (! config('services.turnstile.enabled') || $honeypotFilled || $exempt) {
            return $next($request);
        }

        $token = $request->input(self::FIELD);
        if (! is_string($token) || $token === '' || strlen($token) > self::MAX_LENGTH) {
            $this->fail($request, 'captcha_failed');
        }

        $result = $this->verify($token, (string) $request->ip());
        if ($result !== true) {
            $this->fail($request, $result === null ? 'captcha_unavailable' : 'captcha_failed');
        }

        return $next($request);
    }

    // true = valid, false = Cloudflare refused the token, null = no usable answer (timeout,
    // connection error, non-2xx, not JSON).
    private function verify(string $token, string $ip): ?bool
    {
        try {
            $response = Http::asForm()->connectTimeout(5)->timeout(5)->post(self::URL, [
                'secret' => (string) config('services.turnstile.secret'),
                'response' => $token,
                'remoteip' => $ip,
            ]);
        } catch (Throwable) {
            return null;
        }

        $success = $response->successful() ? $response->json('success') : null;

        return is_bool($success) ? $success : null;
    }

    // Same 422 shape as any validation error, with the same message whatever went wrong.
    private function fail(Request $request, string $type): never
    {
        SecurityEvent::record($type, $request);

        throw ValidationException::withMessages([self::FIELD => __('messages.captcha_failed')]);
    }
}
