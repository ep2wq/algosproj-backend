<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_refuses_the_log_and_array_mailers(): void
    {
        $this->app['env'] = 'production';

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

    public function test_other_environments_accept_the_log_mailer(): void
    {
        config(['mail.default' => 'log']);
        (new AppServiceProvider($this->app))->boot();
        $this->addToAssertionCount(1);
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
}
