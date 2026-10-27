<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// Register must accept the Terms of use and Privacy policy (accepted_terms); the server records
// when and which version (config/legal.php). Only register sets the two columns, and no
// response shows them. Login is unchanged.
class TermsConsentTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $extra = []): array
    {
        return ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret-password'] + $extra;
    }

    public function test_register_without_accepted_terms_is_refused_and_creates_nothing(): void
    {
        $this->postJson('/api/register', $this->form())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accepted_terms'])
            ->assertJsonMissingValidationErrors(['name', 'email', 'password']);

        $this->assertDatabaseCount('users', 0);
        $this->assertSame(0, UserActivityLog::count());
        $this->assertGuest('web');
    }

    public static function refusedValues(): array
    {
        return [
            'false' => [false], '"0"' => ['0'], '0' => [0], '"false"' => ['false'], '"no"' => ['no'],
            'empty' => [''], 'null' => [null], '"yes"' => ['yes'], '"on"' => ['on'], '"true"' => ['true'],
            '"maybe"' => ['maybe'], '2' => [2], 'array' => [[true]],
        ];
    }

    #[DataProvider('refusedValues')]
    public function test_register_refuses_anything_but_true(mixed $value): void
    {
        $this->postJson('/api/register', $this->form(['accepted_terms' => $value]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accepted_terms']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_records_when_and_which_version_was_accepted(): void
    {
        $this->freezeSecond();
        config(['legal.terms_version' => '2030-01-02']);

        $this->postJson('/api/register', $this->form(['accepted_terms' => true]))->assertCreated();

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertTrue($user->terms_accepted_at->equalTo(now()));
        $this->assertSame('2030-01-02', $user->terms_version);
    }

    public function test_register_ignores_terms_columns_sent_by_the_client(): void
    {
        $this->freezeSecond();

        $this->postJson('/api/register', $this->form([
            'accepted_terms' => true,
            'terms_accepted_at' => '2000-01-01 00:00:00',
            'terms_version' => 'forged',
        ]))->assertCreated();

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertTrue($user->terms_accepted_at->equalTo(now()));
        $this->assertSame(config('legal.terms_version'), $user->terms_version);
    }

    public function test_the_terms_columns_are_in_no_user_response(): void
    {
        $this->postJson('/api/register', $this->form(['accepted_terms' => true]))
            ->assertCreated()
            ->assertJsonMissingPath('user.terms_accepted_at')
            ->assertJsonMissingPath('user.terms_version');

        $this->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonMissingPath('user.terms_accepted_at')
            ->assertJsonMissingPath('user.terms_version');

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->actingAs($user)->getJson('/api/me')
            ->assertOk()
            ->assertJsonMissingPath('terms_accepted_at')
            ->assertJsonMissingPath('terms_version');
    }

    public function test_the_terms_columns_are_not_mass_assignable(): void
    {
        $user = User::create(['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'secret-password', 'terms_accepted_at' => now(), 'terms_version' => 'forged']);
        $user->update(['terms_accepted_at' => now(), 'terms_version' => 'forged']);

        $user->refresh();
        $this->assertNull($user->terms_accepted_at);
        $this->assertNull($user->terms_version);
    }

    public function test_admin_and_owner_updates_cannot_change_the_terms_columns(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $accepted = User::factory()->acceptedTerms()->create(['terms_version' => '2026-01-01']);
        $old = User::factory()->create();
        $forged = ['terms_accepted_at' => '2000-01-01 00:00:00', 'terms_version' => 'forged'];

        $this->actingAs($owner)->putJson("/api/admin/users/{$accepted->id}/role", ['role' => 'admin'] + $forged)->assertOk();
        $this->actingAs($owner)->postJson("/api/admin/users/{$accepted->id}/points", ['amount' => 5, 'reason' => 'Bonus'] + $forged)->assertOk();
        $this->actingAs($owner)->postJson("/api/admin/users/{$old->id}/ban", ['reason' => 'Spam'] + $forged)->assertOk()
            ->assertJsonMissingPath('terms_accepted_at')
            ->assertJsonMissingPath('terms_version');
        $this->actingAs($owner)->getJson("/api/admin/users/{$accepted->id}")->assertOk()
            ->assertJsonMissingPath('terms_accepted_at')
            ->assertJsonMissingPath('terms_version');

        $this->assertSame('2026-01-01', $accepted->refresh()->terms_version);
        $this->assertNotNull($accepted->terms_accepted_at);
        $this->assertNull($old->refresh()->terms_version);
        $this->assertNull($old->terms_accepted_at);
    }

    public function test_login_needs_no_terms_field_and_works_for_accounts_without_a_record(): void
    {
        // Accounts made before the columns existed (and the seeded Test User) keep NULL.
        $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);
        $this->assertNull($user->terms_accepted_at);

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('user.email', 'ada@example.com');

        // A stray field is ignored and changes nothing.
        $this->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password', 'accepted_terms' => false])
            ->assertOk();

        $this->assertNull($user->refresh()->terms_accepted_at);
        $this->assertNull($user->terms_version);
    }

    public function test_the_arabic_error_names_the_terms(): void
    {
        $this->postJson('/api/register', $this->form(['accepted_terms' => false]), ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.accepted_terms.0', 'يجب قبول شروط الاستخدام وسياسة الخصوصية.');
    }
}
