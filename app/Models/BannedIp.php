<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

// An IP the owner banned (README "Security events and IP bans"). expires_at NULL = permanent.
#[Fillable(['ip', 'expires_at', 'note', 'banned_by'])]
class BannedIp extends Model
{
    use HasUuids;

    // ip => expiry timestamp (null = permanent) of the bans that had not expired when it was cached.
    const CACHE_KEY = 'security.banned_ips';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_blocked_at' => 'datetime',
        ];
    }

    public function bannedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    // Cached for 60 seconds; ban and unban forget it. The expiry is checked on every request, so a
    // ban that expires inside the 60 seconds stops at once.
    public static function isBanned(string $ip): bool
    {
        $bans = Cache::remember(self::CACHE_KEY, 60, fn () => self::active()->get(['ip', 'expires_at'])
            ->mapWithKeys(fn (self $ban) => [$ban->ip => $ban->expires_at?->getTimestamp()])->all());

        $ip = self::normalize($ip);
        if (! array_key_exists($ip, $bans)) {
            return false;
        }

        return $bans[$ip] === null || $bans[$ip] > now()->getTimestamp();
    }

    // Counts every blocked request in the cache and writes the count at most once a minute per IP.
    public static function noteBlocked(string $ip): void
    {
        $pending = 'security.blocked.pending.'.$ip;
        Cache::add($pending, 0, 3600);
        Cache::increment($pending);

        if (Cache::add('security.blocked.written.'.$ip, true, 60)) {
            $count = (int) Cache::pull($pending);
            if ($count > 0) {
                self::where('ip', $ip)->increment('blocked_count', $count, ['last_blocked_at' => now()]);
            }
        }
    }

    // The canonical text form, so "2001:DB8::1" matches "2001:db8::1", and an IPv4 address seen
    // through IPv6 ("::ffff:8.8.4.4", a dual-stack server) is the IPv4 address itself.
    public static function normalize(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return $ip;
        }
        if (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            $packed = substr($packed, 12);
        }

        return (string) inet_ntop($packed);
    }
}
