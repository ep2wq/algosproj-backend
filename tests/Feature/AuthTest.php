<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_login_requires_fields(): void
    {
        $this->postJson('/api/login', [])->assertUnprocessable();
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
