<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// Cookie sessions end to end (README "Sessions"): the real cookies are sent back by hand, with the
// database session driver, as in production.
class SessionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
        $this->user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);
    }

    // A new browser: no cookies, and nothing left over from the previous request in the guard or
    // the session store (a test reuses one app for every request).
    private function fresh(array $cookies = []): static
    {
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('auth.driver');
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->defaultCookies = [];
        $this->flushHeaders();

        // JSON test requests only send cookies with credentials, like fetch with credentials: 'include'.
        return $this->withCredentials()->withCookies($cookies);
    }

    private function login(bool $remember = false): TestResponse
    {
        return $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password', 'remember' => $remember]);
    }

    private function sessionId(TestResponse $response): string
    {
        return $response->getCookie(config('session.cookie'))->getValue();
    }

    private function recallerName(): string
    {
        return Auth::guard('web')->getRecallerName();
    }

    public function test_login_sets_an_http_only_session_cookie_and_returns_no_token(): void
    {
        $response = $this->login()->assertOk()
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('user.remember_token');
        $this->assertSame(['user'], array_keys($response->json()));

        $cookie = $response->getCookie(config('session.cookie'), false);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());
        $this->assertFalse($cookie->isSecure());
        // Idle timeout: SESSION_LIFETIME minutes (120 by default), renewed by every request.
        $this->assertEqualsWithDelta(now()->addMinutes(120)->getTimestamp(), $cookie->getExpiresTime(), 5);
        $this->assertNull($response->getCookie($this->recallerName(), false));
        $this->assertDatabaseHas('sessions', ['id' => $this->sessionId($response), 'user_id' => $this->user->id]);
    }

    public function test_register_logs_in_with_a_session_and_returns_no_token(): void
    {
        $response = $this->postJson('/api/register', ['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret-password', 'accepted_terms' => true])
            ->assertCreated()->assertJsonMissingPath('token');
        $this->assertSame(['user'], array_keys($response->json()));

        $this->fresh([config('session.cookie') => $this->sessionId($response)])
            ->getJson('/api/me')->assertOk()->assertJsonPath('email', 'bob@example.com');
    }

    public function test_me_works_with_the_cookie_and_is_401_without_it(): void
    {
        $id = $this->sessionId($this->login());

        $this->fresh([config('session.cookie') => $id])->getJson('/api/me')->assertOk()->assertJsonPath('email', 'ada@example.com');
        $this->fresh()->getJson('/api/me')->assertUnauthorized();
        $this->fresh([config('session.cookie') => 'not-a-session'])->getJson('/api/me')->assertUnauthorized();
    }

    public function test_the_session_id_changes_on_login(): void
    {
        $before = $this->sessionId($this->getJson('/sanctum/csrf-cookie')->assertNoContent());

        $after = $this->sessionId($this->fresh([config('session.cookie') => $before])->login()->assertOk());

        $this->assertNotSame($before, $after);
        // The id from before login is not logged in.
        $this->fresh([config('session.cookie') => $before])->getJson('/api/me')->assertUnauthorized();
    }

    public function test_state_changing_requests_need_the_csrf_token(): void
    {
        // Laravel skips the CSRF check in tests; this turns it back on.
        $this->app['env'] = 'local';

        $this->login()->assertStatus(419);
        $this->assertDatabaseMissing('user_activity_logs', ['event' => 'login']);

        // What the frontend does: fetch the CSRF cookie, then send it back in X-XSRF-TOKEN.
        $csrf = $this->fresh()->getJson('/sanctum/csrf-cookie')->assertNoContent();
        $xsrf = $csrf->getCookie('XSRF-TOKEN', false);
        $this->assertFalse($xsrf->isHttpOnly());
        $session = [config('session.cookie') => $this->sessionId($csrf)];

        $this->fresh($session)->withHeader('X-XSRF-TOKEN', 'wrong')->login()->assertStatus(419);
        $loggedIn = $this->fresh($session)->withHeader('X-XSRF-TOKEN', $xsrf->getValue())->login()->assertOk();

        // Logged in, a POST without the header is still refused, and with it accepted.
        $session = [config('session.cookie') => $this->sessionId($loggedIn)];
        $xsrf = $loggedIn->getCookie('XSRF-TOKEN', false)->getValue();
        $this->fresh($session)->postJson('/api/logout')->assertStatus(419);
        $this->fresh($session)->withHeader('X-XSRF-TOKEN', $xsrf)->postJson('/api/logout')->assertOk();
        // GETs need no token.
        $this->fresh()->getJson('/api/modules')->assertOk();
    }

    public function test_logout_ends_the_session(): void
    {
        $id = $this->sessionId($this->login());
        $session = [config('session.cookie') => $id];

        $this->fresh($session)->postJson('/api/logout')->assertOk()->assertExactJson(['message' => 'Logged out']);

        $this->assertDatabaseMissing('sessions', ['id' => $id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $this->user->id]);
        $this->fresh($session)->getJson('/api/me')->assertUnauthorized();
        $this->assertDatabaseHas('user_activity_logs', ['user_id' => $this->user->id, 'event' => 'logout']);
    }

    public function test_remember_me_sets_a_30_day_cookie_that_logs_back_in(): void
    {
        $response = $this->login(remember: true)->assertOk();

        $cookie = $response->getCookie($this->recallerName(), false);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $cookie->getExpiresTime(), 5);

        // The session is gone (idle timeout), the remember cookie alone logs the user back in.
        DB::table('sessions')->delete();
        $remember = [$this->recallerName() => $response->getCookie($this->recallerName())->getValue()];
        $this->fresh($remember)->getJson('/api/me')->assertOk()->assertJsonPath('email', 'ada@example.com');

        // Logout gives a new remember token, so the old cookie is dead.
        $id = $this->sessionId($this->fresh($remember)->login(remember: true));
        $this->fresh([config('session.cookie') => $id])->postJson('/api/logout')->assertOk();
        $this->fresh($remember)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_a_ban_ends_every_session_and_remember_cookie_and_unban_allows_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->login(remember: true);
        $session = [config('session.cookie') => $this->sessionId($response)];
        $remember = [$this->recallerName() => $response->getCookie($this->recallerName())->getValue()];
        $this->sessionId($this->fresh()->login());
        $this->assertSame(2, DB::table('sessions')->where('user_id', $this->user->id)->count());

        $this->fresh()->actingAs($admin)->postJson("/api/admin/users/{$this->user->id}/ban", ['reason' => 'Spam'])->assertOk();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->user->id)->count());
        $this->fresh($session)->getJson('/api/me')->assertUnauthorized();
        $this->fresh($remember)->getJson('/api/me')->assertUnauthorized();
        $this->fresh()->login()->assertForbidden()->assertJsonPath('reason', 'Spam');

        $this->fresh()->actingAs($admin)->postJson("/api/admin/users/{$this->user->id}/unban")->assertOk();
        $id = $this->sessionId($this->fresh()->login()->assertOk());
        $this->fresh([config('session.cookie') => $id])->getJson('/api/me')->assertOk();
    }

    public function test_logout_everywhere_ends_every_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $id = $this->sessionId($this->login());
        $this->fresh()->login();

        $this->fresh()->actingAs($admin)->postJson("/api/admin/users/{$this->user->id}/logout-everywhere")->assertOk();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->user->id)->count());
        $this->assertSame(['sessions_deleted' => 2], AdminAuditLog::where('action', 'user.logout_everywhere')->sole()->details);
        $this->fresh([config('session.cookie') => $id])->getJson('/api/me')->assertUnauthorized();
    }

    public function test_an_old_bearer_token_is_rejected(): void
    {
        // A personal access token as the old login issued it ("<id>|<plain>", stored as a sha256).
        $this->user->forceFill(['role' => 'owner'])->save();
        $tokenId = DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => User::class,
            'tokenable_id' => $this->user->id,
            'name' => 'api',
            'token' => hash('sha256', 'old-plain-token'),
            'abilities' => '["*"]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bearer = "{$tokenId}|old-plain-token";

        foreach ([['GET', '/api/me'], ['GET', '/api/leaderboard/me'], ['POST', '/api/logout'], ['GET', '/api/admin/modules'], ['GET', '/api/admin/audit-log']] as [$method, $uri]) {
            // From the frontend origin and from any other client.
            $this->fresh()->withToken($bearer)->json($method, $uri)->assertUnauthorized();
            $this->serverVariables = [];
            $this->fresh()->withToken($bearer)->json($method, $uri)->assertUnauthorized();
            $this->withServerVariables(['HTTP_ORIGIN' => self::FRONTEND]);
        }
        // Public routes don't recognise it either.
        $this->fresh()->withToken($bearer)->getJson('/api/leaderboard')->assertOk();
        $this->assertNull(Auth::guard('web')->user());
    }

    public function test_only_the_frontend_origin_can_log_in(): void
    {
        $this->serverVariables = [];
        $this->login()->assertStatus(419);
        $this->postJson('/api/register', ['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret-password', 'accepted_terms' => true])->assertStatus(419);

        $this->withServerVariables(['HTTP_ORIGIN' => 'http://evil.test']);
        $this->fresh()->login()->assertStatus(419);

        $this->assertDatabaseCount('user_activity_logs', 0);
        $this->assertDatabaseMissing('users', ['email' => 'bob@example.com']);
    }
}
