<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_carry_nosniff_and_a_referrer_policy(): void
    {
        foreach ([$this->getJson('/api/modules'), $this->getJson('/api/me'), $this->getJson('/api/no-such-route'), $this->get('/up')] as $response) {
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    public function test_a_header_already_set_is_kept(): void
    {
        Route::get('/api/test-own-referrer-policy', fn () => response()->json([])->header('Referrer-Policy', 'no-referrer'));

        $this->getJson('/api/test-own-referrer-policy')->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_per_user_responses_are_private(): void
    {
        $user = User::factory()->create();

        foreach (['/api/me', '/api/leaderboard/me', '/api/leaderboard'] as $path) {
            $this->assertStringContainsString('private', (string) $this->actingAs($user)->getJson($path)->headers->get('Cache-Control'));
        }
    }

    public function test_production_refuses_the_log_and_array_mailers(): void
    {
        $this->app['env'] = 'production';
        config(['session.secure' => true]);

        foreach (['log', 'array'] as $mailer) {
            config(['mail.default' => $mailer]);
            try {
                (new AppServiceProvider($this->app))->boot();
                $this->fail("Booted in production with the $mailer mailer.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('MAIL_MAILER', $e->getMessage());
            }
        }

        config(['mail.default' => 'smtp']);
        (new AppServiceProvider($this->app))->boot();
        $this->addToAssertionCount(1);
    }

    public function test_production_refuses_log_and_array_inside_failover_and_roundrobin(): void
    {
        $this->app['env'] = 'production';
        config(['session.secure' => true]);

        foreach (['failover', 'roundrobin'] as $mailer) {
            foreach (['log', 'array'] as $fake) {
                config(['mail.default' => $mailer, "mail.mailers.$mailer.mailers" => ['smtp', $fake]]);
                try {
                    (new AppServiceProvider($this->app))->boot();
                    $this->fail("Booted in production with $fake inside $mailer.");
                } catch (RuntimeException $e) {
                    $this->assertStringContainsString('MAIL_MAILER', $e->getMessage());
                }
            }

            config(["mail.mailers.$mailer.mailers" => ['smtp', 'resend']]);
            (new AppServiceProvider($this->app))->boot();
        }

        // A mailer of another name with the log transport.
        config(['mail.default' => 'failover', 'mail.mailers.failover.mailers' => ['smtp', 'audit'], 'mail.mailers.audit' => ['transport' => 'log']]);
        $this->expectException(RuntimeException::class);
        (new AppServiceProvider($this->app))->boot();
        $this->addToAssertionCount(1);
    }

    public function test_other_environments_accept_the_log_mailer(): void
    {
        config(['mail.default' => 'log']);
        (new AppServiceProvider($this->app))->boot();
        $this->addToAssertionCount(1);
    }

    public function test_production_refuses_a_session_cookie_without_the_secure_flag(): void
    {
        $this->app['env'] = 'production';
        config(['mail.default' => 'smtp']);

        foreach ([false, null, 'true'] as $secure) {
            config(['session.secure' => $secure]);
            try {
                (new AppServiceProvider($this->app))->boot();
                $this->fail('Booted in production with session.secure '.var_export($secure, true).'.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('SESSION_SECURE_COOKIE', $e->getMessage());
            }
        }

        config(['session.secure' => true]);
        (new AppServiceProvider($this->app))->boot();
        $this->addToAssertionCount(1);
    }

    public function test_other_environments_boot_without_the_secure_flag(): void
    {
        foreach (['local', 'testing'] as $env) {
            $this->app['env'] = $env;
            config(['session.secure' => null]);
            (new AppServiceProvider($this->app))->boot();
        }
        $this->addToAssertionCount(1);
    }

    public function test_the_storage_route_does_not_exist(): void
    {
        $this->get('/storage/bug-reports/anything.png')->assertNotFound();
        $this->put('/storage/anything.txt', ['x' => 'y'])->assertNotFound();
        $this->assertFalse(Route::has('storage.local'));
        $this->assertFalse(Route::has('storage.local.upload'));
    }

    public function test_the_seeder_creates_the_test_user_only_locally_and_in_tests(): void
    {
        // The seeder itself, not db:seed (which asks for confirmation in production).
        $seed = fn () => $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->__invoke();

        $this->app['env'] = 'production';
        $seed();
        $this->assertFalse(User::where('email', 'test@example.com')->exists());

        $this->app['env'] = 'testing';
        $seed();
        $this->assertTrue(User::where('email', 'test@example.com')->exists());
    }

    public function test_activity_prune_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('activity:prune')->assertSuccessful();
    }
}
