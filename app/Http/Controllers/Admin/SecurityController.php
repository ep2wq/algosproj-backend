<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\BannedIp;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\IpUtils;

// Owner only (README "Security events and IP bans"): the security events and the IP bans.
class SecurityController extends Controller
{
    private const DURATIONS = ['1h' => 'addHour', '24h' => 'addDay', '7d' => 'addWeek', 'permanent' => null];

    // 50 per page, last seen first. ip_banned says whether the event's IP has a ban in force.
    public function events(Request $request)
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(SecurityEvent::TYPES)],
            'status' => ['nullable', Rule::in(['seen', 'unseen'])],
            'ip' => ['nullable', 'string', 'max:45'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $query = SecurityEvent::query()->with('user:id,name,email')->orderByDesc('last_seen_at')->orderByDesc('id');

        if (isset($data['type'])) {
            $query->where('type', $data['type']);
        }
        if (isset($data['status'])) {
            $data['status'] === 'unseen' ? $query->whereNull('seen_at') : $query->whereNotNull('seen_at');
        }
        if (isset($data['ip'])) {
            $query->where('ip', BannedIp::normalize($data['ip']));
        }
        // Whole days on last_seen_at, in the app's timezone (UTC), both ends included.
        if (isset($data['from'])) {
            $query->where('last_seen_at', '>=', $data['from'].' 00:00:00');
        }
        if (isset($data['to'])) {
            $query->where('last_seen_at', '<', date('Y-m-d', strtotime($data['to'].' +1 day')).' 00:00:00');
        }

        $page = $query->paginate(50);
        $banned = BannedIp::active()->whereIn('ip', $page->getCollection()->pluck('ip')->unique())->pluck('ip')->all();
        $page->getCollection()->each(fn (SecurityEvent $event) => $event->setAttribute('ip_banned', in_array($event->ip, $banned, true)));

        return response()->json($page);
    }

    // For the badge in the admin navigation.
    public function summary()
    {
        return response()->json(['unseen' => SecurityEvent::whereNull('seen_at')->count()]);
    }

    public function markSeen(Request $request, SecurityEvent $securityEvent)
    {
        DB::transaction(function () use ($request, $securityEvent) {
            $securityEvent->forceFill(['seen_at' => $securityEvent->seen_at ?? now()])->save();
            AdminAuditLog::record($request->user(), 'security.event_seen', $securityEvent, $securityEvent->type.' '.$securityEvent->ip);
        });

        return response()->json(['id' => $securityEvent->id, 'seen_at' => $securityEvent->seen_at]);
    }

    public function markAllSeen(Request $request)
    {
        $updated = DB::transaction(function () use ($request) {
            $updated = SecurityEvent::whereNull('seen_at')->update(['seen_at' => now()]);
            AdminAuditLog::record($request->user(), 'security.events_seen', null, 'security events', ['count' => $updated]);

            return $updated;
        });

        return response()->json(['updated' => $updated]);
    }

    // 50 per page, newest first; active says whether the ban is still in force.
    public function bans()
    {
        $page = BannedIp::query()->with('bannedByUser:id,name,email')->orderByDesc('created_at')->orderByDesc('id')->paginate(50);
        $page->getCollection()->each(fn (BannedIp $ban) => $ban->setAttribute('active', $ban->isActive()));

        return response()->json($page);
    }

    public function ban(Request $request)
    {
        $data = $request->validate([
            'ip' => ['required', 'string', 'ip'],
            'duration' => ['required', Rule::in(array_keys(self::DURATIONS))],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $ip = BannedIp::normalize($data['ip']);

        // Loopback, private, link-local and reserved addresses: a ban there could hit the proxy,
        // the server itself or a whole local network.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false || IpUtils::isPrivateIp($ip)) {
            throw ValidationException::withMessages(['ip' => __('admin.ip_not_public')]);
        }
        if ($ip === BannedIp::normalize((string) $request->ip())) {
            throw ValidationException::withMessages(['ip' => __('admin.ip_is_yours')]);
        }

        $ban = DB::transaction(function () use ($request, $data, $ip) {
            $old = BannedIp::where('ip', $ip)->lockForUpdate()->first();
            if ($old?->isActive()) {
                throw ValidationException::withMessages(['ip' => __('admin.ip_already_banned')]);
            }
            // An expired ban of the same IP is replaced (ip is unique).
            $old?->delete();

            $method = self::DURATIONS[$data['duration']];
            $ban = BannedIp::create([
                'ip' => $ip,
                'expires_at' => $method ? now()->$method() : null,
                'note' => $data['note'] ?? null,
                'banned_by' => $request->user()->id,
            ]);
            AdminAuditLog::record($request->user(), 'security.ban', $ban, $ip, [
                'duration' => $data['duration'],
                'expires_at' => $ban->expires_at?->toJSON(),
                'note' => AdminAuditLog::short($data['note'] ?? null),
            ]);

            return $ban;
        });
        Cache::forget(BannedIp::CACHE_KEY);

        return response()->json($ban->refresh()->load('bannedByUser:id,name,email')->setAttribute('active', true), 201);
    }

    public function unban(Request $request, BannedIp $bannedIp)
    {
        DB::transaction(function () use ($request, $bannedIp) {
            $bannedIp->delete();
            AdminAuditLog::record($request->user(), 'security.unban', $bannedIp, $bannedIp->ip, [
                'expires_at' => $bannedIp->expires_at?->toJSON(),
                'blocked_count' => $bannedIp->blocked_count,
            ]);
        });
        Cache::forget(BannedIp::CACHE_KEY);

        return response()->noContent();
    }
}
