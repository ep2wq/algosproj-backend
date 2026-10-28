<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Problem;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\TestCase;

// Email verification (README "Email"): the signed link, resending, the email itself, and the gate:
// an unverified user reads everything but cannot submit code.
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const SUCCESS = self::FRONTEND.'/verify-email?status=success';

    private const INVALID = self::FRONTEND.'/verify-email?status=invalid';

    private function form(array $extra = []): array
    {
        return array_merge(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret-password', 'accepted_terms' => true], $extra);
    }

    private function link(User $user, ?string $hash = null, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
            'id' => $user->id,
            'hash' => $hash ?? sha1($user->email),
        ]);
    }

    private function problem(): Problem
    {
        $module = Module::create(['slug' => 'algorithms', 'title' => 'Algorithms', 'track' => 'algorithms', 'sort_order' => 1]);
        $lesson = $module->lessons()->create(['slug' => 'warm-up', 'title' => 'Warm-up', 'sort_order' => 1]);
        $problem = $lesson->problems()->create(['slug' => 'warm-up', 'title' => 'Double', 'description' => 'Double it', 'difficulty' => 'easy', 'points' => 10]);
        $problem->testCases()->create(['input' => '1', 'expected_output' => '2', 'is_hidden' => false]);

        return $problem;
    }

    public function test_register_creates_an_unverified_user_and_sends_the_verification_email(): void
    {
        Notification::fake();

        $this->postJson('/api/register', $this->form())
            ->assertCreated()
            ->assertJsonPath('user.email_verified', false)
            ->assertJsonMissingPath('user.email_verified_at');

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_the_link_in_the_email_verifies_the_account(): void
    {
        Notification::fake();
        $this->postJson('/api/register', $this->form())->assertCreated();
        $user = User::where('email', 'ada@example.com')->firstOrFail();

        $url = null;
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user, &$url) {
            $url = $notification->toMail($user)->viewData['url'];

            return true;
        });
        $this->assertStringStartsWith(config('app.url').'/api/email/verify/'.$user->id.'/'.sha1('ada@example.com').'?expires=', $url);

        $this->app['auth']->forgetGuards();
        $this->get($url)->assertRedirect(self::SUCCESS);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_valid_link_verifies_without_being_logged_in(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->link($user))->assertRedirect(self::SUCCESS);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertGuest('web');
    }

    public function test_an_expired_link_verifies_nobody(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->link($user);

        $this->travel(61)->minutes();
        $this->get($url)->assertRedirect(self::INVALID);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_tampered_signature_verifies_nobody(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->link($user).'0')->assertRedirect(self::INVALID);
        $this->get(preg_replace('/expires=\d+/', 'expires='.now()->addYear()->getTimestamp(), $this->link($user)))
            ->assertRedirect(self::INVALID);
        $this->get(preg_replace('/&signature=.*/', '', $this->link($user)))->assertRedirect(self::INVALID);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_wrong_hash_verifies_nobody_even_when_signed(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->link($user, sha1('someone-else@example.com')))->assertRedirect(self::INVALID);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_another_users_id_verifies_nobody(): void
    {
        $alice = User::factory()->unverified()->create();
        $bob = User::factory()->unverified()->create();

        // Alice's signed link with Bob's id swapped in, and a signed link for Bob with Alice's hash.
        $this->get(str_replace($alice->id, $bob->id, $this->link($alice)))->assertRedirect(self::INVALID);
        $this->get($this->link($bob, sha1($alice->email)))->assertRedirect(self::INVALID);

        $this->assertFalse($alice->fresh()->hasVerifiedEmail());
        $this->assertFalse($bob->fresh()->hasVerifiedEmail());
    }

    public function test_a_link_for_an_old_email_verifies_nobody_after_the_email_changed(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'old@example.com']);
        $url = $this->link($user);
        $user->forceFill(['email' => 'new@example.com'])->save();

        $this->get($url)->assertRedirect(self::INVALID);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_non_uuid_id_is_not_found(): void
    {
        $this->getJson('/api/email/verify/123/'.sha1('a@example.com'))->assertNotFound();
        $this->getJson('/api/email/verify/not-a-uuid/'.sha1('a@example.com'))->assertNotFound();
    }

    public function test_an_unknown_uuid_is_invalid(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->link($user);
        $user->delete();

        $this->get($url)->assertRedirect(self::INVALID);
    }

    public function test_opening_the_link_again_keeps_the_first_verification(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->link($user);

        $this->get($url)->assertRedirect(self::SUCCESS);
        $first = $user->fresh()->email_verified_at;

        $this->travel(5)->minutes();
        $this->get($url)->assertRedirect(self::SUCCESS);
        $this->assertTrue($user->fresh()->email_verified_at->equalTo($first));
    }

    public function test_resend_sends_a_new_email_to_an_unverified_user(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->postJson('/api/email/verification-notification')
            ->assertStatus(202)
            ->assertJsonPath('message', __('messages.verification_sent'));

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_resend_to_a_verified_user_sends_nothing(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', __('messages.already_verified'));

        Notification::assertNothingSent();
    }

    public function test_resend_needs_a_login(): void
    {
        $this->postJson('/api/email/verification-notification')->assertUnauthorized();
    }

    public function test_resend_is_limited_to_three_an_hour_per_user(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)->postJson('/api/email/verification-notification')->assertStatus(202);
        }
        $this->actingAs($user)->postJson('/api/email/verification-notification')->assertTooManyRequests();
        Notification::assertSentToTimes($user, VerifyEmail::class, 3);

        $this->travel(61)->minutes();
        $this->actingAs($user)->postJson('/api/email/verification-notification')->assertStatus(202);
    }

    public function test_resend_is_limited_to_ten_an_hour_per_ip(): void
    {
        Notification::fake();
        $users = User::factory()->unverified()->count(4)->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($users[intdiv($i, 3)])->postJson('/api/email/verification-notification')->assertStatus(202);
        }
        // The fourth user has used only one of their own three, but the IP has used ten.
        $this->actingAs($users[3])->postJson('/api/email/verification-notification')->assertTooManyRequests();
    }

    public function test_resend_429_is_arabic_like_the_other_limits(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)->postJson('/api/email/verification-notification');
        }
        $this->actingAs($user)->postJson('/api/email/verification-notification', [], ['Accept-Language' => 'ar'])
            ->assertTooManyRequests()
            ->assertExactJson(['message' => __('messages.too_many_attempts', [], 'ar')]);
    }

    public function test_registration_succeeds_when_the_email_cannot_be_sent_and_logs_no_address_or_link(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            $logged[] = $event->message.' '.json_encode($event->context);
        });
        VerifyEmail::toMailUsing(function () {
            throw new RuntimeException('550 mailbox ada@example.com rejected, link /api/email/verify/');
        });

        $this->postJson('/api/register', $this->form())->assertCreated()->assertJsonPath('user.email_verified', false);

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
        $this->assertAuthenticated('web');
        $this->assertSame(1, count($logged));
        $this->assertStringContainsString('Mail could not be sent', $logged[0]);
        $this->assertStringNotContainsString('ada@example.com', $logged[0]);
        $this->assertStringNotContainsString('verify', $logged[0]);
        $this->assertStringNotContainsString('signature', $logged[0]);
    }

    public function test_the_english_email_is_sent_in_the_request_language(): void
    {
        $this->postJson('/api/register', $this->form())->assertCreated();

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages[0]->getOriginalMessage();
        $this->assertSame('Verify your email for AlgosLab', $email->getSubject());
        $this->assertSame('ada@example.com', $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('dir="ltr"', $email->getHtmlBody());
        $this->assertStringContainsString('Verify email', $email->getHtmlBody());
        $this->assertStringContainsString("If you didn't create an AlgosLab account", $email->getTextBody());
        $this->assertStringContainsString('/api/email/verify/', $email->getTextBody());
        // Plain transactional mail: no images, no tracking pixel.
        $this->assertStringNotContainsString('<img', $email->getHtmlBody());
    }

    public function test_the_arabic_email_is_arabic_and_right_to_left(): void
    {
        $this->postJson('/api/register', $this->form(), ['Accept-Language' => 'ar'])->assertCreated();

        $email = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage();
        $this->assertSame('تأكيد بريدك الإلكتروني في AlgosLab', $email->getSubject());
        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $email->getHtmlBody());
        $this->assertStringContainsString('مرحبًا Ada،', $email->getHtmlBody());
        $this->assertStringContainsString('تأكيد البريد الإلكتروني', $email->getHtmlBody());
        $this->assertStringContainsString('يمكنك تجاهل هذه الرسالة', $email->getTextBody());
    }

    public function test_the_name_in_the_email_is_escaped(): void
    {
        $this->postJson('/api/register', $this->form(['name' => '<b>Ada</b>']))->assertCreated();

        $html = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage()->getHtmlBody();
        $this->assertStringNotContainsString('<b>Ada</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;Ada&lt;/b&gt;', $html);
    }

    public function test_me_shows_email_verified_false_for_an_unverified_user(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email_verified', false)
            ->assertJsonMissingPath('email_verified_at');
    }

    public function test_me_adds_only_the_email_verified_boolean(): void
    {
        $me = $this->actingAs(User::factory()->create())->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email_verified', true);

        $this->assertEqualsCanonicalizing(['id', 'name', 'email', 'points', 'role', 'created_at', 'updated_at', 'email_verified'], array_keys($me->json()));
    }

    public function test_login_shows_email_verified_as_a_boolean_only(): void
    {
        User::factory()->create(['email' => 'v@example.com', 'password' => 'secret-password']);

        $this->postJson('/api/login', ['email' => 'v@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('user.email_verified', true)
            ->assertJsonMissingPath('user.email_verified_at');
    }

    public function test_an_unverified_user_cannot_submit_code(): void
    {
        Http::fake();
        $problem = $this->problem();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->postJson("/api/problems/{$problem->id}/submit", ['language' => 'python', 'code' => 'print(2)'])
            ->assertForbidden()
            ->assertExactJson(['message' => __('messages.email_unverified'), 'reason' => 'email_unverified']);

        Http::assertNothingSent();
        $this->assertDatabaseCount('submissions', 0);
        $this->assertSame(0, $user->fresh()->points);
    }

    public function test_an_unverified_user_gets_403_for_any_problem_id(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->postJson('/api/problems/'.fake()->uuid().'/submit', ['language' => 'python', 'code' => 'x'])
            ->assertForbidden()
            ->assertJsonPath('reason', 'email_unverified');
    }

    public function test_the_unverified_message_is_arabic(): void
    {
        $problem = $this->problem();

        $this->actingAs(User::factory()->unverified()->create())
            ->postJson("/api/problems/{$problem->id}/submit", ['language' => 'python', 'code' => 'x'], ['Accept-Language' => 'ar'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'يجب تأكيد البريد الإلكتروني قبل إرسال الكود.', 'reason' => 'email_unverified']);
    }

    public function test_a_verified_user_can_submit_and_earn_points(): void
    {
        config(['services.sandbox.url' => 'http://sandbox.test']);
        Http::fake(['sandbox.test/*' => Http::response([
            'status' => 'passed',
            'results' => [['passed' => true, 'actual' => '2', 'expected' => '2', 'error' => null]],
            'runtime_ms' => 5,
            'compile_error' => null,
        ])]);
        $problem = $this->problem();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/api/problems/{$problem->id}/submit", ['language' => 'python', 'code' => 'print(2)'])
            ->assertOk()
            ->assertJsonPath('status', 'passed');
        $this->assertSame(10, $user->fresh()->points);
    }

    public function test_an_unverified_user_can_still_read_everything(): void
    {
        $problem = $this->problem();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user);
        $this->getJson('/api/me')->assertOk();
        $this->getJson('/api/modules')->assertOk();
        $this->getJson('/api/lessons/warm-up')->assertOk();
        $this->getJson('/api/challenges')->assertOk();
        $this->getJson("/api/challenges/{$problem->slug}")->assertOk();
        $this->getJson('/api/leaderboard')->assertOk();
        $this->getJson('/api/leaderboard/me')->assertOk();
        $this->postJson('/api/logout')->assertOk();
    }

    public function test_make_admin_marks_the_email_as_verified(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'ada@example.com']);

        $this->artisan('user:make-admin', ['email' => 'ada@example.com'])
            ->expectsOutput('ada@example.com is now an admin.')
            ->expectsOutput('The email ada@example.com is marked as verified.')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_make_admin_keeps_an_earlier_verification_date(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create(['email' => 'ada@example.com', 'email_verified_at' => now()->subYear()]);

        $this->artisan('user:make-admin', ['email' => 'ada@example.com', '--owner' => true])->assertSuccessful();

        $this->assertTrue($user->fresh()->email_verified_at->equalTo(now()->subYear()));
    }
}
