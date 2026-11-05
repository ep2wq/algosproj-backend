<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HoneypotTest extends TestCase
{
    use RefreshDatabase;

    private const TRAP = SecurityEvent::HONEYPOT_FIELD;

    private function fresh(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
    }

    private function assertRecorded(string $route): void
    {
        $event = SecurityEvent::sole();
        $this->assertSame(['honeypot_filled', '127.0.0.1', 'POST', "/$route"], [$event->type, $event->ip, $event->method, $event->path]);
        $this->assertSameJson(['route' => $route, 'fields' => [self::TRAP], 'value' => 'https://spam.example'], $event->detail);
    }

    public function test_login_with_the_field_filled_answers_like_a_wrong_password_even_with_the_right_one(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);
        $wrong = $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong-password']);
        $this->fresh();

        $trapped = $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password', self::TRAP => 'https://spam.example']);

        $trapped->assertUnauthorized();
        $this->assertSame($wrong->getContent(), $trapped->getContent());
        $this->assertGuest('web');
        $this->assertRecorded('api/login');
    }

    public function test_register_with_the_field_filled_answers_like_a_taken_email_and_creates_no_account(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $fields = ['name' => 'Ada', 'password' => 'secret-password', 'accepted_terms' => true];

        foreach (['en', 'ar'] as $language) {
            SecurityEvent::query()->delete();
            $taken = $this->postJson('/api/register', $fields + ['email' => 'taken@example.com'], ['Accept-Language' => $language]);
            $this->fresh();
            $trapped = $this->postJson('/api/register', $fields + ['email' => 'new@example.com', self::TRAP => 'https://spam.example'], ['Accept-Language' => $language]);

            $trapped->assertUnprocessable();
            $this->assertSame($taken->getContent(), $trapped->getContent(), $language);
            $this->assertRecorded('api/register');
        }
        $this->assertFalse(User::where('email', 'new@example.com')->exists());
        $this->assertGuest('web');

        // Missing fields still get the ordinary validation errors first.
        $this->postJson('/api/register', [self::TRAP => 'x'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_forgot_password_with_the_field_filled_answers_202_and_sends_no_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $normal = $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com']);
        $trapped = $this->postJson('/api/forgot-password', ['email' => 'ada@example.com', self::TRAP => 'https://spam.example']);

        $trapped->assertStatus(202);
        $this->assertSame($normal->getContent(), $trapped->getContent());
        Notification::assertNotSentTo($user, ResetPassword::class);
        $this->assertRecorded('api/forgot-password');
    }

    public function test_an_empty_field_changes_nothing(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password', self::TRAP => ''])->assertOk();
        $this->assertSame(0, SecurityEvent::count());
    }
}
