<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserActivityLog;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// Google and GitHub login (README "Social login"). The real Socialite drivers run, but their HTTP
// client is a Guzzle mock: no request ever leaves the test, and one that is not expected fails
// (an empty mock throws). Callbacks come like the real ones: Referer google.com, no Origin.
class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private const STATE = 'test-state-value';

    private const TOKEN = 'secret-access-token';

    /** @var array<int, array> Requests the providers made (Guzzle history). */
    private array $requests = [];

    /** @var array<int, MessageLogged> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['app.frontend_url' => self::FRONTEND]);
        foreach (['google', 'github'] as $provider) {
            config(["services.$provider.client_id" => "$provider-client-id", "services.$provider.client_secret" => "$provider-secret"]);
            $this->provider($provider, []);
        }
        Event::listen(MessageLogged::class, fn (MessageLogged $e) => $this->logged[] = $e);
    }

    // The provider's answers, in order, through Socialite's guzzle option. Drivers are cached with
    // the request they were made for, so they are dropped and made again by the next request.
    private function provider(string $provider, array $responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->requests));
        config(["services.$provider.guzzle" => ['handler' => $stack]]);
        Socialite::forgetDrivers();
    }

    private function google(array $profile = []): array
    {
        return [
            new Response(200, [], json_encode(['access_token' => self::TOKEN, 'token_type' => 'Bearer', 'expires_in' => 3600])),
            new Response(200, [], json_encode($profile + ['sub' => 'g-1', 'name' => 'Ada Google', 'email' => 'ada@example.com', 'email_verified' => true])),
        ];
    }

    private function github(?array $emails = null, array $profile = []): array
    {
        $emails ??= [['email' => 'ada@example.com', 'primary' => true, 'verified' => true]];

        return [
            new Response(200, [], json_encode(['access_token' => self::TOKEN, 'token_type' => 'bearer', 'scope' => 'user:email'])),
            new Response(200, [], json_encode($profile + ['id' => 42, 'node_id' => 'n42', 'login' => 'ada-gh', 'name' => 'Ada GitHub', 'email' => null, 'avatar_url' => 'https://avatars.example/42', 'html_url' => null])),
            new Response(200, [], json_encode($emails)),
        ];
    }

    // The provider sends the browser back: a top-level GET from its site, no Origin header.
    private function returnFrom(string $provider, array $responses, ?string $query = null)
    {
        $this->provider($provider, $responses);
        $this->withSession(['state' => self::STATE, 'code_verifier' => str_repeat('v', 64)]);
        $response = $this->withServerVariables(['HTTP_REFERER' => "https://accounts.$provider.com/"])
            ->get("/auth/$provider/callback?".($query ?? 'state='.self::STATE.'&code=test-code'));
        $this->withServerVariables(['HTTP_ORIGIN' => self::FRONTEND]);

        return $response;
    }

    private function assertBackOnLogin($response, string $error): void
    {
        $response->assertRedirect(self::FRONTEND.'/login?error='.$error);
        $this->assertGuest('web');
    }

    private function sessionId(): string
    {
        return app('session.store')->getId();
    }

    private function complete(array $data = [])
    {
        return $this->postJson('/api/auth/complete', $data + ['name' => 'Ada Lovelace', 'accepted_terms' => true]);
    }

    public function test_an_unknown_provider_is_404(): void
    {
        foreach (['facebook', 'twitter', 'GOOGLE'] as $provider) {
            $this->get("/auth/$provider/redirect")->assertNotFound();
            $this->get("/auth/$provider/callback?code=x&state=y")->assertNotFound();
        }
    }

    public function test_redirect_goes_to_the_provider_with_state_and_pkce(): void
    {
        $expected = [
            'google' => ['https://accounts.google.com/o/oauth2/auth', 'openid email profile'],
            'github' => ['https://github.com/login/oauth/authorize', 'user:email'],
        ];

        foreach ($expected as $provider => [$url, $scope]) {
            // Like the browser: a page visit from the frontend, Referer only.
            $location = $this->withServerVariables(['HTTP_REFERER' => self::FRONTEND.'/login'])
                ->get("/auth/$provider/redirect")->assertRedirect()->headers->get('Location');
            parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

            $this->assertStringStartsWith($url.'?', $location);
            $this->assertSame("$provider-client-id", $query['client_id']);
            $this->assertSame("http://localhost:8000/auth/$provider/callback", $query['redirect_uri']);
            $this->assertSame($scope, $query['scope']);
            $this->assertSame('S256', $query['code_challenge_method']);
            $this->assertNotEmpty($query['code_challenge']);
            // The session started without an Origin from the frontend, and holds the state.
            $this->assertSame($query['state'], session('state'));
            $this->assertNotEmpty(session('code_verifier'));
        }
        $this->assertSame([], $this->requests);
    }

    public function test_the_callback_has_a_session_although_the_referer_is_the_provider(): void
    {
        // The web group starts the session for any caller: the state is found and checked, the
        // code swapped for a token, and the user logged in by a session cookie.
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->returnFrom('google', $this->google());

        $response->assertRedirect(self::FRONTEND.'/');
        $response->assertCookie(config('session.cookie'));
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertCount(2, $this->requests);
    }

    public function test_a_wrong_or_missing_state_fails_without_calling_the_provider(): void
    {
        $this->assertBackOnLogin($this->returnFrom('google', $this->google(), 'state=other-state&code=test-code'), 'social_failed');
        $this->assertBackOnLogin($this->returnFrom('github', $this->github(), 'code=test-code'), 'social_failed');

        // No state in the session at all (the redirect never happened in this browser).
        $this->provider('google', $this->google());
        $this->assertBackOnLogin($this->get('/auth/google/callback?state=x&code=test-code'), 'social_failed');

        $this->assertSame([], $this->requests);
        $this->assertDatabaseCount('users', 0);
        $this->assertNull(session('social_pending'));
        $this->assertSame(['provider' => 'google', 'error' => InvalidStateException::class], $this->logged[0]->context);
    }

    public function test_a_denied_access_or_provider_error_returns_to_login(): void
    {
        $this->assertBackOnLogin($this->returnFrom('google', [], 'error=access_denied&state='.self::STATE), 'social_cancelled');
        $this->assertBackOnLogin($this->returnFrom('github', [], 'error=access_denied&error_description=The+user+has+denied'), 'social_cancelled');
        $this->assertBackOnLogin($this->returnFrom('github', [], 'error=redirect_uri_mismatch'), 'social_failed');
        $this->assertBackOnLogin($this->returnFrom('google', [new Response(500, [], 'boom')]), 'social_failed');
        $this->assertBackOnLogin($this->returnFrom('google', [$this->google()[0], new Response(401, [], 'no')]), 'social_failed');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertNull(session('social_pending'));
    }

    public function test_a_new_email_is_kept_pending_in_the_session_and_creates_nothing(): void
    {
        $this->returnFrom('google', $this->google())->assertRedirect(self::FRONTEND.'/auth/complete');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertSame(0, UserActivityLog::count());
        $this->assertGuest('web');

        $this->getJson('/api/auth/pending')
            ->assertOk()
            ->assertExactJson(['name' => 'Ada Google', 'email' => 'ada@example.com', 'provider' => 'google']);
    }

    public function test_pending_is_404_without_a_pending_sign_in_or_without_a_session(): void
    {
        $this->getJson('/api/auth/pending')->assertNotFound()->assertJsonPath('message', __('messages.social_expired'));

        $this->returnFrom('google', $this->google());
        $this->withServerVariables(['HTTP_ORIGIN' => 'https://evil.example'])->getJson('/api/auth/pending')->assertNotFound();
    }

    public function test_a_pending_sign_in_expires_after_15_minutes(): void
    {
        $this->returnFrom('google', $this->google());

        $this->travel(14)->minutes();
        $this->getJson('/api/auth/pending')->assertOk();

        $this->travel(2)->minutes();
        $this->getJson('/api/auth/pending')->assertNotFound();
        $this->complete()->assertStatus(410);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_complete_without_a_pending_sign_in_is_refused(): void
    {
        $this->complete()->assertStatus(410)->assertExactJson(['message' => __('messages.social_expired')]);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest('web');
    }

    public static function refusedTerms(): array
    {
        return [
            'missing' => [[]], 'false' => [['accepted_terms' => false]], '0' => [['accepted_terms' => 0]],
            '"0"' => [['accepted_terms' => '0']], '"yes"' => [['accepted_terms' => 'yes']], '"on"' => [['accepted_terms' => 'on']],
            '"true"' => [['accepted_terms' => 'true']], 'null' => [['accepted_terms' => null]], 'array' => [['accepted_terms' => [true]]],
        ];
    }

    #[DataProvider('refusedTerms')]
    public function test_complete_needs_the_terms_exactly_like_register(array $terms): void
    {
        $this->returnFrom('google', $this->google());

        $this->postJson('/api/auth/complete', ['name' => 'Ada'] + $terms)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accepted_terms'])
            ->assertJsonMissingValidationErrors(['name']);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest('web');
        // Still pending: the user can tick the box and send again.
        $this->getJson('/api/auth/pending')->assertOk();
    }

    public function test_complete_validates_the_name_like_register(): void
    {
        $this->returnFrom('google', $this->google());

        $this->complete(['name' => ''])->assertJsonValidationErrors(['name']);
        $this->complete(['name' => str_repeat('a', 256)])->assertJsonValidationErrors(['name']);
        $this->complete(['name' => ['x']])->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_complete_creates_a_verified_user_without_a_password_and_logs_in(): void
    {
        $this->freezeSecond();
        config(['legal.terms_version' => '2030-01-02']);
        $this->returnFrom('github', $this->github());
        $before = $this->sessionId();

        $response = $this->complete(['name' => 'Ada Lovelace'])->assertCreated()
            ->assertJsonPath('user.name', 'Ada Lovelace')
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('user.points', 0)
            ->assertJsonPath('user.email_verified', true);
        foreach (['password', 'terms_accepted_at', 'terms_version', 'email_verified_at', 'social_accounts', 'provider_user_id'] as $hidden) {
            $response->assertJsonMissingPath("user.$hidden");
        }

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue($user->terms_accepted_at->equalTo(now()));
        $this->assertSame('2030-01-02', $user->terms_version);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'github', 'provider_user_id' => '42']);
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotSame($before, $this->sessionId());
        $this->assertSame(['register'], UserActivityLog::where('user_id', $user->id)->pluck('event')->all());

        $this->getJson('/api/me')->assertOk()->assertJsonPath('email', 'ada@example.com')
            ->assertJsonMissingPath('password')->assertJsonMissingPath('social_accounts');
    }

    public function test_a_pending_sign_in_works_once(): void
    {
        $this->returnFrom('google', $this->google());
        $this->complete()->assertCreated();

        $this->complete(['name' => 'Again'])->assertStatus(410);
        $this->getJson('/api/auth/pending')->assertNotFound();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_complete_ignores_role_points_email_and_provider_in_the_body(): void
    {
        $this->returnFrom('google', $this->google());

        $this->complete([
            'role' => 'owner', 'points' => 1000, 'email' => 'other@example.com', 'email_verified_at' => null,
            'provider' => 'github', 'provider_user_id' => '1', 'password' => 'chosen-password',
        ])->assertCreated()->assertJsonPath('user.role', 'user');

        $user = User::sole();
        $this->assertSame(['user', 0, 'ada@example.com', null], [$user->role, $user->points, $user->email, $user->password]);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame([['google', 'g-1']], SocialAccount::all()->map(fn ($a) => [$a->provider, $a->provider_user_id])->all());
    }

    public function test_complete_refuses_when_the_email_was_registered_meanwhile(): void
    {
        $this->returnFrom('google', $this->google());
        // Another tab, or someone else, registered the address before the Terms were accepted.
        User::factory()->unverified()->create(['email' => 'ada@example.com']);

        $this->complete()->assertStatus(410);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertGuest('web');
        $this->getJson('/api/auth/pending')->assertNotFound();
    }

    public function test_complete_refuses_when_the_provider_account_was_linked_meanwhile(): void
    {
        // Two tabs both reached /auth/complete; the first one finished.
        $this->returnFrom('google', $this->google());
        $pending = session('social_pending');
        $this->complete()->assertCreated();
        $this->withSession(['social_pending' => ['email' => 'second@example.com'] + $pending]);

        $this->complete()->assertStatus(410);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 1);
    }

    public function test_a_linked_account_logs_in_even_if_the_provider_email_changed(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'g-1']);
        $this->withSession(['social_pending' => ['stale' => true]]);
        $before = $this->sessionId();

        $this->returnFrom('google', $this->google(['email' => 'new-address@example.com']))->assertRedirect(self::FRONTEND.'/');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotSame($before, $this->sessionId());
        $this->assertNull(session('social_pending'));
        $this->assertSame(['login'], UserActivityLog::where('user_id', $user->id)->pluck('event')->all());
        $this->assertDatabaseCount('social_accounts', 1);
        $this->assertSame('ada@example.com', $user->fresh()->email);
    }

    public function test_a_verified_account_with_the_same_email_is_linked_then_logged_in(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);

        $this->returnFrom('github', $this->github())->assertRedirect(self::FRONTEND.'/');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'github', 'provider_user_id' => '42']);
        // The password still works, nothing else changed.
        $this->assertTrue(Hash::check('secret-password', $user->fresh()->password));
        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_an_unverified_account_with_the_same_email_is_never_linked(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'ada@example.com']);

        $this->assertBackOnLogin($this->returnFrom('google', $this->google()), 'social_email_exists');

        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertNull(session('social_pending'));
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public static function unverifiedGoogle(): array
    {
        return [
            'false' => [['email_verified' => false]], 'string "true"' => [['email_verified' => 'true']],
            'missing' => [['email_verified' => null]], 'no email' => [['email' => null]], 'not an email' => [['email' => 'ada']],
        ];
    }

    #[DataProvider('unverifiedGoogle')]
    public function test_google_without_a_verified_email_is_refused(array $profile): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->assertBackOnLogin($this->returnFrom('google', $this->google($profile)), 'social_email_unverified');

        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertNull(session('social_pending'));
    }

    public static function unverifiedGithub(): array
    {
        return [
            'primary not verified' => [[['email' => 'ada@example.com', 'primary' => true, 'verified' => false],
                ['email' => 'other@example.com', 'primary' => false, 'verified' => true]]],
            'no emails' => [[]],
        ];
    }

    #[DataProvider('unverifiedGithub')]
    public function test_github_without_a_verified_primary_email_is_refused(array $emails): void
    {
        User::factory()->create(['email' => 'other@example.com']);

        // The public profile email is never used.
        $this->assertBackOnLogin($this->returnFrom('github', $this->github($emails, ['email' => 'other@example.com'])), 'social_email_unverified');

        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertNull(session('social_pending'));
    }

    public function test_github_with_an_unreadable_email_list_is_refused(): void
    {
        $responses = $this->github();
        $responses[2] = new Response(403, [], '{"message":"Resource not accessible"}');

        $this->assertBackOnLogin($this->returnFrom('github', $responses), 'social_email_unverified');
    }

    public function test_a_banned_user_is_blocked_with_a_link_or_by_email(): void
    {
        $linked = User::factory()->create(['email' => 'ada@example.com', 'banned_at' => now(), 'ban_reason' => 'Spam']);
        SocialAccount::create(['user_id' => $linked->id, 'provider' => 'google', 'provider_user_id' => 'g-1']);
        User::factory()->create(['email' => 'grace@example.com', 'banned_at' => now(), 'ban_reason' => 'Spam']);

        $this->assertBackOnLogin($this->returnFrom('google', $this->google()), 'social_banned');
        $this->assertBackOnLogin($this->returnFrom('github', $this->github([['email' => 'grace@example.com', 'primary' => true, 'verified' => true]])), 'social_banned');

        $this->assertDatabaseCount('social_accounts', 1);
        $this->assertSame(2, UserActivityLog::where('event', 'login_blocked_banned')->count());
        $this->assertSame(0, UserActivityLog::where('event', 'login')->count());
    }

    public function test_an_admin_keeps_the_role_and_a_new_account_is_always_a_user(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->forceFill(['role' => 'admin'])->save();

        $this->returnFrom('google', $this->google(['email' => 'admin@example.com']))->assertRedirect(self::FRONTEND.'/');
        $this->assertSame('admin', $admin->fresh()->role);

        $this->post('/api/logout');
        $this->returnFrom('github', $this->github([['email' => 'new@example.com', 'primary' => true, 'verified' => true]]));
        $this->complete(['role' => 'admin'])->assertCreated();
        $this->assertSame('user', User::where('email', 'new@example.com')->value('role'));
    }

    public function test_a_second_provider_with_the_same_verified_email_links_to_the_same_user(): void
    {
        $this->returnFrom('google', $this->google());
        $this->complete()->assertCreated();
        $this->post('/api/logout');

        $this->returnFrom('github', $this->github())->assertRedirect(self::FRONTEND.'/');

        $user = User::sole();
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertEqualsCanonicalizing(['google', 'github'], SocialAccount::where('user_id', $user->id)->pluck('provider')->all());
    }

    public function test_a_second_account_of_the_same_provider_is_not_linked(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'github', 'provider_user_id' => '7']);

        $this->assertBackOnLogin($this->returnFrom('github', $this->github()), 'social_failed');
        $this->assertDatabaseCount('social_accounts', 1);
    }

    public function test_provider_emails_are_lower_cased(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->returnFrom('google', $this->google(['email' => 'Ada@Example.COM']))->assertRedirect(self::FRONTEND.'/');
        $this->assertAuthenticatedAs($user, 'web');

        $this->post('/api/logout');
        $this->returnFrom('github', $this->github([['email' => 'Grace@Example.com', 'primary' => true, 'verified' => true]]));
        $this->getJson('/api/auth/pending')->assertJsonPath('email', 'grace@example.com');
    }

    public function test_the_github_login_is_the_name_when_there_is_no_name(): void
    {
        $this->returnFrom('github', $this->github(null, ['name' => null]));

        $this->getJson('/api/auth/pending')->assertJsonPath('name', 'ada-gh');
    }

    public function test_a_social_only_account_cannot_log_in_with_a_password(): void
    {
        User::factory()->create(['email' => 'grace@example.com', 'password' => 'secret-password']);
        $this->returnFrom('google', $this->google());
        $this->complete()->assertCreated();
        $this->post('/api/logout');

        $social = $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'any-password']);
        $wrong = $this->postJson('/api/login', ['email' => 'grace@example.com', 'password' => 'any-password']);

        $social->assertUnauthorized();
        $this->assertSame($wrong->getContent(), $social->getContent());
        $this->assertSame($wrong->getStatusCode(), $social->getStatusCode());
        $this->assertGuest('web');
        $this->assertSame(2, UserActivityLog::where('event', 'login_failed')->count());
    }

    public function test_a_social_only_account_can_set_a_password_by_reset_and_stays_verified(): void
    {
        Notification::fake();
        $this->returnFrom('google', $this->google());
        $this->complete()->assertCreated();
        $this->post('/api/logout');
        $user = User::sole();

        $this->postJson('/api/forgot-password', ['email' => 'ada@example.com'])->assertStatus(202);
        Notification::assertSentTo($user, ResetPassword::class);

        $this->postJson('/api/reset-password', [
            'token' => Password::createToken($user), 'email' => 'ada@example.com',
            'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'new-password'])->assertOk();
    }

    public function test_logs_hold_no_email_token_or_provider_answer(): void
    {
        $this->returnFrom('google', [$this->google()[0], new Response(500, [], 'ada@example.com '.self::TOKEN)]);
        $this->returnFrom('google', $this->google(['email_verified' => false]));
        $this->returnFrom('github', $this->github(), 'state=wrong&code=test-code');
        $this->returnFrom('github', [], 'error=access_denied');

        $this->assertCount(4, $this->logged);
        foreach ($this->logged as $entry) {
            $text = $entry->message.json_encode($entry->context);
            $this->assertSame('Social login failed', $entry->message);
            $this->assertSame(['provider', 'error'], array_keys($entry->context));
            foreach (['ada@example.com', self::TOKEN, 'test-code', self::STATE, 'secret'] as $secret) {
                $this->assertStringNotContainsString($secret, $text);
            }
        }
    }

    public function test_the_redirect_and_callback_are_limited_per_ip(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get('/auth/google/redirect')->assertRedirect();
        }

        $this->get('/auth/github/callback?error=access_denied')->assertTooManyRequests();
    }

    public function test_complete_shares_the_register_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->complete()->assertStatus(410);
        }

        $this->complete()->assertTooManyRequests();
    }

    public function test_password_users_without_a_link_are_unaffected(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password'])->assertOk()
            ->assertJsonMissingPath('user.social_accounts');
        $this->getJson('/api/me')->assertOk()->assertJsonMissingPath('social_accounts')->assertJsonMissingPath('password');
        $this->assertDatabaseCount('social_accounts', 0);
    }
}
