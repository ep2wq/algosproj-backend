<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Tests\TestCase;

// Forgot and reset password (README "Email"): no account enumeration, hashed single-use tokens
// that expire after 60 minutes, every session ended, no login.
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'old-password']);
    }

    private function reset(array $extra = [])
    {
        // A new token only when none is given: creating one replaces the stored token.
        return $this->postJson('/api/reset-password', array_merge([
            'token' => array_key_exists('token', $extra) ? null : Password::createToken($this->user),
            'email' => 'ada@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ], $extra));
    }

    private function passwordIs(string $password): bool
    {
        return Hash::check($password, $this->user->fresh()->password);
    }

    public function test_forgot_password_answers_the_same_for_known_and_unknown_emails(): void
    {
        Notification::fake();

        $known = $this->postJson('/api/forgot-password', ['email' => 'ada@example.com']);
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertStatus(202)->assertExactJson(['message' => __('messages.reset_link_sent')]);
        $unknown->assertStatus(202);
        $this->assertSame($known->getContent(), $unknown->getContent());
        Notification::assertSentToTimes($this->user, ResetPassword::class, 1);
        Notification::assertCount(1);
    }

    public function test_forgot_password_sends_a_link_to_the_frontend_form(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', ['email' => 'ada@example.com'])->assertStatus(202);

        Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification) {
            $url = $notification->toMail($this->user)->viewData['url'];
            $this->assertSame(self::FRONTEND.'/reset-password?token='.$notification->token.'&email=ada%40example.com', $url);

            return true;
        });
        // The token is stored hashed, never as sent.
        $stored = DB::table('password_reset_tokens')->where('email', 'ada@example.com')->value('token');
        $this->assertNotNull($stored);
        Notification::assertSentTo($this->user, ResetPassword::class, fn (ResetPassword $n) => $n->token !== $stored && Hash::check($n->token, $stored));
    }

    public function test_forgot_password_validates_the_email_the_same_way_for_everyone(): void
    {
        $this->postJson('/api/forgot-password', [])->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->postJson('/api/forgot-password', ['email' => 'not-an-email'])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_refuses_a_non_string_email_without_an_error(): void
    {
        $this->postJson('/api/forgot-password', ['email' => ['ada@example.com']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_is_limited_to_three_an_hour_per_email_and_ip(): void
    {
        Notification::fake();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/forgot-password', ['email' => 'ada@example.com'])->assertStatus(202);
        }
        $this->postJson('/api/forgot-password', ['email' => 'ADA@example.com'])->assertTooManyRequests();
        // Another email from the same IP still works.
        $this->postJson('/api/forgot-password', ['email' => 'bob@example.com'])->assertStatus(202);
    }

    public function test_forgot_password_is_limited_to_ten_an_hour_per_ip(): void
    {
        Notification::fake();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/forgot-password', ['email' => "user$i@example.com"])->assertStatus(202);
        }
        $this->postJson('/api/forgot-password', ['email' => 'ada@example.com'])->assertTooManyRequests();
        Notification::assertNothingSent();

        $this->travel(61)->minutes();
        $this->postJson('/api/forgot-password', ['email' => 'ada@example.com'])->assertStatus(202);
    }

    public function test_forgot_password_answers_in_arabic(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', ['email' => 'ada@example.com'], ['Accept-Language' => 'ar'])
            ->assertStatus(202)
            ->assertExactJson(['message' => 'إذا كان هناك حساب بهذا البريد الإلكتروني، فقد أرسلنا إليه رابطًا لإعادة تعيين كلمة المرور.']);

        Notification::assertSentTo($this->user, ResetPassword::class, fn (ResetPassword $n) => $n->locale === 'ar');
    }

    public function test_the_reset_emails_are_english_and_arabic_right_to_left(): void
    {
        $this->postJson('/api/forgot-password', ['email' => 'ada@example.com'])->assertStatus(202);
        $this->travel(2)->minutes();
        $this->postJson('/api/forgot-password', ['email' => 'ada@example.com'], ['Accept-Language' => 'ar'])->assertStatus(202);

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(2, $messages);
        $english = $messages[0]->getOriginalMessage();
        $arabic = $messages[1]->getOriginalMessage();
        $this->assertSame('Reset your AlgosLab password', $english->getSubject());
        $this->assertStringContainsString('dir="ltr"', $english->getHtmlBody());
        $this->assertStringContainsString("If you didn't ask to reset your password", $english->getTextBody());
        $this->assertSame('إعادة تعيين كلمة المرور في AlgosLab', $arabic->getSubject());
        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $arabic->getHtmlBody());
        $this->assertStringContainsString('ستبقى كلمة المرور كما هي', $arabic->getTextBody());
        $this->assertStringContainsString(self::FRONTEND.'/reset-password?token=', $arabic->getTextBody());
    }

    public function test_a_mail_failure_still_answers_202_and_logs_no_address_or_token(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            $logged[] = $event->message.' '.json_encode($event->context);
        });
        ResetPassword::toMailUsing(function ($user, $token) {
            throw new RuntimeException("550 ada@example.com rejected token=$token");
        });

        $this->postJson('/api/forgot-password', ['email' => 'ada@example.com'])->assertStatus(202);

        $this->assertCount(1, $logged);
        $this->assertStringContainsString('Mail could not be sent', $logged[0]);
        $this->assertStringNotContainsString('ada@', $logged[0]);
        $this->assertStringNotContainsString('token', $logged[0]);
    }

    public function test_a_valid_token_sets_the_new_password_once_and_does_not_log_in(): void
    {
        $token = Password::createToken($this->user);

        $this->reset(['token' => $token])
            ->assertOk()
            ->assertExactJson(['message' => __('messages.password_reset')]);

        $this->assertTrue($this->passwordIs('new-password'));
        $this->assertGuest('web');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'ada@example.com']);

        // The same token a second time.
        $this->reset(['token' => $token, 'password' => 'third-password', 'password_confirmation' => 'third-password'])
            ->assertUnprocessable()
            ->assertExactJson(['message' => __('messages.reset_invalid')]);
        $this->assertTrue($this->passwordIs('new-password'));
    }

    public function test_an_expired_token_is_refused(): void
    {
        $token = Password::createToken($this->user);
        $this->travel(61)->minutes();

        $this->reset(['token' => $token])->assertUnprocessable()->assertExactJson(['message' => __('messages.reset_invalid')]);

        $this->assertTrue($this->passwordIs('old-password'));
    }

    public function test_a_wrong_token_or_email_gets_the_same_generic_error(): void
    {
        $other = User::factory()->create(['email' => 'bob@example.com', 'password' => 'bob-password']);
        Password::createToken($this->user);

        $wrongToken = $this->reset(['token' => str_repeat('a', 64)]);
        $wrongEmail = $this->reset(['email' => 'bob@example.com']);
        $unknownEmail = $this->reset(['email' => 'nobody@example.com']);

        foreach ([$wrongToken, $wrongEmail, $unknownEmail] as $response) {
            $response->assertUnprocessable()->assertExactJson(['message' => __('messages.reset_invalid')]);
        }
        $this->assertTrue($this->passwordIs('old-password'));
        $this->assertTrue(Hash::check('bob-password', $other->fresh()->password));
    }

    public function test_the_password_rules_match_register_and_need_the_confirmation(): void
    {
        $this->reset(['password' => 'short', 'password_confirmation' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors(['password']);
        $this->reset(['password_confirmation' => 'different-password'])
            ->assertUnprocessable()->assertJsonValidationErrors(['password']);
        $this->reset(['token' => ''])->assertUnprocessable()->assertJsonValidationErrors(['token']);
        $this->reset(['email' => 'not-an-email'])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->assertTrue($this->passwordIs('old-password'));
    }

    public function test_reset_errors_are_arabic(): void
    {
        $this->postJson('/api/reset-password', ['token' => 'x', 'email' => 'ada@example.com', 'password' => 'new-password', 'password_confirmation' => 'other-password'], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'تأكيد كلمة المرور غير مطابق.');
        $this->postJson('/api/reset-password', ['token' => 'x', 'email' => 'ada@example.com', 'password' => 'new-password', 'password_confirmation' => 'new-password'], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'رابط إعادة التعيين غير صالح أو انتهت صلاحيته. يمكنك طلب رابط جديد.']);
    }

    public function test_reset_is_limited_to_five_an_hour_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->reset(['token' => str_repeat('a', 64)])->assertUnprocessable();
        }
        $this->reset()->assertTooManyRequests();
        $this->assertTrue($this->passwordIs('old-password'));
    }

    public function test_a_banned_user_can_reset_and_stays_banned(): void
    {
        $this->user->forceFill(['banned_at' => now(), 'ban_reason' => 'spam'])->save();

        $this->reset()->assertOk();

        $this->assertTrue($this->passwordIs('new-password'));
        $this->assertNotNull($this->user->fresh()->banned_at);
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'new-password'])
            ->assertForbidden()
            ->assertJsonPath('reason', 'spam');
    }

    public function test_reset_ends_every_session_and_remember_cookie_and_logs_nobody_in(): void
    {
        config(['session.driver' => 'database']);
        $cookie = config('session.cookie');
        $login = fn () => $this->fresh()->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'old-password', 'remember' => true])->assertOk();

        $first = $login();
        $second = $login();
        $recaller = Auth::guard('web')->getRecallerName();
        $remember = [$recaller => $second->getCookie($recaller)->getValue()];
        $this->assertSame(2, DB::table('sessions')->where('user_id', $this->user->id)->count());

        // Reset from the first browser, which is still logged in.
        $response = $this->fresh([$cookie => $first->getCookie($cookie)->getValue()])->reset()->assertOk();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->user->id)->count());
        $this->fresh([$cookie => $first->getCookie($cookie)->getValue()])->getJson('/api/me')->assertUnauthorized();
        $this->fresh([$cookie => $second->getCookie($cookie)->getValue()])->getJson('/api/me')->assertUnauthorized();
        $this->fresh($remember)->getJson('/api/me')->assertUnauthorized();
        $this->fresh([$cookie => $response->getCookie($cookie)->getValue()])->getJson('/api/me')->assertUnauthorized();
    }

    // A new browser (the pattern of SessionTest::fresh).
    private function fresh(array $cookies = []): static
    {
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('auth.driver');
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->defaultCookies = [];
        $this->flushHeaders();

        return $this->withCredentials()->withCookies($cookies);
    }
}
