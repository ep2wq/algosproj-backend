<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_ignores_a_role_sent_by_the_client(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'secret-password',
            'role' => 'admin',
        ])->assertCreated()->assertJsonPath('user.role', 'user');

        $this->assertSame('user', User::where('email', 'mallory@example.com')->value('role'));
    }

    public function test_role_is_not_mass_assignable(): void
    {
        $user = User::create(['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'secret-password', 'role' => 'admin']);

        $this->assertSame('user', $user->refresh()->role);
    }

    public function test_login_and_me_include_the_role(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);
        $user->role = 'admin';
        $user->save();

        $token = $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->json('token');

        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('role', 'admin');
    }

    public function test_make_admin_command_grants_and_removes_the_role(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->artisan('user:make-admin', ['email' => 'ada@example.com'])
            ->expectsOutput('ada@example.com is now an admin.')
            ->assertExitCode(0);
        $this->assertSame('admin', $user->refresh()->role);

        $this->artisan('user:make-admin', ['email' => 'ada@example.com', '--remove' => true])
            ->expectsOutput('ada@example.com is now a normal user.')
            ->assertExitCode(0);
        $this->assertSame('user', $user->refresh()->role);
    }

    public function test_make_admin_command_makes_an_owner_and_back(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->artisan('user:make-admin', ['email' => 'ada@example.com', '--owner' => true])
            ->expectsOutput('ada@example.com is now an owner.')
            ->assertExitCode(0);
        $this->assertSame('owner', $user->refresh()->role);

        $this->artisan('user:make-admin', ['email' => 'ada@example.com'])
            ->expectsOutput('ada@example.com is now an admin.')
            ->assertExitCode(0);
        $this->assertSame('admin', $user->refresh()->role);

        $this->artisan('user:make-admin', ['email' => 'ada@example.com', '--owner' => true, '--remove' => true])
            ->expectsOutput('Use either --owner or --remove, not both.')
            ->assertExitCode(1);
        $this->assertSame('admin', $user->refresh()->role);
    }

    public function test_make_admin_command_rejects_an_unknown_email(): void
    {
        foreach ([[], ['--owner' => true], ['--remove' => true]] as $options) {
            $this->artisan('user:make-admin', ['email' => 'nobody@example.com'] + $options)
                ->expectsOutput('No user has the email nobody@example.com.')
                ->assertExitCode(1);
        }
    }

    public function test_an_owner_passes_the_admin_middleware(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->getJson('/api/admin/modules')->assertOk();
    }

    public function test_public_responses_never_include_a_role(): void
    {
        $admin = User::factory()->create(['points' => 30]);
        $admin->role = 'admin';
        $admin->save();
        User::factory()->create(['points' => 10]);
        $module = Module::create(['slug' => 'basics', 'title' => 'Basics', 'track' => 'basics', 'sort_order' => 1]);
        $lesson = $module->lessons()->create(['slug' => 'warm-up', 'title' => 'Warm-up', 'body' => 'Text', 'sort_order' => 1]);
        $lesson->problems()->create(['slug' => 'warm-up', 'title' => 'Double', 'description' => 'Double it', 'difficulty' => 'easy', 'points' => 10]);
        $token = $admin->createToken('api')->plainTextToken;

        // Logged out and logged in as the admin (the token marks is_you and solved).
        foreach ([null, $token] as $sentToken) {
            $this->flushHeaders();
            if ($sentToken) {
                $this->withToken($sentToken);
            }
            foreach (['/api/leaderboard?limit=100', '/api/challenges', '/api/challenges/warm-up', '/api/modules', '/api/lessons/warm-up'] as $uri) {
                $body = $this->getJson($uri)->assertOk()->getContent();
                $this->assertStringNotContainsString('role', $body, $uri);
                $this->assertStringNotContainsString('admin', $body, $uri);
            }
        }
    }
}
