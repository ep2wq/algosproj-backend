<?php

namespace Tests\Feature;

use App\Models\Completion;
use App\Models\Module;
use App\Models\Problem;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $otherAdmin;

    private User $user;

    private Problem $problem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'owner', 'email' => 'owner@example.com']);
        $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $this->otherAdmin = User::factory()->create(['role' => 'admin', 'email' => 'admin2@example.com']);
        $this->user = User::factory()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret-password', 'points' => 10]);

        $module = Module::create(['slug' => 'basics', 'title' => 'Basics', 'track' => 'basics', 'sort_order' => 1]);
        $lesson = $module->lessons()->create(['slug' => 'warm-up', 'title' => 'Warm-up', 'sort_order' => 1]);
        $this->problem = $lesson->problems()->create(['slug' => 'warm-up', 'title' => 'Double', 'description' => 'D', 'difficulty' => 'easy', 'points' => 10]);
        Completion::create(['user_id' => $this->user->id, 'problem_id' => $this->problem->id]);
        $this->user->submissions()->create(['problem_id' => $this->problem->id, 'language' => 'python', 'code' => 'SECRET-CODE', 'status' => 'passed', 'passed_count' => 2, 'total_count' => 2, 'runtime_ms' => 12]);
    }

    private function send(string $method, string $uri, array $data = [], ?User $as = null): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        if ($as) {
            $this->withToken($as->createToken('api')->plainTextToken);
        }

        return $this->json($method, $uri, $data);
    }

    // Each write action on a user, with a valid body.
    private function actions(User $target): array
    {
        return [
            'ban' => ['POST', "/api/admin/users/{$target->id}/ban", ['reason' => 'Spam']],
            'unban' => ['POST', "/api/admin/users/{$target->id}/unban", []],
            'logout-everywhere' => ['POST', "/api/admin/users/{$target->id}/logout-everywhere", []],
            'role' => ['PUT', "/api/admin/users/{$target->id}/role", ['role' => 'user']],
            'points' => ['POST', "/api/admin/users/{$target->id}/points", ['amount' => 5, 'reason' => 'Bonus']],
            'reset-progress' => ['POST', "/api/admin/users/{$target->id}/reset-progress", []],
            'delete' => ['DELETE', "/api/admin/users/{$target->id}", ['confirm_email' => $target->email]],
        ];
    }

    private function usersSnapshot(): array
    {
        return User::all()->map(fn ($user) => $user->makeVisible(['banned_at', 'ban_reason'])->toArray())->all();
    }

    public function test_nobody_can_act_on_themselves_or_on_an_owner(): void
    {
        $before = $this->usersSnapshot();

        foreach ([$this->owner, $this->admin] as $actor) {
            foreach ($this->actions($actor) as $name => [$method, $uri, $data]) {
                $response = $this->send($method, $uri, $data, $actor)->assertForbidden();
                // An admin is stopped on owner-only routes by the middleware before the target check.
                if ($actor->role === 'owner' || in_array($name, ['ban', 'unban', 'logout-everywhere'])) {
                    $response->assertJsonPath('message', "You can't do this to your own account.");
                }
            }
        }
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $before = $this->usersSnapshot();
        foreach ([$this->owner, $this->admin] as $actor) {
            foreach ($this->actions($otherOwner) as $name => [$method, $uri, $data]) {
                $response = $this->send($method, $uri, $data, $actor)->assertForbidden();
                if ($actor->role === 'owner' || in_array($name, ['ban', 'unban', 'logout-everywhere'])) {
                    $response->assertJsonPath('message', 'Owners can only be changed with the artisan command.');
                }
            }
        }

        $this->assertSame($before, $this->usersSnapshot());
    }

    public function test_an_admin_cannot_act_on_another_admin_but_an_owner_can(): void
    {
        $before = $this->usersSnapshot();
        foreach ($this->actions($this->otherAdmin) as $name => [$method, $uri, $data]) {
            $response = $this->send($method, $uri, $data, $this->admin)->assertForbidden();
            $response->assertJsonPath('message', in_array($name, ['ban', 'unban', 'logout-everywhere'])
                ? 'Only an owner can do this to an admin.'
                : 'Only an owner can do this.');
        }
        $this->assertSame($before, $this->usersSnapshot());

        $this->send('POST', "/api/admin/users/{$this->otherAdmin->id}/ban", ['reason' => 'Rogue admin'], $this->owner)->assertOk();
        $this->send('POST', "/api/admin/users/{$this->otherAdmin->id}/unban", [], $this->owner)->assertOk();
        $this->send('PUT', "/api/admin/users/{$this->otherAdmin->id}/role", ['role' => 'user'], $this->owner)->assertOk()->assertJsonPath('role', 'user');
    }

    public function test_an_admin_can_ban_unban_and_log_out_a_normal_user(): void
    {
        $this->send('POST', "/api/admin/users/{$this->user->id}/ban", ['reason' => 'Spam'], $this->admin)->assertOk();
        $this->send('POST', "/api/admin/users/{$this->user->id}/unban", [], $this->admin)->assertOk();
        $this->send('POST', "/api/admin/users/{$this->user->id}/logout-everywhere", [], $this->admin)->assertOk();
    }

    public function test_ban_kills_tokens_hides_from_the_leaderboard_and_unban_restores_everything(): void
    {
        $token = $this->user->createToken('api')->plainTextToken;
        $this->user->createToken('phone');
        User::factory()->create(['name' => 'Bob', 'points' => 5]);

        $this->send('POST', "/api/admin/users/{$this->user->id}/ban", [], $this->admin)
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->send('POST', "/api/admin/users/{$this->user->id}/ban", ['reason' => str_repeat('a', 256)], $this->admin)
            ->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->send('POST', "/api/admin/users/{$this->user->id}/ban", ['reason' => 'Spam'], $this->admin)
            ->assertOk()->assertJsonPath('id', $this->user->id)->assertJsonPath('points', 10);
        $this->assertSame(0, $this->user->tokens()->count());
        $this->send('POST', "/api/admin/users/{$this->user->id}/ban", ['reason' => 'Again'], $this->admin)
            ->assertStatus(409)->assertJsonPath('message', 'This user is already banned.');

        // The old token is dead, login is refused, and the leaderboard and ranks skip the user.
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
        $this->flushHeaders();
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password'])
            ->assertForbidden()->assertJsonPath('reason', 'Spam');
        $this->send('GET', '/api/leaderboard')->assertExactJson([['rank' => 1, 'name' => 'Bob', 'points' => 5, 'is_you' => false]]);
        $this->send('GET', '/api/leaderboard/me', [], User::where('name', 'Bob')->first())->assertExactJson(['rank' => 1, 'points' => 5]);

        $this->send('POST', "/api/admin/users/{$this->user->id}/unban", [], $this->admin)->assertOk()->assertJsonPath('banned_at', null);
        $this->send('POST', "/api/admin/users/{$this->user->id}/unban", [], $this->admin)->assertStatus(409);

        $this->user->refresh();
        $this->assertNull($this->user->ban_reason);
        $this->assertSame(10, $this->user->points);
        $this->assertSame(1, $this->user->completions()->count());
        $this->send('GET', '/api/leaderboard')->assertJsonPath('0.name', 'Ada');
        $this->flushHeaders();
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password'])->assertOk();
    }

    public function test_logout_everywhere_deletes_every_token(): void
    {
        $this->user->createToken('a');
        $this->user->createToken('b');

        $this->send('POST', "/api/admin/users/{$this->user->id}/logout-everywhere", [], $this->admin)->assertOk();

        $this->assertSame(0, $this->user->tokens()->count());
    }

    public function test_the_role_can_be_user_or_admin_but_never_owner(): void
    {
        foreach (['owner', 'superadmin', ''] as $role) {
            $this->send('PUT', "/api/admin/users/{$this->user->id}/role", ['role' => $role], $this->owner)
                ->assertUnprocessable()->assertJsonValidationErrors('role');
        }
        $this->assertSame('user', $this->user->refresh()->role);

        $this->send('PUT', "/api/admin/users/{$this->user->id}/role", ['role' => 'admin'], $this->owner)->assertOk()->assertJsonPath('role', 'admin');
        $this->send('PUT', "/api/admin/users/{$this->user->id}/role", ['role' => 'user'], $this->owner)->assertOk()->assertJsonPath('role', 'user');
    }

    public function test_points_adjustments_need_a_reason_are_never_zero_and_never_go_below_zero(): void
    {
        $uri = "/api/admin/users/{$this->user->id}/points";

        $this->send('POST', $uri, ['amount' => 0, 'reason' => 'x'], $this->owner)->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->send('POST', $uri, ['amount' => 1.5, 'reason' => 'x'], $this->owner)->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->send('POST', $uri, ['amount' => 5], $this->owner)->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->send('POST', $uri, ['amount' => -11, 'reason' => 'x'], $this->owner)
            ->assertUnprocessable()->assertJsonPath('errors.amount.0', "Points can't go below 0. This user has 10.");
        $this->assertSame(10, $this->user->refresh()->points);

        $this->send('POST', $uri, ['amount' => 15, 'reason' => 'Contest'], $this->owner)->assertOk()->assertJsonPath('points', 25);
        $this->send('POST', $uri, ['amount' => -25, 'reason' => 'Cheating'], $this->owner)->assertOk()->assertJsonPath('points', 0);
    }

    public function test_reset_progress_deletes_completions_and_zeroes_points(): void
    {
        $this->send('POST', "/api/admin/users/{$this->user->id}/reset-progress", [], $this->owner)
            ->assertOk()->assertJsonPath('points', 0)->assertJsonPath('solved_count', 0);

        $this->assertSame(0, $this->user->completions()->count());
        $this->assertSame(1, $this->user->submissions()->count());
    }

    public function test_delete_needs_the_exact_email_and_cascades(): void
    {
        $this->user->createToken('api');
        $uri = "/api/admin/users/{$this->user->id}";

        foreach ([[], ['confirm_email' => 'ADA@example.com'], ['confirm_email' => 'ada@example'], ['confirm_email' => 'admin@example.com']] as $data) {
            $this->send('DELETE', $uri, $data, $this->owner)->assertUnprocessable()->assertJsonValidationErrors('confirm_email');
        }
        $this->assertModelExists($this->user);

        $this->send('DELETE', $uri, ['confirm_email' => 'ada@example.com'], $this->owner)->assertNoContent();

        $this->assertModelMissing($this->user);
        $this->assertSame(0, Completion::count());
        $this->assertSame(0, Submission::count());
        $this->assertSame(0, $this->user->tokens()->count());
    }

    public function test_the_list_searches_filters_and_paginates(): void
    {
        User::factory()->create(['name' => '100% sure', 'email' => 'percent@example.com']);
        User::factory()->create(['name' => 'Under_score', 'email' => 'under@example.com']);
        User::factory()->create(['name' => 'Banned', 'email' => 'banned@example.com', 'banned_at' => now(), 'ban_reason' => 'x']);

        $names = fn (string $query) => array_column($this->send('GET', "/api/admin/users?$query", [], $this->admin)->assertOk()->json('data'), 'name');

        $this->assertSame(['100% sure'], $names('search=%25'));
        $this->assertSame(['Under_score'], $names('search=_'));
        $this->assertSame(['Ada'], $names('search=ADA@EX'));
        $this->assertSame(['Banned'], $names('banned=1'));
        $this->assertNotContains('Banned', $names('banned=0'));
        $this->assertCount(2, $names('role=admin'));
        $this->send('GET', '/api/admin/users?role=root', [], $this->admin)->assertUnprocessable()->assertJsonValidationErrors('role');

        User::factory()->count(30)->create();
        $page = $this->send('GET', '/api/admin/users?page=2', [], $this->admin)->assertOk();
        $page->assertJsonPath('per_page', 25)->assertJsonPath('current_page', 2)->assertJsonPath('total', 37);
        $this->assertCount(12, $page->json('data'));

        $row = collect($this->send('GET', '/api/admin/users?search=ada@', [], $this->admin)->json('data'))->first();
        $this->assertSame(['id', 'name', 'email', 'role', 'points', 'banned_at', 'created_at', 'solved_count'], array_keys($row));
        $this->assertSame(1, $row['solved_count']);
    }

    public function test_the_detail_shows_solved_problems_and_submissions_without_code(): void
    {
        $response = $this->send('GET', "/api/admin/users/{$this->user->id}", [], $this->admin)->assertOk()
            ->assertJsonPath('email', 'ada@example.com')
            ->assertJsonPath('ban_reason', null)
            ->assertJsonPath('solved.0.title', 'Double')
            ->assertJsonPath('submissions.0.problem.title', 'Double')
            ->assertJsonPath('submissions.0.status', 'passed')
            ->assertJsonPath('submissions.0.language', 'python');

        $body = $response->getContent();
        foreach (['SECRET-CODE', 'password', 'remember_token', 'token'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }

        for ($i = 0; $i < 25; $i++) {
            $this->user->submissions()->create(['problem_id' => $this->problem->id, 'language' => 'cpp', 'code' => 'x', 'status' => 'failed', 'passed_count' => 0, 'total_count' => 2]);
        }
        $this->assertCount(20, $this->send('GET', "/api/admin/users/{$this->user->id}", [], $this->admin)->json('submissions'));
    }

    public function test_no_request_field_can_set_a_password_or_an_owner_role(): void
    {
        $hash = $this->user->password;
        $extra = ['password' => 'hacked-password', 'role' => 'owner', 'banned_at' => null, 'points' => 999999];

        foreach ($this->actions($this->user) as $name => [$method, $uri, $data]) {
            if ($name !== 'delete') {
                $this->send($method, $uri, $data + $extra, $this->owner)->assertOk();
            }
        }

        $this->user->refresh();
        $this->assertSame($hash, $this->user->password);
        $this->assertSame('user', $this->user->role);
        $this->assertSame(0, $this->user->points);
    }
}
