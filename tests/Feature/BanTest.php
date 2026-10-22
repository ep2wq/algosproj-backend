<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BanTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name, int $points, array $attributes = []): User
    {
        $user = User::factory()->create(['name' => $name, 'email' => strtolower($name).'@example.com', 'password' => 'secret-password'] + $attributes);
        $user->forceFill(['points' => $points])->save();

        return $user;
    }

    private function ban(User $user): void
    {
        $user->forceFill(['banned_at' => now(), 'ban_reason' => 'Spamming the leaderboard'])->save();
    }

    public function test_a_banned_user_cannot_log_in_and_gets_no_token(): void
    {
        $this->ban($this->user('Mallory', 0));

        $this->postJson('/api/login', ['email' => 'mallory@example.com', 'password' => 'secret-password'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'This account is banned.', 'reason' => 'Spamming the leaderboard'])
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('user');

        // A wrong password says nothing about the ban.
        $this->postJson('/api/login', ['email' => 'mallory@example.com', 'password' => 'wrong-password'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Wrong email or password.']);
    }

    public function test_a_session_that_survived_a_ban_is_refused_everywhere(): void
    {
        $user = $this->user('Mallory', 10, ['role' => 'admin']);
        // Set directly, so no session is deleted as the ban endpoint would; actingAs is that session.
        $this->ban($user);

        foreach ([['GET', '/api/me'], ['GET', '/api/leaderboard/me'], ['POST', '/api/logout'], ['POST', '/api/problems/99999/submit'], ['GET', '/api/admin/modules'], ['GET', '/api/admin/problems/99999']] as [$method, $uri]) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($user)->json($method, $uri)
                ->assertForbidden()
                ->assertExactJson(['message' => 'This account is banned.', 'reason' => 'Spamming the leaderboard']);
        }
    }

    public function test_banned_users_are_left_out_of_the_leaderboard_and_the_ranks(): void
    {
        $top = $this->user('Top', 50);
        $this->user('Second', 40);
        $me = $this->user('Me', 30);
        $this->ban($top);

        $this->getJson('/api/leaderboard')->assertOk()->assertExactJson([
            ['rank' => 1, 'name' => 'Second', 'points' => 40, 'is_you' => false],
            ['rank' => 2, 'name' => 'Me', 'points' => 30, 'is_you' => false],
        ]);
        $this->actingAs($me)->getJson('/api/leaderboard/me')->assertOk()->assertExactJson(['rank' => 2, 'points' => 30]);
    }

    public function test_ban_fields_are_not_in_the_user_object(): void
    {
        $user = $this->user('Ada', 0);

        $this->actingAs($user)->getJson('/api/me')->assertOk()
            ->assertJsonMissingPath('banned_at')->assertJsonMissingPath('ban_reason');
    }
}
