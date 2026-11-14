<?php

namespace Tests\Feature;

use App\Models\BugReport;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private const SECRET = '1x0000000000000000000000000000000AA';

    private const TOKEN = 'XXXX.DUMMY.TOKEN.for-the-test';

    private const MESSAGE = 'Captcha check failed, please try again.';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.turnstile.enabled' => true, 'services.turnstile.secret' => self::SECRET]);
        Storage::fake('local');
        Http::preventStrayRequests();
    }

    private function cloudflare(mixed $answer = null): void
    {
        Http::fake([self::URL => $answer ?? Http::response(['success' => true])]);
    }

    private function login(array $extra = [], array $headers = [])
    {
        User::query()->firstOrCreate(['email' => 'ada@example.com'], ['name' => 'Ada', 'password' => 'secret-password']);

        return $this->postJson('/api/login', $extra + ['email' => 'ada@example.com', 'password' => 'secret-password'], $headers);
    }

    private function bugReport(array $extra = [])
    {
        return $this->post('/api/bug-reports', $extra + [
            'description' => 'The submit button does nothing.',
            'page_path' => '/challenges/binary-search',
            'included' => json_encode(['account' => false, 'diagnostics' => false, 'screenshot' => false, 'problem' => false]),
        ], ['Accept' => 'application/json']);
    }

    private function assertCaptchaRefused($response, string $message = self::MESSAGE): void
    {
        $response->assertUnprocessable();
        $this->assertSame(['message' => $message, 'errors' => ['turnstile_token' => [$message]]], $response->json());
        $this->assertGuest('web');
    }

    public function test_a_missing_token_is_refused_without_calling_cloudflare_or_checking_the_password(): void
    {
        $this->assertCaptchaRefused($this->login());

        Http::assertNothingSent();
        $this->assertSame(['captcha_failed'], SecurityEvent::pluck('type')->all());
    }

    public function test_an_empty_or_non_string_token_is_refused(): void
    {
        foreach (['', ['a'], 123, null] as $token) {
            $this->assertCaptchaRefused($this->login(['turnstile_token' => $token]));
        }

        Http::assertNothingSent();
    }

    public function test_a_token_over_2048_characters_is_refused_and_2048_is_sent_to_cloudflare(): void
    {
        $this->cloudflare();

        $this->assertCaptchaRefused($this->login(['turnstile_token' => str_repeat('a', 2049)]));
        Http::assertNothingSent();

        $this->login(['turnstile_token' => str_repeat('a', 2048)])->assertOk();
        Http::assertSentCount(1);
    }

    public function test_an_invalid_token_is_refused_as_captcha_failed(): void
    {
        $this->cloudflare(Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]));

        $this->assertCaptchaRefused($this->login(['turnstile_token' => self::TOKEN]));
        $this->assertSame(['captcha_failed'], SecurityEvent::pluck('type')->all());
    }

    public function test_a_valid_token_logs_in_and_cloudflare_gets_secret_response_and_ip(): void
    {
        $this->cloudflare();

        $this->login(['turnstile_token' => self::TOKEN])->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            parse_str($request->body(), $form);

            return $request->url() === self::URL
                && $request->method() === 'POST'
                && $form === ['secret' => self::SECRET, 'response' => self::TOKEN, 'remoteip' => '127.0.0.1'];
        });
        $this->assertSame(0, SecurityEvent::count());
    }

    public function test_a_cloudflare_timeout_is_refused_as_captcha_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->assertCaptchaRefused($this->login(['turnstile_token' => self::TOKEN]));
        $this->assertSame(['captcha_unavailable'], SecurityEvent::pluck('type')->all());
    }

    public function test_a_cloudflare_500_is_refused_as_captcha_unavailable(): void
    {
        $this->cloudflare(Http::response(['success' => true], 500));

        $this->assertCaptchaRefused($this->login(['turnstile_token' => self::TOKEN]));
        $this->assertSame(['captcha_unavailable'], SecurityEvent::pluck('type')->all());
    }

    public function test_a_malformed_or_incomplete_cloudflare_body_is_refused_as_captcha_unavailable(): void
    {
        foreach ([Http::response('not json', 200), Http::response(['success' => 'true'], 200), Http::response([], 200)] as $answer) {
            SecurityEvent::query()->delete();
            $this->cloudflare($answer);

            $this->assertCaptchaRefused($this->login(['turnstile_token' => self::TOKEN]));
            $this->assertSame(['captcha_unavailable'], SecurityEvent::pluck('type')->all());
        }
    }

    public function test_when_disabled_login_needs_no_token_and_nothing_is_sent(): void
    {
        config(['services.turnstile.enabled' => false]);

        $this->login()->assertOk();

        Http::assertNothingSent();
    }

    public function test_the_security_event_has_ip_path_and_route_but_never_the_token_or_the_secret(): void
    {
        $this->cloudflare(Http::response(['success' => false]));
        $this->login(['turnstile_token' => self::TOKEN]);
        $this->login(['turnstile_token' => str_repeat('z', 2049)]);

        $event = SecurityEvent::sole();
        $this->assertSame(['captcha_failed', '127.0.0.1', 'POST', '/api/login'], [$event->type, $event->ip, $event->method, $event->path]);
        $this->assertSame('api/login', $event->detail['route']);

        $dump = json_encode(DB::table('security_events')->get());
        $this->assertStringNotContainsString(self::TOKEN, $dump);
        $this->assertStringNotContainsString(self::SECRET, $dump);
        $this->assertStringNotContainsString('zzzzzzzz', $dump);
    }

    public function test_failed_captchas_count_toward_the_login_limit(): void
    {
        $this->cloudflare();

        for ($i = 0; $i < 5; $i++) {
            $this->assertCaptchaRefused($this->login());
        }

        $this->login(['turnstile_token' => self::TOKEN])->assertTooManyRequests();
        Http::assertNothingSent();
    }

    public function test_failed_captchas_count_toward_the_register_limit(): void
    {
        $this->cloudflare();
        $fields = ['name' => 'Ada', 'password' => 'secret-password', 'accepted_terms' => true];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/register', $fields + ['email' => "u$i@example.com"])->assertUnprocessable();
        }

        $this->postJson('/api/register', $fields + ['email' => 'u6@example.com', 'turnstile_token' => self::TOKEN])->assertTooManyRequests();
        $this->assertSame(0, User::count());
    }

    public function test_a_logged_in_user_sends_a_bug_report_without_a_token(): void
    {
        $this->actingAs(User::factory()->create());

        $this->bugReport()->assertCreated();

        Http::assertNothingSent();
        $this->assertSame(1, BugReport::count());
    }

    public function test_a_logged_in_session_does_not_skip_the_captcha_on_login_register_or_forgot(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertCaptchaRefusedForSession($this->login());
        $this->assertCaptchaRefusedForSession($this->postJson('/api/register', ['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'secret-password', 'accepted_terms' => true]));
        $this->assertCaptchaRefusedForSession($this->postJson('/api/forgot-password', ['email' => 'ada@example.com']));

        Http::assertNothingSent();
        $this->assertSame(0, User::where('email', 'eve@example.com')->count());
    }

    private function assertCaptchaRefusedForSession($response): void
    {
        $response->assertUnprocessable()->assertJsonPath('errors.turnstile_token.0', self::MESSAGE);
    }

    public function test_a_guest_bug_report_needs_a_valid_token(): void
    {
        $this->cloudflare();

        $this->assertCaptchaRefused($this->bugReport());
        $this->assertSame(0, BugReport::count());
        Http::assertNothingSent();

        $this->bugReport(['turnstile_token' => self::TOKEN])->assertCreated();
        $this->assertSame(1, BugReport::count());
        Http::assertSentCount(1);
    }

    public function test_register_needs_a_token_and_creates_no_account_without_one(): void
    {
        $this->cloudflare();
        $fields = ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret-password', 'accepted_terms' => true];

        $this->assertCaptchaRefused($this->postJson('/api/register', $fields));
        $this->assertSame(0, User::count());

        $this->postJson('/api/register', $fields + ['turnstile_token' => self::TOKEN])->assertCreated();
        $this->assertSame(1, User::count());
    }

    public function test_forgot_password_needs_a_token_and_sends_no_email_without_one(): void
    {
        Notification::fake();
        $this->cloudflare();
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->assertCaptchaRefused($this->postJson('/api/forgot-password', ['email' => 'ada@example.com']));
        Notification::assertNothingSent();

        $this->postJson('/api/forgot-password', ['email' => 'ada@example.com', 'turnstile_token' => self::TOKEN])->assertStatus(202);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_a_filled_honeypot_skips_the_captcha_and_gets_the_decoy_answer(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'ada@example.com', SecurityEvent::HONEYPOT_FIELD => 'https://spam.example'])
            ->assertStatus(202);

        Http::assertNothingSent();
        Notification::assertNotSentTo($user, ResetPassword::class);
        $this->assertSame(['honeypot_filled'], SecurityEvent::pluck('type')->all());
    }

    public function test_the_message_is_arabic_for_an_arabic_request(): void
    {
        $message = trans('messages.captcha_failed', [], 'ar');
        $this->assertNotSame(self::MESSAGE, $message);

        $this->assertCaptchaRefused($this->login([], ['Accept-Language' => 'ar']), $message);
    }
}
