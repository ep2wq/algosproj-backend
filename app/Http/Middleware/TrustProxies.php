<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

// Replaces Laravel's TrustProxies (bootstrap/app.php). The proxies come from TRUSTED_PROXIES
// (config/security.php), read on each request. Empty means none: X-Forwarded-For is ignored and
// the request IP is the connecting address.
class TrustProxies extends Middleware
{
    protected function proxies()
    {
        $value = trim((string) config('security.trusted_proxies'));

        if ($value === '') {
            return null;
        }

        return $value === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
