<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Every API request answers in Arabic or English: course text (HasArabicText reads the app
// locale) and messages (lang/). Global middleware (bootstrap/app.php). The response
// varies by Accept-Language, so a cache keeps the two apart.
class SetContentLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(self::wantsArabic($request->header('Accept-Language')) ? 'ar' : 'en');

        $response = $next($request);
        $response->setVary('Accept-Language', false);

        return $response;
    }

    // Arabic only when the first language listed is "ar" or "ar-XX" ("ar-SA",
    // "ar;q=0.9,en;q=0.8"). Anything else, missing or malformed means English.
    public static function wantsArabic(?string $header): bool
    {
        $first = strtolower(trim(explode(';', explode(',', (string) $header)[0])[0]));

        return preg_match('/^ar(-[a-z0-9]{1,8})?$/', $first) === 1;
    }
}
