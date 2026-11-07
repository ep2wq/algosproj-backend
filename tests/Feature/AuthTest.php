<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_token_and_user(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'accepted_terms' => true,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'points']])
            ->assertJsonMissingPath('token')
            ->assertJsonPath('user.points', 0)
            ->assertJsonMissingPath('user.password');
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
            'accepted_terms' => true,
        ])->assertUnprocessable();
    }

    public function test_login_returns_the_user_and_no_token(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonStructure(['user' => ['id', 'email', 'points']])
            ->assertJsonMissingPath('token');
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong-password'])
            ->assertUnauthorized()
            ->assertJsonMissingPath('token');
    }

    public function test_sixth_register_in_a_minute_is_throttled(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/register', [
                'name' => "User $i",
                'email' => "user$i@example.com",
                'password' => 'secret-password',
                'accepted_terms' => true,
            ])->assertCreated();
        }

        $this->postJson('/api/register', [
            'name' => 'User 6',
            'email' => 'user6@example.com',
            'password' => 'secret-password',
            'accepted_terms' => true,
        ])->assertStatus(429);
    }

    public function test_sixth_login_in_a_minute_is_throttled(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong-password'])
                ->assertUnauthorized();
        }

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password'])
            ->assertStatus(429);

        // The limit is per IP plus email, so another email from the same IP still gets through.
        $this->postJson('/api/login', ['email' => 'other@example.com', 'password' => 'wrong-password'])
            ->assertUnauthorized();
    }

    public function test_the_31st_login_from_one_ip_across_emails_is_throttled(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->postJson('/api/login', ['email' => "spray$i@example.com", 'password' => 'wrong-password'])->assertUnauthorized();
        }
        $this->postJson('/api/login', ['email' => 'spray31@example.com', 'password' => 'wrong-password'])->assertStatus(429);
        $this->assertSame(['/api/login'], SecurityEvent::where('type', 'rate_limited')->pluck('path')->all());

        // Another IP has its own count, and a correct login from it still works.
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);
        $other = fn () => $this->withServerVariables(['HTTP_ORIGIN' => self::FRONTEND, 'REMOTE_ADDR' => '203.0.113.9']);
        $other()->postJson('/api/login', ['email' => 'spray31@example.com', 'password' => 'wrong-password'])->assertUnauthorized();
        $other()->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password'])->assertOk();
    }

    public function test_a_successful_login_still_works_under_both_limits(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);

        for ($i = 1; $i <= 4; $i++) {
            $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong-password'])->assertUnauthorized();
        }
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password'])->assertOk();
    }

    public function test_an_unknown_email_is_checked_against_a_dummy_hash_and_answers_like_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);
        $wrong = $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong-password']);

        // The unknown email still runs one password check, against a bcrypt hash.
        Hash::shouldReceive('make')->once()->andReturn('$2y$04$dummydummydummydummydu2E8tqSgx3YI8U6MQIdBpbLUXxDq5Z9S');
        Hash::shouldReceive('check')->once()->with('wrong-password', '$2y$04$dummydummydummydummydu2E8tqSgx3YI8U6MQIdBpbLUXxDq5Z9S')->andReturnFalse();
        $unknown = $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password']);

        $this->assertSame([401, $wrong->getContent()], [$unknown->getStatusCode(), $unknown->getContent()]);
        $unknown->assertExactJson(['message' => 'Wrong email or password.']);
    }

    public function test_the_dummy_hash_is_made_at_the_configured_cost_and_only_once(): void
    {
        $key = 'login-dummy-hash:'.config('hashing.bcrypt.rounds');
        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])->assertUnauthorized();
        $hash = Cache::get($key);
        $this->postJson('/api/login', ['email' => 'nobody2@example.com', 'password' => 'wrong-password'])->assertUnauthorized();

        $this->assertSame($hash, Cache::get($key));
        $this->assertSame((int) config('hashing.bcrypt.rounds'), password_get_info($hash)['options']['cost']);
    }

    public function test_login_requires_fields(): void
    {
        $this->postJson('/api/login', [])->assertUnprocessable();
    }

    // The login rate limiter reads the email before validation: an array or object must not crash it.
    public function test_an_email_that_is_not_a_string_is_a_validation_error_not_a_server_error(): void
    {
        foreach ([['ada@example.com'], ['a' => 'ada@example.com']] as $email) {
            $this->postJson('/api/login', ['email' => $email, 'password' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('email');
            $this->postJson('/api/register', ['name' => 'Ada', 'email' => $email, 'password' => 'secret-password', 'accepted_terms' => true])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
            $this->postJson('/api/forgot-password', ['email' => $email])->assertUnprocessable()->assertJsonValidationErrors('email');
            $this->postJson('/api/reset-password', ['token' => 't', 'email' => $email, 'password' => 'secret-password', 'password_confirmation' => 'secret-password'])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }
    }

    public function test_me_returns_user_with_points(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->actingAs($user)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email', 'ada@example.com')
            ->assertJsonPath('points', 0);
    }

    public function test_me_requires_auth(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    // SessionTest checks that the old session cookie stops working.
    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/logout')->assertOk();

        $this->assertGuest('web');
    }
}
