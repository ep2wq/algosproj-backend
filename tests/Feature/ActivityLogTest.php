<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function events(): array
    {
        return UserActivityLog::orderBy('created_at')->orderBy('id')->get(['user_id', 'email', 'event', 'ip', 'user_agent'])->toArray();
    }

    public function test_register_login_failures_and_logout_are_logged_with_user_ip_and_browser(): void
    {
        $this->withHeader('User-Agent', 'TestBrowser/1.0');

        $this->postJson('/api/register', ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret-password', 'accepted_terms' => true])->assertCreated();
        $id = User::where('email', 'ada@example.com')->value('id');
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong-password'])->assertUnauthorized();
        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])->assertUnauthorized();
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::find($id))->postJson('/api/logout')->assertOk();

        $row = fn ($userId, $email, $event) => ['user_id' => $userId, 'email' => $email, 'event' => $event, 'ip' => '127.0.0.1', 'user_agent' => 'TestBrowser/1.0'];
        $this->assertSame([
            $row($id, 'ada@example.com', 'register'),
            $row($id, 'ada@example.com', 'login_failed'),
            $row(null, 'nobody@example.com', 'login_failed'),
            $row($id, 'ada@example.com', 'login'),
            $row($id, 'ada@example.com', 'logout'),
        ], $this->events());
    }

    public function test_banned_login_ban_and_unban_are_logged(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);

        $this->actingAs($owner)->postJson("/api/admin/users/{$user->id}/ban", ['reason' => 'Spam'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password'])->assertForbidden();
        $this->actingAs($owner)->postJson("/api/admin/users/{$user->id}/unban")->assertOk();

        // Ban and unban are the admin's requests, so they carry no IP or browser.
        $this->assertSame([
            ['user_id' => $user->id, 'email' => 'ada@example.com', 'event' => 'banned_by_admin', 'ip' => null, 'user_agent' => null],
            ['user_id' => $user->id, 'email' => 'ada@example.com', 'event' => 'login_blocked_banned', 'ip' => '127.0.0.1', 'user_agent' => 'Symfony'],
            ['user_id' => $user->id, 'email' => 'ada@example.com', 'event' => 'unbanned', 'ip' => null, 'user_agent' => null],
        ], $this->events());
    }

    public function test_long_values_are_cut_and_throttled_requests_are_not_logged(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);
        $this->withHeader('User-Agent', str_repeat('u', 300));

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong-password']);
        }

        $this->assertSame(5, UserActivityLog::count());
        $this->assertSame(255, strlen(UserActivityLog::first()->user_agent));
    }

    public function test_no_row_holds_a_password_or_a_token(): void
    {
        $this->postJson('/api/register', ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret-password', 'accepted_terms' => true]);
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong-password-guess']);
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password']);
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::first())->postJson('/api/logout');

        $all = json_encode(UserActivityLog::all()->toArray());
        foreach (['secret-password', 'wrong-password-guess', 'password', User::first()->password] as $secret) {
            $this->assertStringNotContainsString($secret, $all);
        }
    }

    public function test_a_logging_failure_does_not_break_login_or_register(): void
    {
        User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);
        Schema::drop('user_activity_logs');
        Log::shouldReceive('error')->twice()->withArgs(fn ($message) => $message === 'Could not write user activity');

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password'])->assertOk()->assertJsonStructure(['user'])->assertJsonMissingPath('token');
        $this->postJson('/api/register', ['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret-password', 'accepted_terms' => true])->assertCreated();
    }

    public function test_a_ban_is_not_saved_when_its_activity_row_cannot_be(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $user = User::factory()->create();
        $rememberToken = $user->remember_token;
        DB::table('sessions')->insert(['id' => str_repeat('a', 40), 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        Schema::drop('user_activity_logs');

        $this->actingAs($owner)->postJson("/api/admin/users/{$user->id}/ban", ['reason' => 'Spam'])->assertServerError();

        $this->assertNull($user->refresh()->banned_at);
        // Rolled back with the ban: the session and the remember token are still there.
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame($rememberToken, $user->remember_token);
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_prune_deletes_only_rows_older_than_the_given_days(): void
    {
        foreach ([1, 89, 91, 400] as $daysAgo) {
            UserActivityLog::forceCreate(['email' => "d$daysAgo@example.com", 'event' => 'login', 'created_at' => now()->subDays($daysAgo)]);
        }

        $this->artisan('activity:prune')->expectsOutput('Deleted 2 activity rows older than 90 days.')->assertExitCode(0);
        $this->assertSame(['d1@example.com', 'd89@example.com'], UserActivityLog::orderByDesc('created_at')->pluck('email')->all());

        $this->artisan('activity:prune', ['--days' => 30])->expectsOutput('Deleted 1 activity rows older than 30 days.')->assertExitCode(0);
        $this->artisan('activity:prune', ['--days' => 0])->expectsOutput('--days must be a whole number of at least 1.')->assertExitCode(1);
        $this->assertSame(1, UserActivityLog::count());
    }

    public function test_activity_endpoints_are_newest_first_filtered_and_paginated(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $admin = User::factory()->create(['role' => 'admin']);
        $ada = User::factory()->create(['email' => 'ada@example.com']);
        UserActivityLog::forceCreate(['user_id' => $ada->id, 'email' => 'ada@example.com', 'event' => 'register', 'created_at' => '2026-01-01 10:00:00']);
        UserActivityLog::forceCreate(['user_id' => $ada->id, 'email' => 'ada@example.com', 'event' => 'login', 'created_at' => '2026-01-02 10:00:00']);
        UserActivityLog::forceCreate(['user_id' => null, 'email' => 'ghost@example.com', 'event' => 'login_failed', 'created_at' => '2026-01-03 10:00:00']);

        $this->actingAs($admin)->getJson("/api/admin/users/{$ada->id}/activity")->assertOk()
            ->assertJsonPath('per_page', 25)->assertJsonPath('data.0.event', 'login')->assertJsonPath('data.1.event', 'register')->assertJsonCount(2, 'data');
        $this->actingAs($admin)->getJson('/api/admin/activity')->assertForbidden();

        $events = fn (string $query = '') => array_column($this->actingAs($owner)->getJson("/api/admin/activity$query")->assertOk()->json('data'), 'event');
        $this->assertSame(['login_failed', 'login', 'register'], $events());
        $this->assertSame(['login'], $events('?event=login'));
        $this->assertSame(['login', 'register'], $events("?user={$ada->id}"));
        $this->assertSame(['login_failed'], $events('?user=ghost@example.com'));
        $this->assertSame(['login', 'register'], $events('?from=2026-01-01&to=2026-01-02'));
        $this->assertSame(['login_failed'], $events('?from=2026-01-03'));
        $this->actingAs($owner)->getJson('/api/admin/activity?from=bad')->assertUnprocessable()->assertJsonValidationErrors('from');

        UserActivityLog::insert(array_map(fn () => ['id' => (string) Str::uuid7(), 'email' => 'x@example.com', 'event' => 'login', 'created_at' => now()], range(1, 60)));
        $this->actingAs($owner)->getJson('/api/admin/activity')->assertJsonPath('per_page', 50)->assertJsonCount(50, 'data')->assertJsonPath('total', 63);
    }

    public function test_deleting_a_user_keeps_their_activity_rows_without_the_link(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $user = User::factory()->create(['email' => 'ada@example.com']);
        UserActivityLog::write($user, $user->email, 'login');

        $this->actingAs($owner)->deleteJson("/api/admin/users/{$user->id}", ['confirm_email' => 'ada@example.com'])->assertNoContent();

        $this->assertSame([['user_id' => null, 'email' => 'ada@example.com', 'event' => 'login', 'ip' => null, 'user_agent' => null]], $this->events());
    }
}
