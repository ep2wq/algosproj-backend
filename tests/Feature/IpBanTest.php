<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\BannedIp;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IpBanTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'owner']);
    }

    // A request from $ip (the frontend origin kept), with no user from an earlier request.
    private function fromIp(string $ip): static
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->withServerVariables(['HTTP_ORIGIN' => self::FRONTEND, 'REMOTE_ADDR' => $ip]);
    }

    private function asOwner(): static
    {
        return $this->fromIp('127.0.0.1')->actingAs($this->owner);
    }

    public function test_a_banned_ip_gets_a_plain_403_before_the_session_starts(): void
    {
        BannedIp::create(['ip' => '9.9.9.9']);

        foreach ([['GET', '/api/modules'], ['POST', '/api/login'], ['GET', '/api/admin/users'], ['GET', '/.env']] as [$method, $uri]) {
            $response = $this->fromIp('9.9.9.9')->json($method, $uri, ['email' => 'a@example.com', 'password' => 'x']);
            $response->assertForbidden()->assertExactJson(['message' => 'Forbidden.']);
            // No session or XSRF cookie: the session middleware never ran.
            $this->assertSame([], $response->headers->getCookies(), $uri);
        }
        $this->fromIp('9.9.9.9')->getJson('/api/modules', ['Accept-Language' => 'ar'])->assertForbidden()->assertExactJson(['message' => 'الوصول غير مسموح.']);

        // Blocked requests are not recorded as events, and other IPs are not affected.
        $this->assertSame(0, SecurityEvent::count());
        $this->assertSame(0, DB::table('sessions')->count());
        $this->fromIp('9.9.9.8')->getJson('/api/modules')->assertOk();
    }

    public function test_an_ipv4_address_seen_through_ipv6_is_the_same_address(): void
    {
        BannedIp::create(['ip' => '9.9.9.9']);
        $this->fromIp('::ffff:9.9.9.9')->getJson('/api/modules')->assertForbidden();

        $this->fromIp('::FFFF:9.9.9.6')->getJson('/.env')->assertNotFound();
        $this->assertSame('9.9.9.6', SecurityEvent::sole()->ip);

        $this->asOwner()->postJson('/api/admin/security/bans', ['ip' => '::ffff:9.9.9.6', 'duration' => '1h'])
            ->assertCreated()->assertJsonPath('ip', '9.9.9.6');
        $this->assertSame('2001:db8::1', BannedIp::normalize('2001:DB8:0::1'));
    }

    public function test_an_expired_ban_is_ignored_even_inside_the_cache_window(): void
    {
        BannedIp::create(['ip' => '9.9.9.9', 'expires_at' => now()->addSeconds(30)]);
        BannedIp::create(['ip' => '9.9.9.7', 'expires_at' => now()->subMinute()]);

        $this->fromIp('9.9.9.9')->getJson('/api/modules')->assertForbidden();
        $this->fromIp('9.9.9.7')->getJson('/api/modules')->assertOk();
        $this->travel(31)->seconds();
        $this->fromIp('9.9.9.9')->getJson('/api/modules')->assertOk();
    }

    public function test_the_ban_list_is_cached_for_60_seconds_and_forgotten_on_ban_and_unban(): void
    {
        $this->fromIp('9.9.9.9')->getJson('/api/modules')->assertOk();

        // Written behind the cache's back: seen after at most 60 seconds.
        $ban = BannedIp::create(['ip' => '9.9.9.9']);
        $this->fromIp('9.9.9.9')->getJson('/api/modules')->assertOk();
        $this->travel(61)->seconds();
        $this->fromIp('9.9.9.9')->getJson('/api/modules')->assertForbidden();

        // Through the API: at once, both ways.
        $this->asOwner()->deleteJson("/api/admin/security/bans/{$ban->id}")->assertNoContent();
        $this->fromIp('9.9.9.9')->getJson('/api/modules')->assertOk();
        $this->asOwner()->postJson('/api/admin/security/bans', ['ip' => '9.9.9.9', 'duration' => '1h'])->assertCreated();
        $this->fromIp('9.9.9.9')->getJson('/api/modules')->assertForbidden();
    }

    public function test_blocked_requests_are_counted_with_at_most_one_write_a_minute(): void
    {
        $ban = BannedIp::create(['ip' => '9.9.9.9']);

        DB::enableQueryLog();
        for ($i = 0; $i < 3; $i++) {
            $this->fromIp('9.9.9.9')->getJson('/api/modules')->assertForbidden();
        }
        $writes = collect(DB::getQueryLog())->filter(fn ($query) => preg_match('/^update [`"]banned_ips[`"]/', $query['query']) === 1)->count();
        DB::disableQueryLog();

        $this->assertSame(1, $writes);
        $this->assertSame(1, $ban->fresh()->blocked_count);
        $this->assertNotNull($ban->fresh()->last_blocked_at);

        // The next write, a minute later, adds the requests counted in between.
        $this->travel(61)->seconds();
        $this->fromIp('9.9.9.9')->getJson('/api/modules')->assertForbidden();
        $this->assertSame(4, $ban->fresh()->blocked_count);
    }

    public function test_ban_creates_the_row_with_its_expiry_and_an_audit_entry(): void
    {
        $this->freezeSecond();
        foreach (['1h' => now()->addHour(), '24h' => now()->addDay(), '7d' => now()->addWeek(), 'permanent' => null] as $duration => $expires) {
            $ip = '8.8.4.'.(count(BannedIp::all()) + 1);
            $response = $this->asOwner()->postJson('/api/admin/security/bans', ['ip' => $ip, 'duration' => $duration, 'note' => 'Scanner'])->assertCreated();

            $response->assertJsonPath('ip', $ip)->assertJsonPath('note', 'Scanner')->assertJsonPath('active', true)
                ->assertJsonPath('blocked_count', 0)->assertJsonPath('banned_by', $this->owner->id)
                ->assertJsonPath('banned_by_user.email', $this->owner->email);
            $this->assertSame($expires?->toJSON(), BannedIp::where('ip', $ip)->sole()->expires_at?->toJSON(), $duration);
        }

        $audit = AdminAuditLog::where('action', 'security.ban')->orderBy('created_at')->orderBy('id')->first();
        $this->assertSame($this->owner->id, $audit->actor_id);
        $this->assertSame('banned_ip', $audit->target_type);
        $this->assertSame('8.8.4.1', $audit->target_label);
        $this->assertSame('127.0.0.1', $audit->ip);
        $this->assertSameJson(['duration' => '1h', 'expires_at' => now()->addHour()->toJSON(), 'note' => 'Scanner'], $audit->details);
        $this->assertSame(4, AdminAuditLog::where('action', 'security.ban')->count());
    }

    public function test_unban_deletes_the_row_and_writes_an_audit_entry(): void
    {
        $ban = BannedIp::create(['ip' => '8.8.4.4', 'banned_by' => $this->owner->id]);

        $this->asOwner()->deleteJson("/api/admin/security/bans/{$ban->id}")->assertNoContent();

        $this->assertSame(0, BannedIp::count());
        $audit = AdminAuditLog::sole();
        $this->assertSame(['security.unban', $ban->id, '8.8.4.4'], [$audit->action, $audit->target_id, $audit->target_label]);
        $this->asOwner()->deleteJson("/api/admin/security/bans/{$ban->id}")->assertNotFound();
    }

    public function test_loopback_private_reserved_invalid_and_own_ips_cannot_be_banned(): void
    {
        foreach (['127.0.0.1', '127.1.2.3', '10.0.0.5', '172.16.0.1', '192.168.1.1', '169.254.1.1', '100.64.0.1', '0.0.0.0', '203.0.113.9', '::1', 'fe80::1', 'fc00::1', '::ffff:10.0.0.1'] as $ip) {
            $this->asOwner()->postJson('/api/admin/security/bans', ['ip' => $ip, 'duration' => '1h'])
                ->assertUnprocessable()->assertJsonPath('errors.ip.0', 'Only a public IP address can be banned, not a loopback, private or reserved one.');
        }
        foreach (['not-an-ip', '8.8.8.8/24', ''] as $ip) {
            $this->asOwner()->postJson('/api/admin/security/bans', ['ip' => $ip, 'duration' => '1h'])->assertUnprocessable()->assertJsonValidationErrors('ip');
        }
        $this->asOwner()->postJson('/api/admin/security/bans', ['ip' => '8.8.8.8', 'duration' => '2h'])->assertUnprocessable()->assertJsonValidationErrors('duration');
        $this->asOwner()->postJson('/api/admin/security/bans', ['ip' => '8.8.8.8', 'duration' => '1h', 'note' => str_repeat('n', 256)])->assertUnprocessable()->assertJsonValidationErrors('note');

        // The owner's own IP, in any spelling.
        $this->fromIp('2606:4700::1111')->actingAs($this->owner)->postJson('/api/admin/security/bans', ['ip' => '2606:4700:0:0:0:0:0:1111', 'duration' => '1h'])
            ->assertUnprocessable()->assertJsonPath('errors.ip.0', "This is your own IP address. You can't ban it.");

        $this->assertSame(0, BannedIp::count());
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_an_ip_banned_already_is_refused_and_an_expired_ban_is_replaced(): void
    {
        BannedIp::create(['ip' => '8.8.4.4']);
        $this->asOwner()->postJson('/api/admin/security/bans', ['ip' => '8.8.4.4', 'duration' => '1h'])
            ->assertUnprocessable()->assertJsonPath('errors.ip.0', 'This IP address is already banned.');

        $old = BannedIp::create(['ip' => '8.8.4.5', 'expires_at' => now()->subHour()]);
        $this->asOwner()->postJson('/api/admin/security/bans', ['ip' => '8.8.4.5', 'duration' => 'permanent'])->assertCreated();
        $this->assertNull(BannedIp::where('ip', '8.8.4.5')->sole()->expires_at);
        $this->assertNull(BannedIp::find($old->id));
    }

    public function test_events_list_filters_newest_first_with_account_and_ban_flag(): void
    {
        $user = User::factory()->create();
        $make = fn (string $type, string $ip, int $minutesAgo, ?string $seen = null, ?string $userId = null) => SecurityEvent::create([
            'type' => $type, 'ip' => $ip, 'method' => 'GET', 'path' => '/x', 'user_id' => $userId, 'detail' => ['route' => 'x'],
            'first_seen_at' => now()->subMinutes($minutesAgo), 'last_seen_at' => now()->subMinutes($minutesAgo), 'seen_at' => $seen,
        ]);
        $a = $make('decoy_url', '9.9.9.1', 30);
        $b = $make('forbidden_admin_route', '9.9.9.2', 10, null, $user->id);
        $c = $make('decoy_url', '9.9.9.2', 20, now()->toDateTimeString());
        BannedIp::create(['ip' => '9.9.9.2']);
        BannedIp::create(['ip' => '9.9.9.1', 'expires_at' => now()->subMinute()]);

        $page = $this->asOwner()->getJson('/api/admin/security/events')->assertOk();
        $this->assertSame([$b->id, $c->id, $a->id], array_column($page->json('data'), 'id'));
        $this->assertSame([true, true, false], array_column($page->json('data'), 'ip_banned'));
        $this->assertSame(['id' => $user->id, 'name' => $user->name, 'email' => $user->email], $page->json('data.0.user'));
        $this->assertNull($page->json('data.1.user'));
        $this->assertSame([1, 1, 50, 3], [$page->json('current_page'), $page->json('last_page'), $page->json('per_page'), $page->json('total')]);

        $ids = fn (string $query) => array_column($this->asOwner()->getJson("/api/admin/security/events?$query")->assertOk()->json('data'), 'id');
        $this->assertSame([$c->id, $a->id], $ids('type=decoy_url'));
        $this->assertSame([$b->id, $a->id], $ids('status=unseen'));
        $this->assertSame([$c->id], $ids('status=seen'));
        $this->assertSame([$b->id, $c->id], $ids('ip=9.9.9.2'));
        $this->assertSame([$b->id, $c->id, $a->id], $ids('from='.now()->subMinutes(30)->toDateString().'&to='.now()->toDateString()));
        $this->assertSame([], $ids('to='.now()->subDays(2)->toDateString()));
        $this->asOwner()->getJson('/api/admin/security/events?type=nope')->assertUnprocessable();
        $this->asOwner()->getJson('/api/admin/security/events?status=maybe')->assertUnprocessable();
    }

    public function test_summary_mark_seen_and_mark_all_seen(): void
    {
        $make = fn () => SecurityEvent::create(['type' => 'decoy_url', 'ip' => '9.9.9.1', 'method' => 'GET', 'path' => '/x', 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $one = $make();
        $make();
        $make();

        $this->asOwner()->getJson('/api/admin/security/summary')->assertExactJson(['unseen' => 3]);
        $this->asOwner()->postJson("/api/admin/security/events/{$one->id}/seen")->assertOk()->assertJsonPath('id', $one->id);
        $this->assertNotNull($one->fresh()->seen_at);
        $this->asOwner()->getJson('/api/admin/security/summary')->assertExactJson(['unseen' => 2]);
        $this->asOwner()->postJson('/api/admin/security/events/seen')->assertExactJson(['updated' => 2]);
        $this->asOwner()->getJson('/api/admin/security/summary')->assertExactJson(['unseen' => 0]);

        $this->assertSame(['security.event_seen', 'security.events_seen'], AdminAuditLog::orderBy('created_at')->orderBy('id')->pluck('action')->all());
        $this->assertSameJson(['count' => 2], AdminAuditLog::where('action', 'security.events_seen')->sole()->details);
    }

    public function test_bans_list_newest_first_with_who_banned_and_active_flag(): void
    {
        BannedIp::create(['ip' => '9.9.9.1', 'banned_by' => $this->owner->id, 'expires_at' => now()->subMinute()]);
        $this->travel(1)->seconds();
        BannedIp::create(['ip' => '9.9.9.2', 'banned_by' => $this->owner->id, 'note' => 'Scanner']);

        $page = $this->asOwner()->getJson('/api/admin/security/bans')->assertOk();
        $this->assertSame(['9.9.9.2', '9.9.9.1'], array_column($page->json('data'), 'ip'));
        $this->assertSame([true, false], array_column($page->json('data'), 'active'));
        $this->assertSame(['id' => $this->owner->id, 'name' => $this->owner->name, 'email' => $this->owner->email], $page->json('data.0.banned_by_user'));
    }

    public function test_every_security_route_is_owner_only(): void
    {
        $event = SecurityEvent::create(['type' => 'decoy_url', 'ip' => '9.9.9.1', 'method' => 'GET', 'path' => '/x', 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $ban = BannedIp::create(['ip' => '9.9.9.2']);
        $routes = [
            ['GET', '/api/admin/security/summary', 200], ['GET', '/api/admin/security/events', 200], ['POST', '/api/admin/security/events/seen', 200],
            ['POST', "/api/admin/security/events/{$event->id}/seen", 200], ['GET', '/api/admin/security/bans', 200],
            ['POST', '/api/admin/security/bans', 201], ['DELETE', "/api/admin/security/bans/{$ban->id}", 204],
        ];
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ($routes as [$method, $uri, $ok]) {
            $data = ['ip' => '9.9.9.3', 'duration' => '1h'];
            $this->fromIp('127.0.0.1')->json($method, $uri, $data)->assertUnauthorized();
            $this->fromIp('127.0.0.1')->actingAs($user)->json($method, $uri, $data)->assertForbidden();
            $this->fromIp('127.0.0.1')->actingAs($admin)->json($method, $uri, $data)->assertForbidden();
            $this->asOwner()->json($method, $uri, $data)->assertStatus($ok);
        }
    }
}
