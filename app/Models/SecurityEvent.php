<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

// Forbidden or suspicious requests for the owner's Security page (README "Security events and IP
// bans"). Never holds a password, a token, a cookie, an Authorization header or a request body:
// detail only has the route, the rule that matched, field names and at most 200 characters of a
// flagged value.
#[Fillable(['type', 'ip', 'user_agent', 'method', 'path', 'user_id', 'detail', 'count', 'first_seen_at', 'last_seen_at', 'seen_at'])]
class SecurityEvent extends Model
{
    use HasUuids;

    const TYPES = [
        'forbidden_admin_route', 'unauthenticated_admin_route', 'honeypot_filled', 'decoy_url',
        'suspicious_input', 'rate_limited', 'csrf_mismatch',
    ];

    // The hidden form field of login, register and forgot-password (the frontend uses the same name).
    const HONEYPOT_FIELD = 'form_extra';

    // Repeats from the same IP and type within this many minutes add to one row.
    private const AGGREGATE_MINUTES = 10;

    // At most this many new rows per IP per day, so nobody can fill the table.
    private const MAX_NEW_PER_IP_PER_DAY = 200;

    private const DETAIL_KEYS = ['route', 'rule', 'fields', 'value', 'required'];

    protected function casts(): array
    {
        return [
            'detail' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Never breaks the request: a failure is logged (type and error class only) and ignored.
    public static function record(string $type, Request $request, array $detail = []): void
    {
        try {
            self::write($type, $request, $detail);
        } catch (Throwable $e) {
            Log::warning('Could not record a security event', ['type' => $type, 'error' => $e::class]);
        }
    }

    // Real users never see or fill the hidden field. When it is filled, the event is recorded and
    // the caller answers like an ordinary failure, with no hint.
    public static function honeypotFilled(Request $request): bool
    {
        $value = $request->input(self::HONEYPOT_FIELD);
        if ($value === null || $value === '' || $value === []) {
            return false;
        }

        self::record('honeypot_filled', $request, ['fields' => [self::HONEYPOT_FIELD], 'value' => is_string($value) ? $value : null]);

        return true;
    }

    private static function write(string $type, Request $request, array $detail): void
    {
        $ip = BannedIp::normalize((string) $request->ip());
        $now = now();
        $route = $request->route();
        $detail = self::clean(['route' => $route ? ($route->getName() ?? $route->uri()) : null] + $detail);
        $path = self::text('/'.ltrim($request->path(), '/'), 255);
        $userId = Auth::guard('web')->hasUser() ? Auth::guard('web')->id() : null;
        $userAgent = $request->userAgent() === null ? null : self::text($request->userAgent(), 255);
        $method = self::text($request->method(), 10);

        $recent = self::where('ip', $ip)->where('type', $type)
            ->where('last_seen_at', '>=', $now->copy()->subMinutes(self::AGGREGATE_MINUTES))
            // Rate limits add up per route, the other types per IP.
            ->when($type === 'rate_limited', fn ($query) => $query->where('method', $method)->where('path', $path))
            ->orderByDesc('last_seen_at')->orderByDesc('id')
            ->first();

        if ($recent) {
            // The row shows the newest request; new activity shows as unseen again.
            $recent->forceFill([
                'count' => $recent->count + 1,
                'last_seen_at' => $now,
                'user_agent' => $userAgent,
                'method' => $method,
                'path' => $path,
                'detail' => $detail,
                'seen_at' => null,
                'user_id' => $recent->user_id ?? $userId,
            ])->save();

            return;
        }

        $key = 'security_events.new.'.$ip.'.'.$now->format('Y-m-d');
        Cache::add($key, 0, $now->copy()->addDay());
        if (Cache::increment($key) > self::MAX_NEW_PER_IP_PER_DAY) {
            return;
        }

        self::create([
            'type' => $type,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'method' => $method,
            'path' => $path,
            'user_id' => $userId,
            'detail' => $detail,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ]);
    }

    // Only the known keys, short strings, at most 20 field names.
    private static function clean(array $detail): array
    {
        $clean = [];
        foreach (self::DETAIL_KEYS as $key) {
            $value = $detail[$key] ?? null;
            if (is_string($value)) {
                $clean[$key] = self::text($value, 200);
            } elseif (is_array($value)) {
                $clean[$key] = array_map(fn ($item) => self::text((string) $item, 100), array_slice(array_values($value), 0, 20));
            }
        }

        return $clean;
    }

    // Valid UTF-8 without control characters, cut to $max characters.
    public static function text(string $value, int $max): string
    {
        return mb_substr((string) preg_replace('/[\x00-\x1F\x7F]/u', '?', mb_scrub($value, 'UTF-8')), 0, $max);
    }
}
