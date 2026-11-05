<?php

namespace App\Http\Middleware;

use App\Models\SecurityEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

// Global (bootstrap/app.php). Looks at the finished response and records security events
// (README "Security events and IP bans"). It never changes the response. The 403 of the admin and
// owner middleware is recorded there; honeypots in the auth controllers.
class RecordSecurityEvents
{
    // Paths real users never visit. They get the same 404 as any unknown path.
    private const DECOYS = ['.env', '.git/config', 'wp-login.php', 'wp-admin', 'xmlrpc.php', 'phpmyadmin', 'admin.php'];

    // Conservative markers; this only records, it never blocks.
    private const PATTERNS = [
        'sql_injection' => '/\bunion\b[\s(]+(all\s+)?select\b|\'\s*(or|and)\s+\'?\w+\'?\s*=\s*\'?\w+|\b(sleep|benchmark)\s*\(|\binformation_schema\b|;\s*(drop|truncate|delete|insert|update)\s/i',
        'script_tag' => '/<\s*\/?\s*script\b|<[^>]+\son[a-z]+\s*=|javascript\s*:/i',
        'path_traversal' => '#\.\.[/\\\\]#',
        'null_byte' => '/\x00/',
    ];

    // Auth routes whose name and email fields are checked (never the password).
    private const AUTH_PATHS = ['api/register', 'api/login', 'api/forgot-password', 'api/reset-password', 'api/auth/complete'];

    // Fields never read or stored, whatever the route.
    private const SECRET_FIELD = '/pass|token|secret|key|auth|cookie|session|code/i';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $status = $response->getStatusCode();

        if ($status === 401 && $request->is('api/admin', 'api/admin/*')) {
            SecurityEvent::record('unauthenticated_admin_route', $request);
        } elseif ($status === 419) {
            SecurityEvent::record('csrf_mismatch', $request);
        } elseif ($status === 429) {
            SecurityEvent::record('rate_limited', $request);
        } elseif ($status === 404 && $this->isDecoy($request)) {
            SecurityEvent::record('decoy_url', $request);
        }

        $this->checkInput($request);

        return $response;
    }

    private function isDecoy(Request $request): bool
    {
        $path = strtolower(trim($request->path(), '/'));

        foreach (self::DECOYS as $decoy) {
            if ($path === $decoy || str_starts_with($path, $decoy.'/')) {
                return true;
            }
        }

        return false;
    }

    // Only the query string of GET API routes (not the admin content editors) and the name and
    // email of the auth routes. Never a code submission or any other request body.
    private function checkInput(Request $request): void
    {
        if ($request->isMethod('GET') && $request->is('api/*') && ! $request->is('api/admin/modules*', 'api/admin/lessons*', 'api/admin/problems*')) {
            $values = Arr::dot($request->query());
        } elseif ($request->isMethod('POST') && $request->is(...self::AUTH_PATHS)) {
            $values = array_filter(['name' => $request->input('name'), 'email' => $request->input('email')], 'is_string');
        } else {
            return;
        }

        $fields = [];
        $rule = $value = null;
        foreach ($values as $field => $text) {
            if (! is_string($text) || preg_match(self::SECRET_FIELD, (string) $field)) {
                continue;
            }
            foreach (self::PATTERNS as $name => $pattern) {
                if (preg_match($pattern, $text)) {
                    $fields[] = (string) $field;
                    $rule ??= $name;
                    $value ??= $text;
                    break;
                }
            }
        }

        if ($fields !== []) {
            SecurityEvent::record('suspicious_input', $request, ['rule' => $rule, 'fields' => $fields, 'value' => $value]);
        }
    }
}
