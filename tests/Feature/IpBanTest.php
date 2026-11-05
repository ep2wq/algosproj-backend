<?php

namespace Tests\Feature;

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
}
