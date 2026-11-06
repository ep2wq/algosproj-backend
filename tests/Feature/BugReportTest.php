<?php

namespace Tests\Feature;

use App\Models\BugReport;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BugReportTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = ['account' => true, 'diagnostics' => true, 'screenshot' => true, 'problem' => true];

    private const NONE = ['account' => false, 'diagnostics' => false, 'screenshot' => false, 'problem' => false];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function fresh(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
    }

    private function payload(array $included = self::ALL, array $extra = []): array
    {
        return $extra + [
            'description' => 'The submit button does nothing on the binary search problem.',
            'page_path' => '/challenges/binary-search?tab=code#top',
            'page_title' => 'Binary search - AlgosLab',
            'app_version' => '1.4.0',
            'language' => 'en',
            'theme' => 'dark',
            'viewport' => '1280x800',
            'timezone' => 'Asia/Riyadh',
            'error_context' => json_encode(['status' => 503, 'path' => '/api/problems/01a1792e-257b-7368-9b50-7f372b2a5c21/submit?x=1', 'code' => 'runner_unavailable', 'message' => 'Code runner unavailable']),
            'failed_requests' => json_encode([['method' => 'post', 'path' => '/api/problems/01a1792e-257b-7368-9b50-7f372b2a5c21/submit', 'status' => 503, 'time' => '2026-11-06T10:00:00Z', 'code' => 'runner_unavailable', 'message' => 'Code runner unavailable']]),
            'console_errors' => json_encode([['message' => 'TypeError: x is undefined', 'time' => '2026-11-06T10:00:01Z']]),
            'problem_context' => json_encode(['slug' => 'binary-search', 'language' => 'python', 'code' => "key = 5\nprint(key)", 'result' => ['status' => 'error', 'passed_count' => 0, 'total_count' => 4]]),
            'included' => json_encode($included),
        ];
    }

    private function send(array $data, array $headers = [])
    {
        return $this->withHeaders($headers + ['User-Agent' => 'TestBrowser/1.0'])->post('/api/bug-reports', $data, ['Accept' => 'application/json']);
    }

    private function png(int $width = 10, int $height = 10, string $name = 'shot.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    public function test_a_logged_out_report_stores_every_field_and_answers_201_with_the_id_only(): void
    {
        $response = $this->send($this->payload(self::ALL, ['contact_email' => 'visitor@example.com']));

        $response->assertCreated();
        $report = BugReport::sole();
        $this->assertSame(['id' => $report->id], $response->json());
        $this->assertSame([
            'user_id' => null, 'contact_email' => 'visitor@example.com',
            'description' => 'The submit button does nothing on the binary search problem.', 'status' => 'new',
            'page_path' => '/challenges/binary-search', 'page_title' => 'Binary search - AlgosLab', 'app_version' => '1.4.0',
            'language' => 'en', 'theme' => 'dark', 'user_agent' => 'TestBrowser/1.0', 'viewport' => '1280x800', 'timezone' => 'Asia/Riyadh',
        ], $report->only(['user_id', 'contact_email', 'description', 'status', 'page_path', 'page_title', 'app_version', 'language', 'theme', 'user_agent', 'viewport', 'timezone']));
        $this->assertSameJson(['status' => 503, 'path' => '/api/problems/01a1792e-257b-7368-9b50-7f372b2a5c21/submit', 'code' => 'runner_unavailable', 'message' => 'Code runner unavailable'], $report->error_context);
        $this->assertSameJson([['method' => 'POST', 'path' => '/api/problems/01a1792e-257b-7368-9b50-7f372b2a5c21/submit', 'status' => 503, 'time' => '2026-11-06T10:00:00Z', 'code' => 'runner_unavailable', 'message' => 'Code runner unavailable']], $report->failed_requests);
        $this->assertSameJson([['message' => 'TypeError: x is undefined', 'time' => '2026-11-06T10:00:01Z']], $report->console_errors);
        // Code like key = 5 is kept as written.
        $this->assertSameJson(['slug' => 'binary-search', 'language' => 'python', 'code' => "key = 5\nprint(key)", 'result' => ['status' => 'error', 'passed_count' => 0, 'total_count' => 4]], $report->problem_context);
        // No screenshot was sent, and a logged-out visitor has no account part.
        $this->assertSameJson(['account' => false, 'diagnostics' => true, 'screenshot' => false, 'problem' => true], $report->included);
        $this->assertNull($report->screenshot_path);
        $this->assertNull($report->seen_at);
    }

    public function test_a_logged_in_report_with_the_account_part_stores_the_account_and_its_email(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->actingAs($user)->send($this->payload(self::ALL, ['contact_email' => 'other@example.com']))->assertCreated();

        $report = BugReport::sole();
        $this->assertSame([$user->id, 'ada@example.com'], [$report->user_id, $report->contact_email]);
        $this->assertTrue($report->included['account']);
    }

    public function test_optional_parts_are_not_stored_when_their_flags_are_off_even_if_sent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->send($this->payload(self::NONE, ['screenshot' => $this->png()]))->assertCreated();

        $report = BugReport::sole();
        $this->assertSame([null, null, null, null, null], [$report->user_id, $report->contact_email, $report->failed_requests, $report->console_errors, $report->problem_context]);
        $this->assertNull($report->screenshot_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSameJson(self::NONE, $report->included);
        // Always sent: the page, the device and the error.
        $this->assertSame('/challenges/binary-search', $report->page_path);
        $this->assertSame(503, $report->error_context['status']);
    }

    public function test_secrets_are_never_stored(): void
    {
        $jwt = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U';
        $text = "My password=hunter2 and Authorization: Bearer abc.def.ghi and Cookie: laravel_session=s3cr3tvalue; token: $jwt see https://x.test/reset?token=abcd1234";
        $data = $this->payload(self::ALL, [
            'description' => $text,
            'page_path' => '/reset-password?token=abcd1234&email=ada@example.com',
            'error_context' => json_encode(['status' => 500, 'path' => '/api/me?token=abcd1234', 'message' => $text, 'password' => 'hunter2', 'headers' => ['Authorization' => 'Bearer abc']]),
            'failed_requests' => json_encode([['method' => 'GET', 'path' => '/api/x?api_key=k3y', 'status' => 500, 'message' => $text, 'cookie' => 'laravel_session=s3cr3tvalue', 'body' => ['password' => 'hunter2']]]),
            'console_errors' => json_encode([['message' => $text, 'token' => $jwt]]),
            'problem_context' => json_encode(['slug' => 'binary-search', 'code' => "# Bearer abc.def.ghi\nt = '$jwt'", 'secret' => 'x', 'result' => ['message' => $text, 'xsrf' => 'x']]),
        ]);

        $this->send($data)->assertCreated();

        $row = json_encode(BugReport::sole()->getAttributes());
        foreach (['hunter2', 'abc.def.ghi', 's3cr3tvalue', $jwt, 'abcd1234', 'k3y', 'ada@example.com', '"password"', '"cookie"', '"headers"', '"body"', '"secret"', '"xsrf"', '"token"'] as $secret) {
            $this->assertStringNotContainsString($secret, $row, $secret);
        }
        $report = BugReport::sole();
        $this->assertSame('/reset-password', $report->page_path);
        $this->assertSame('/api/me', $report->error_context['path']);
        $this->assertStringContainsString('password=[redacted]', $report->description);
        $this->assertStringContainsString('Authorization: [redacted]', $report->description);
    }

    public function test_limits_cut_lists_and_messages(): void
    {
        $many = array_map(fn ($i) => ['method' => 'GET', 'path' => "/api/$i", 'status' => 500, 'message' => str_repeat('no reply ', 50)], range(1, 15));
        $code = str_repeat('a', 25000);

        $this->send($this->payload(self::ALL, [
            'failed_requests' => json_encode($many),
            'console_errors' => json_encode(array_map(fn ($i) => ['message' => "error $i"], range(1, 12))),
            'problem_context' => json_encode(['slug' => 'binary-search', 'code' => $code]),
        ]))->assertCreated();

        $report = BugReport::sole();
        $this->assertCount(10, $report->failed_requests);
        $this->assertSame('/api/6', $report->failed_requests[0]['path']);
        $this->assertSame(300, mb_strlen($report->failed_requests[0]['message']));
        $this->assertSame(['error 3', 'error 12'], [$report->console_errors[0]['message'], $report->console_errors[9]['message']]);
        $this->assertSame(20000, mb_strlen($report->problem_context['code']));
    }

    public function test_validation_and_unknown_fields(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->send(['included' => '{}'])->assertUnprocessable()->assertJsonValidationErrors(['description', 'page_path']);
        $this->send($this->payload(self::ALL, ['description' => 'short']))->assertUnprocessable()->assertJsonValidationErrors('description');
        $this->send($this->payload(self::ALL, ['description' => str_repeat('a', 2001)]))->assertUnprocessable()->assertJsonValidationErrors('description');
        $this->send($this->payload(self::ALL, ['ip' => '1.2.3.4']))->assertUnprocessable()->assertJsonValidationErrors('ip');
        $this->send($this->payload(self::ALL, ['failed_requests' => 'not json']))->assertUnprocessable()->assertJsonValidationErrors('failed_requests');
        $this->send($this->payload(self::ALL, ['theme' => 'blue', 'viewport' => 'big']))->assertUnprocessable()->assertJsonValidationErrors(['theme', 'viewport']);
        $this->assertSame(0, BugReport::count());
    }

    public function test_a_filled_honeypot_answers_like_a_success_saves_nothing_and_is_recorded(): void
    {
        $response = $this->send($this->payload(self::ALL, [SecurityEvent::HONEYPOT_FIELD => 'https://spam.example']));

        $response->assertCreated();
        $this->assertSame(['id'], array_keys($response->json()));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $response->json('id'));
        $this->assertSame(0, BugReport::count());
        $event = SecurityEvent::sole();
        $this->assertSame(['honeypot_filled', '/api/bug-reports'], [$event->type, $event->path]);
    }

    public function test_five_reports_an_hour_per_ip_and_per_account_then_429_recorded_as_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->send($this->payload(self::NONE))->assertCreated();
        }
        $this->send($this->payload(self::NONE))->assertTooManyRequests();
        $this->assertSame(5, BugReport::count());
        $event = SecurityEvent::sole();
        $this->assertSame(['rate_limited', '/api/bug-reports'], [$event->type, $event->path]);

        // The same account from five other addresses: its own limit of 5.
        $user = User::factory()->create();
        for ($i = 1; $i <= 6; $i++) {
            $this->fresh();
            $response = $this->withServerVariables(['HTTP_ORIGIN' => self::FRONTEND, 'REMOTE_ADDR' => "203.0.113.$i"])->actingAs($user)->send($this->payload(self::NONE));
            $response->assertStatus($i <= 5 ? 201 : 429);
        }
    }

    public function test_bad_screenshots_are_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent('shot.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $html = UploadedFile::fake()->createWithContent('shot.png', '<html><script>alert(1)</script></html>');
        $gif = UploadedFile::fake()->image('shot.gif', 10, 10);
        $big = UploadedFile::fake()->image('shot.png', 10, 10)->size(1600);
        $huge = $this->png(4001, 10);

        foreach (['svg' => $svg, 'html as png' => $html, 'gif' => $gif, 'oversized' => $big, 'over 4000 px' => $huge] as $name => $file) {
            $this->send($this->payload(self::ALL, ['screenshot' => $file]))->assertUnprocessable()->assertJsonValidationErrors('screenshot');
            $this->assertSame(0, BugReport::count(), $name);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_png_webp_and_jpeg_screenshots_are_stored_on_the_private_disk_with_a_random_name(): void
    {
        $webp = UploadedFile::fake()->image('page.webp', 20, 10);
        $jpeg = UploadedFile::fake()->image('page.jpg', 20, 10);

        foreach (['png' => $this->png(1600, 900, 'evil.php.png'), 'webp' => $webp, 'jpg' => $jpeg] as $ext => $file) {
            $this->fresh();
            $this->send($this->payload(self::ALL, ['screenshot' => $file]))->assertCreated();
            $report = BugReport::orderByDesc('created_at')->orderByDesc('id')->first();
            $this->assertMatchesRegularExpression('#^bug-reports/[0-9a-f-]{36}\.'.$ext.'$#', $report->screenshot_path);
            Storage::disk('local')->assertExists($report->screenshot_path);
            $this->assertTrue($report->included['screenshot']);
            // Never sent to anyone.
            $this->assertArrayNotHasKey('screenshot_path', $report->toArray());
        }
        $this->assertSame('local', BugReport::DISK);
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.local.root'));
    }

    public function test_suspicious_patterns_in_a_report_are_not_flagged(): void
    {
        $this->send($this->payload(self::ALL, ['description' => "1' OR '1'='1 union select * from users; <script>alert(1)</script> ../../etc/passwd"]))->assertCreated();

        $this->assertSame(0, SecurityEvent::count());
        $this->assertStringContainsString('<script>', BugReport::sole()->description);
    }

    public function test_a_report_keeps_working_after_its_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->send($this->payload())->assertCreated();

        $user->delete();

        $report = BugReport::sole();
        $this->assertNull($report->user_id);
        $this->assertSame($user->email, $report->contact_email);
    }
}
