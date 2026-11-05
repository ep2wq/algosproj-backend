<?php

namespace Tests\Feature;

use App\Models\BannedIp;
use App\Models\Module;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class SecurityEventTest extends TestCase
{
    use RefreshDatabase;

    // Every stored column, without ids and timestamps.
    private function rows(): array
    {
        return SecurityEvent::orderBy('first_seen_at')->orderBy('id')->get()
            ->map(fn (SecurityEvent $event) => $event->only(['type', 'ip', 'user_agent', 'method', 'path', 'user_id', 'detail', 'count']))
            ->all();
    }

    private function fresh(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
    }

    public function test_a_normal_user_and_an_admin_above_their_role_are_recorded(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->withHeader('User-Agent', 'TestBrowser/1.0')->actingAs($user)->getJson('/api/admin/users')->assertForbidden();
        $this->assertSame([['type' => 'forbidden_admin_route', 'ip' => '127.0.0.1', 'user_agent' => 'TestBrowser/1.0', 'method' => 'GET',
            'path' => '/api/admin/users', 'user_id' => $user->id, 'detail' => ['route' => 'api/admin/users', 'required' => 'admin'], 'count' => 1]], $this->rows());

        // Same IP and type within 10 minutes: the same row, count 2, the newest request, the first account.
        $this->fresh();
        $this->actingAs($admin)->getJson('/api/admin/activity')->assertForbidden();
        $this->assertSame([['type' => 'forbidden_admin_route', 'ip' => '127.0.0.1', 'user_agent' => 'Symfony', 'method' => 'GET',
            'path' => '/api/admin/activity', 'user_id' => $user->id, 'detail' => ['route' => 'api/admin/activity', 'required' => 'owner'], 'count' => 2]], $this->rows());

        // From another IP the admin gets a row of their own.
        $this->fresh();
        $this->withServerVariables(['HTTP_ORIGIN' => self::FRONTEND, 'REMOTE_ADDR' => '9.9.9.9'])->actingAs($admin)->getJson('/api/admin/audit-log')->assertForbidden();
        $this->assertSame($admin->id, SecurityEvent::where('ip', '9.9.9.9')->sole()->user_id);
    }

    public function test_a_logged_out_admin_request_is_recorded(): void
    {
        $this->postJson('/api/admin/modules', ['title' => 'x'])->assertUnauthorized();

        $this->assertSame([['type' => 'unauthenticated_admin_route', 'ip' => '127.0.0.1', 'user_agent' => 'Symfony', 'method' => 'POST',
            'path' => '/api/admin/modules', 'user_id' => null, 'detail' => ['route' => 'api/admin/modules'], 'count' => 1]], $this->rows());
    }

    public function test_decoy_urls_are_recorded_and_answer_like_any_unknown_path(): void
    {
        $unknown = $this->get('/no-such-page');
        foreach (['/.env', '/.git/config', '/wp-login.php', '/wp-admin/setup-config.php', '/xmlrpc.php', '/phpmyadmin/', '/admin.php'] as $i => $path) {
            $response = $this->get($path);
            $response->assertNotFound();
            $this->assertSame($unknown->getContent(), $response->getContent(), $path);
        }

        // Seven hits from one IP within 10 minutes: one row.
        $this->assertSame([['type' => 'decoy_url', 'ip' => '127.0.0.1', 'user_agent' => 'Symfony', 'method' => 'GET',
            'path' => '/admin.php', 'user_id' => null, 'detail' => [], 'count' => 7]], $this->rows());

        // An ordinary unknown path and an API 404 are not events.
        $this->getJson('/api/no-such-route')->assertNotFound();
        $this->assertSame(1, SecurityEvent::count());
    }

    public function test_suspicious_query_strings_and_auth_fields_are_recorded_but_not_blocked(): void
    {
        $this->getJson('/api/leaderboard?limit=1%20UNION%20SELECT%20password%20FROM%20users')->assertOk();
        $this->assertSameJson(['type' => 'suspicious_input', 'method' => 'GET', 'path' => '/api/leaderboard',
            'detail' => ['route' => 'api/leaderboard', 'rule' => 'sql_injection', 'fields' => ['limit'], 'value' => '1 UNION SELECT password FROM users']],
            collect($this->rows()[0])->only(['type', 'method', 'path', 'detail'])->all());

        $cases = [
            ['GET', '/api/challenges?q=%3Cscript%3Ealert(1)%3C/script%3E', [], 'script_tag', ['q']],
            ['GET', '/api/challenges?f[a]=..%2F..%2Fetc%2Fpasswd', [], 'path_traversal', ['f.a']],
            ['GET', '/api/challenges?x=a%00b', [], 'null_byte', ['x']],
            ['POST', '/api/register', ['name' => '<img src=x onerror=alert(1)>', 'email' => "a' OR '1'='1", 'password' => 'secret-password', 'accepted_terms' => true], 'script_tag', ['name', 'email']],
        ];
        foreach ($cases as [$method, $uri, $data, $rule, $fields]) {
            SecurityEvent::query()->delete();
            $this->json($method, $uri, $data);
            $this->assertSame($rule, $this->rows()[0]['detail']['rule'] ?? null, $uri);
            $this->assertSame($fields, $this->rows()[0]['detail']['fields'], $uri);
        }

        // The control character is stored as "?", never as a raw null byte.
        SecurityEvent::query()->delete();
        $this->getJson('/api/challenges?x=a%00b');
        $this->assertSame('a?b', $this->rows()[0]['detail']['value']);
    }

    public function test_code_submissions_admin_editors_passwords_and_token_fields_are_never_flagged(): void
    {
        config(['services.sandbox.url' => 'http://sandbox.test', 'services.sandbox.secret' => 'test-secret']);
        Http::fake(['sandbox.test/*' => Http::response(['status' => 'passed', 'results' => [['passed' => true, 'actual' => '2', 'expected' => '2', 'error' => null]], 'runtime_ms' => 1, 'compile_error' => null])]);
        $module = Module::create(['slug' => 'm', 'title' => 'M', 'track' => 'basics', 'sort_order' => 1]);
        $lesson = $module->lessons()->create(['slug' => 'l', 'title' => 'L', 'sort_order' => 1]);
        $problem = $lesson->problems()->create(['title' => 'P', 'description' => 'D', 'difficulty' => 'easy', 'points' => 10]);
        $problem->testCases()->create(['input' => '1', 'expected_output' => '2', 'is_hidden' => false]);
        $owner = User::factory()->create(['role' => 'owner']);
        $code = "q = \"' OR 1=1 --\"\nprint('<script>alert(1)</script>', '../../etc', 'UNION SELECT')";

        $this->actingAs(User::factory()->create())->postJson("/api/problems/{$problem->id}/submit", ['language' => 'python', 'code' => $code])->assertOk();
        $this->fresh();
        $this->actingAs($owner)->putJson("/api/admin/lessons/{$lesson->id}", ['module_id' => $module->id, 'title' => '<script>x</script>', 'slug' => 'l', 'body' => $code, 'sort_order' => 1])->assertOk();
        $this->actingAs($owner)->postJson("/api/admin/problems/{$problem->id}/test-cases", ['input' => "' OR 1=1 --", 'expected_output' => '<script>', 'is_hidden' => false])->assertCreated();
        $this->actingAs($owner)->getJson('/api/admin/problems?search=%27%20OR%201%3D1%20--%20UNION%20SELECT')->assertOk();
        $this->fresh();
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => "' OR 1=1 -- <script>"])->assertUnauthorized();
        $this->getJson('/api/challenges?token=%3Cscript%3E&password=UNION%20SELECT%201')->assertOk();

        $this->assertSame(0, SecurityEvent::count());
    }

    public function test_rate_limits_and_csrf_failures_are_recorded_and_added_up(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong-password']);
        }
        // Without the frontend origin there is no session: 419.
        $this->withServerVariables([]);
        $this->postJson('/api/login', ['email' => 'other@example.com', 'password' => 'x'])->assertStatus(419);
        $this->postJson('/api/register', [])->assertStatus(419);

        $rows = collect($this->rows())->keyBy('type');
        $this->assertSame(['type' => 'rate_limited', 'method' => 'POST', 'path' => '/api/login', 'detail' => ['route' => 'api/login'], 'count' => 3],
            collect($rows['rate_limited'])->only(['type', 'method', 'path', 'detail', 'count'])->all());
        $this->assertSame(['type' => 'csrf_mismatch', 'path' => '/api/register', 'detail' => ['route' => 'api/register'], 'count' => 2],
            collect($rows['csrf_mismatch'])->only(['type', 'path', 'detail', 'count'])->all());
    }

    public function test_rate_limits_add_up_per_route(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong-password']);
            $this->postJson('/api/register', ['name' => 'A', 'email' => "a$i@example.com", 'password' => 'short']);
        }

        $this->assertSame(['/api/login', '/api/register'], SecurityEvent::where('type', 'rate_limited')->orderBy('path')->pluck('path')->all());
    }

    public function test_no_password_cookie_token_or_authorization_header_is_ever_stored(): void
    {
        $secrets = ['Sup3r-Secret-Pass', 'COOKIE-SECRET-VALUE', 'BEARER-SECRET-TOKEN', 'RESET-TOKEN-SECRET'];

        $this->withHeaders(['Authorization' => 'Bearer BEARER-SECRET-TOKEN', 'Cookie' => 'algoslab-session=COOKIE-SECRET-VALUE; XSRF-TOKEN=COOKIE-SECRET-VALUE'])
            ->withUnencryptedCookie('algoslab-session', 'COOKIE-SECRET-VALUE')
            ->getJson('/api/admin/users')->assertUnauthorized();
        $this->fresh();
        $this->postJson('/api/reset-password', ['token' => 'RESET-TOKEN-SECRET', 'email' => "x' OR '1'='1", 'password' => 'Sup3r-Secret-Pass', 'password_confirmation' => 'Sup3r-Secret-Pass']);
        $this->get('/.env?password=Sup3r-Secret-Pass');

        $this->assertSame(['decoy_url', 'suspicious_input', 'unauthenticated_admin_route'], SecurityEvent::orderBy('type')->pluck('type')->all());
        $stored = json_encode(SecurityEvent::all()->map->getAttributes());
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }
        // The path is stored without its query string.
        $this->assertSame('/.env', SecurityEvent::where('type', 'decoy_url')->value('path'));
    }

    public function test_repeats_within_10_minutes_add_up_and_later_ones_start_a_new_unseen_row(): void
    {
        $this->get('/.env');
        SecurityEvent::query()->update(['seen_at' => now()]);
        $this->travel(9)->minutes();
        $this->get('/.env');

        $row = SecurityEvent::sole();
        $this->assertSame(2, $row->count);
        $this->assertNull($row->seen_at);
        $this->assertTrue($row->last_seen_at->gt($row->first_seen_at));

        $this->travel(11)->minutes();
        $this->get('/.env');
        $this->assertSame([2, 1], SecurityEvent::orderBy('first_seen_at')->pluck('count')->all());
    }

    public function test_at_most_200_new_rows_per_ip_per_day(): void
    {
        Cache::put('security_events.new.127.0.0.1.'.now()->format('Y-m-d'), 199, now()->addDay());

        $this->get('/.env');
        $this->postJson('/api/admin/modules')->assertUnauthorized();
        $this->assertSame(['decoy_url'], SecurityEvent::pluck('type')->all());

        // An existing row still adds up, and another IP is not affected.
        $this->get('/.env');
        $this->assertSame(2, SecurityEvent::sole()->count);
        $this->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])->get('/.env');
        $this->assertSame(2, SecurityEvent::count());
    }

    public function test_a_recording_failure_never_breaks_the_request(): void
    {
        Log::spy();
        SecurityEvent::creating(fn () => throw new RuntimeException('database down'));

        $this->get('/.env')->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson('/api/admin/users')->assertForbidden();

        Log::shouldHaveReceived('warning')->with('Could not record a security event', ['type' => 'decoy_url', 'error' => RuntimeException::class])->once();
        Log::shouldHaveReceived('warning')->with('Could not record a security event', ['type' => 'forbidden_admin_route', 'error' => RuntimeException::class])->once();
    }

    public function test_without_trusted_proxies_x_forwarded_for_is_ignored(): void
    {
        config(['security.trusted_proxies' => null]);
        BannedIp::create(['ip' => '9.9.9.9']);

        $this->withHeader('X-Forwarded-For', '8.8.8.8')->get('/.env')->assertNotFound();
        $this->assertSame('127.0.0.1', SecurityEvent::sole()->ip);

        // A banned client cannot get past the ban with a fake header either.
        $this->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])->withHeader('X-Forwarded-For', '8.8.8.8')->getJson('/api/modules')->assertForbidden();
    }

    public function test_with_a_trusted_proxy_the_forwarded_client_ip_is_used(): void
    {
        config(['security.trusted_proxies' => '127.0.0.1, 10.0.0.0/8']);

        $this->withHeader('X-Forwarded-For', '8.8.8.8')->get('/.env')->assertNotFound();
        $this->assertSame('8.8.8.8', SecurityEvent::sole()->ip);

        // A proxy that is not on the list is not believed.
        SecurityEvent::query()->delete();
        $this->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])->withHeader('X-Forwarded-For', '8.8.8.8')->get('/.env');
        $this->assertSame('9.9.9.9', SecurityEvent::sole()->ip);
    }
}
