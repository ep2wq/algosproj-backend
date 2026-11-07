<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Every /api error is JSON, whatever the request's Accept header says (README "API messages").
class ApiErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_logged_out_api_request_without_the_json_accept_header_is_a_json_401(): void
    {
        foreach (['/api/me', '/api/leaderboard/me', '/api/admin/users', '/api/admin/security/events'] as $path) {
            $this->get($path)->assertUnauthorized()->assertHeader('Content-Type', 'application/json')
                ->assertExactJson(['message' => 'Unauthenticated.']);
            $this->get($path, ['Accept' => 'text/html'])->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
            $this->getJson($path)->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        }
    }

    public function test_the_logged_out_admin_request_without_the_header_is_recorded(): void
    {
        $this->get('/api/admin/users')->assertUnauthorized();

        $event = SecurityEvent::sole();
        $this->assertSame(['unauthenticated_admin_route', '/api/admin/users', 1], [$event->type, $event->path, $event->count]);
    }

    public function test_other_api_errors_keep_their_json_shape_without_the_header(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);

        // 404, 405, 422: the framework's JSON, as with the header (checked by message: with APP_DEBUG
        // on, exception responses also carry the trace).
        $this->get('/api/no-such-route')->assertNotFound()->assertJsonPath('message', 'The route api/no-such-route could not be found.');
        $this->delete('/api/modules')->assertStatus(405)->assertJsonStructure(['message']);
        $this->post('/api/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);

        // 403 from the role middleware.
        $this->actingAs($user)->get('/api/admin/users')->assertForbidden()->assertJsonPath('message', 'Forbidden.');
        $this->app['auth']->forgetGuards();

        // 429 after the login limit.
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong-password'])->assertUnauthorized();
        }
        $this->post('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong-password'])->assertStatus(429)->assertJsonStructure(['message']);

        // 419: a login from outside the frontend gets no session.
        $this->withServerVariables([])->post('/api/register', [])->assertStatus(419)->assertJsonPath('message', 'CSRF token mismatch.');
    }
}
