<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Problem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SubmitTest extends TestCase
{
    use RefreshDatabase;

    private Problem $problem;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sandbox.url' => 'http://sandbox.test',
            'services.sandbox.secret' => 'test-secret',
        ]);

        $module = Module::create(['slug' => 'algorithms', 'title' => 'Algorithms', 'track' => 'algorithms', 'sort_order' => 1]);
        $lesson = $module->lessons()->create(['slug' => 'warm-up', 'title' => 'Warm-up', 'sort_order' => 1]);
        $this->problem = $lesson->problems()->create(['title' => 'Double', 'description' => 'Double it', 'difficulty' => 'easy', 'points' => 10]);
        $this->problem->testCases()->createMany([
            ['input' => 'VISIBLE-INPUT-1', 'expected_output' => 'VISIBLE-EXPECTED-1', 'is_hidden' => false],
            ['input' => 'HIDDEN-INPUT-MARKER-1', 'expected_output' => 'HIDDEN-EXPECTED-MARKER-1', 'is_hidden' => true],
            ['input' => 'HIDDEN-INPUT-MARKER-2', 'expected_output' => 'HIDDEN-EXPECTED-MARKER-2', 'is_hidden' => true],
        ]);

        $this->user = User::factory()->create();
    }

    private function sandboxReturns(string $status, array $passed): void
    {
        $actuals = ['VISIBLE-ACTUAL-1', 'HIDDEN-ACTUAL-MARKER-1', 'HIDDEN-ACTUAL-MARKER-2'];
        $expected = ['VISIBLE-EXPECTED-1', 'HIDDEN-EXPECTED-MARKER-1', 'HIDDEN-EXPECTED-MARKER-2'];

        Http::fake(['sandbox.test/*' => Http::response([
            'status' => $status,
            'results' => array_map(fn ($i) => [
                'passed' => $passed[$i],
                'actual' => $actuals[$i],
                'expected' => $expected[$i],
                'error' => $status === 'error' ? 'HIDDEN-ERROR-MARKER-'.$i : null,
            ], [0, 1, 2]),
            'runtime_ms' => 12,
            'compile_error' => null,
        ])]);
    }

    private function submit(array $body = [])
    {
        return $this->actingAs($this->user)->postJson("/api/problems/{$this->problem->id}/submit", $body + [
            'language' => 'python',
            'code' => 'print(int(input()) * 2)',
        ]);
    }

    public function test_passing_submission_awards_points_once(): void
    {
        $this->sandboxReturns('passed', [true, true, true]);

        $this->submit()->assertOk()
            ->assertJsonPath('status', 'passed')
            ->assertJsonPath('passed_count', 3)
            ->assertJsonPath('total_count', 3)
            ->assertJsonPath('runtime_ms', 12)
            ->assertJsonPath('points_awarded', 10);

        $this->submit()->assertOk()->assertJsonPath('points_awarded', 0);

        $this->assertSame(10, $this->user->fresh()->points);
        $this->assertDatabaseCount('completions', 1);
        $this->assertDatabaseCount('submissions', 2);
    }

    public function test_failed_submission_awards_nothing(): void
    {
        $this->sandboxReturns('failed', [true, false, true]);

        $this->submit()->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('passed_count', 2)
            ->assertJsonPath('points_awarded', 0);

        $this->assertSame(0, $this->user->fresh()->points);
        $this->assertDatabaseCount('completions', 0);
        $this->assertDatabaseHas('submissions', ['user_id' => $this->user->id, 'status' => 'failed', 'passed_count' => 2, 'total_count' => 3]);
    }

    public function test_errored_submission_awards_nothing(): void
    {
        $this->sandboxReturns('error', [false, false, false]);

        $this->submit()->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('points_awarded', 0);

        $this->assertSame(0, $this->user->fresh()->points);
        $this->assertDatabaseCount('completions', 0);
        $this->assertDatabaseHas('submissions', ['status' => 'error']);
    }

    public function test_compile_error_is_returned_and_awards_nothing(): void
    {
        Http::fake(['sandbox.test/*' => Http::response([
            'status' => 'error', 'results' => [], 'runtime_ms' => 0, 'compile_error' => 'Main.java:1: error',
        ])]);

        $this->submit(['language' => 'java'])->assertOk()
            ->assertJsonPath('compile_error', 'Main.java:1: error')
            ->assertJsonPath('passed_count', 0)
            ->assertJsonPath('total_count', 3)
            ->assertJsonPath('points_awarded', 0);
    }

    public function test_response_never_contains_hidden_test_data(): void
    {
        $this->sandboxReturns('error', [false, false, false]);

        $response = $this->submit()->assertOk();

        $response->assertJsonPath('results.0.input', 'VISIBLE-INPUT-1')
            ->assertJsonPath('results.0.expected', 'VISIBLE-EXPECTED-1')
            ->assertJsonPath('results.0.actual', 'VISIBLE-ACTUAL-1')
            ->assertJsonPath('results.1', ['passed' => false])
            ->assertJsonPath('results.2', ['passed' => false]);
        foreach (['HIDDEN-INPUT-MARKER', 'HIDDEN-EXPECTED-MARKER', 'HIDDEN-ACTUAL-MARKER', 'HIDDEN-ERROR-MARKER-1', 'HIDDEN-ERROR-MARKER-2'] as $marker) {
            $this->assertStringNotContainsString($marker, $response->getContent());
        }
    }

    public function test_sandbox_request_has_secret_and_hidden_test_cases(): void
    {
        $this->sandboxReturns('passed', [true, true, true]);

        $this->submit()->assertOk();

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://sandbox.test/run'
                && $request->hasHeader('X-Sandbox-Secret', 'test-secret')
                && $request['language'] === 'python'
                && count($request['test_cases']) === 3
                && $request['test_cases'][1] === ['input' => 'HIDDEN-INPUT-MARKER-1', 'expected_output' => 'HIDDEN-EXPECTED-MARKER-1'];
        });
    }

    public function test_sandbox_connection_error_returns_503_without_submission(): void
    {
        Log::spy();
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->submit()->assertStatus(503)
            ->assertJsonPath('message', 'Code runner unavailable, try again shortly');
        $this->assertDatabaseCount('submissions', 0);
        Log::shouldHaveReceived('error')->once()->withArgs(
            fn ($message, $context) => $context === ['error' => 'Connection refused']
        );
    }

    public function test_sandbox_server_error_returns_503_without_submission(): void
    {
        Log::spy();
        Http::fake(['sandbox.test/*' => Http::response(str_repeat('x', 500), 500)]);

        $this->submit()->assertStatus(503)
            ->assertJsonPath('message', 'Code runner unavailable, try again shortly');
        $this->assertDatabaseCount('submissions', 0);
        Log::shouldHaveReceived('error')->once()->withArgs(function ($message, $context) {
            $logged = json_encode([$message, $context]);

            return $context['status'] === 500
                && $context['body'] === str_repeat('x', 300)
                && ! str_contains($logged, 'print(int(input())')
                && ! str_contains($logged, 'HIDDEN-')
                && ! str_contains($logged, 'test-secret');
        });
    }

    public function test_invalid_language_is_rejected(): void
    {
        Http::fake();

        $this->submit(['language' => 'ruby'])->assertUnprocessable()->assertJsonValidationErrors('language');
        Http::assertNothingSent();
    }

    public function test_oversized_code_is_rejected(): void
    {
        Http::fake();

        $this->submit(['code' => str_repeat('a', 65537)])->assertUnprocessable()->assertJsonValidationErrors('code');
        Http::assertNothingSent();
    }

    public function test_code_over_64_kb_in_bytes_is_rejected_even_when_under_the_character_count(): void
    {
        Http::fake();
        $code = str_repeat('é', 40000);
        $this->assertLessThan(65536, mb_strlen($code));
        $this->assertGreaterThan(65536, strlen($code));

        $this->submit(['code' => $code])->assertUnprocessable()->assertJsonValidationErrors('code');
        Http::assertNothingSent();
    }

    public function test_submit_requires_auth(): void
    {
        $this->postJson("/api/problems/{$this->problem->id}/submit", ['language' => 'python', 'code' => 'x'])
            ->assertUnauthorized();
    }

    public function test_eleventh_submission_in_a_minute_is_throttled(): void
    {
        $this->sandboxReturns('failed', [false, false, false]);

        for ($i = 0; $i < 10; $i++) {
            $this->submit()->assertOk();
        }

        $this->submit()->assertStatus(429);
    }
}
