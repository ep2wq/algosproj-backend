<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPoints(string $name, int $points, string $createdAt = '2026-01-01 00:00:00'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->forceFill(['points' => $points, 'created_at' => $createdAt])->save();

        return $user;
    }

    public function test_leaderboard_orders_by_points_then_earliest_created(): void
    {
        $this->userWithPoints('Low', 5);
        $this->userWithPoints('Later tie', 20, '2026-03-01 00:00:00');
        $this->userWithPoints('Top', 30);
        $this->userWithPoints('Earlier tie', 20, '2026-02-01 00:00:00');
        $this->userWithPoints('Zero', 0);

        $this->getJson('/api/leaderboard')
            ->assertOk()
            ->assertExactJson([
                ['rank' => 1, 'name' => 'Top', 'points' => 30, 'is_you' => false],
                ['rank' => 2, 'name' => 'Earlier tie', 'points' => 20, 'is_you' => false],
                ['rank' => 2, 'name' => 'Later tie', 'points' => 20, 'is_you' => false],
                ['rank' => 4, 'name' => 'Low', 'points' => 5, 'is_you' => false],
            ]);
    }

    public function test_leaderboard_limit_defaults_to_50_and_is_capped_at_100(): void
    {
        for ($i = 1; $i <= 101; $i++) {
            $this->userWithPoints("User $i", $i);
        }

        $this->getJson('/api/leaderboard')->assertOk()->assertJsonCount(50);
        $this->getJson('/api/leaderboard?limit=3')->assertOk()->assertJsonCount(3)
            ->assertJsonPath('0.name', 'User 101');
        $this->getJson('/api/leaderboard?limit=500')->assertOk()->assertJsonCount(100);
    }

    public function test_leaderboard_marks_only_the_requesters_row_when_name_and_points_tie(): void
    {
        $first = $this->userWithPoints('Same', 10, '2026-01-01 00:00:00');
        $second = $this->userWithPoints('Same', 10, '2026-02-01 00:00:00');

        foreach ([[$first, [true, false]], [$second, [false, true]]] as [$user, $expected]) {
            $this->app['auth']->forgetGuards();
            $rows = $this->withToken($user->createToken('api')->plainTextToken)
                ->getJson('/api/leaderboard')
                ->assertOk()
                ->json();

            $this->assertSame($expected, array_column($rows, 'is_you'));
            $this->assertArrayNotHasKey('id', $rows[0]);
        }
    }

    public function test_leaderboard_logged_out_marks_no_row(): void
    {
        $this->userWithPoints('Top', 30);
        $this->userWithPoints('Other', 20);

        $rows = $this->getJson('/api/leaderboard')->assertOk()->json();

        $this->assertSame([false, false], array_column($rows, 'is_you'));
    }

    public function test_leaderboard_me_returns_rank_and_points(): void
    {
        $this->userWithPoints('Top', 30);
        $this->userWithPoints('Tie', 20);
        $me = $this->userWithPoints('Me', 20);

        $this->actingAs($me)->getJson('/api/leaderboard/me')
            ->assertOk()
            ->assertExactJson(['rank' => 2, 'points' => 20]);
    }

    public function test_leaderboard_me_for_user_without_points(): void
    {
        $this->userWithPoints('Top', 30);
        $me = User::factory()->create();

        $this->actingAs($me)->getJson('/api/leaderboard/me')
            ->assertOk()
            ->assertExactJson(['rank' => 2, 'points' => 0]);
    }

    public function test_leaderboard_me_requires_auth(): void
    {
        $this->getJson('/api/leaderboard/me')->assertUnauthorized();
    }

    public function test_users_table_has_the_leaderboard_index(): void
    {
        $index = collect(Schema::getIndexes('users'))->firstWhere('name', 'users_leaderboard_index');

        $this->assertNotNull($index);
        $this->assertSame(['points', 'created_at'], $index['columns']);
        $this->assertFalse($index['unique']);
    }
}
