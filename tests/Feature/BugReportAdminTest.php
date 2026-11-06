<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\BugReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BugReportAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->owner = User::factory()->create(['role' => 'owner']);
    }

    private function fresh(): static
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this;
    }

    private function asOwner(): static
    {
        return $this->fresh()->actingAs($this->owner);
    }

    private function report(array $fields = [], bool $screenshot = false): BugReport
    {
        $path = null;
        if ($screenshot) {
            $path = Storage::disk('local')->putFileAs('bug-reports', UploadedFile::fake()->image('x.png', 10, 10), fake()->uuid().'.png');
        }
        $report = BugReport::create(['description' => 'Something broke here.', 'page_path' => '/lessons', 'included' => [], 'screenshot_path' => $path]);
        $report->forceFill($fields)->save();

        return $report;
    }

    public function test_every_bug_report_route_is_owner_only(): void
    {
        $report = $this->report([], true);
        $other = $this->report();
        $routes = [
            ['GET', '/api/admin/bug-reports', 200], ['GET', '/api/admin/bug-reports/summary', 200],
            ['GET', "/api/admin/bug-reports/{$report->id}", 200], ['PATCH', "/api/admin/bug-reports/{$report->id}", 200],
            ['GET', "/api/admin/bug-reports/{$report->id}/screenshot", 200], ['DELETE', "/api/admin/bug-reports/{$other->id}", 204],
        ];
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ($routes as [$method, $uri, $ok]) {
            $data = ['status' => 'in_progress'];
            $this->fresh()->json($method, $uri, $data)->assertUnauthorized();
            $this->fresh()->actingAs($user)->json($method, $uri, $data)->assertForbidden();
            $this->fresh()->actingAs($admin)->json($method, $uri, $data)->assertForbidden();
            $this->asOwner()->json($method, $uri, $data)->assertStatus($ok);
        }
    }

    public function test_list_is_newest_first_with_filters_and_without_the_large_parts(): void
    {
        $reporter = User::factory()->create(['email' => 'ada@example.com']);
        $old = $this->report(['created_at' => now()->subDays(3), 'user_id' => $reporter->id, 'contact_email' => 'ada@example.com', 'problem_context' => ['code' => 'x']]);
        $seen = $this->report(['created_at' => now()->subDay(), 'status' => 'fixed', 'seen_at' => now(), 'page_path' => '/challenges/100%_done']);
        $new = $this->report([], true);
        $ids = fn ($response) => array_column($response->assertOk()->json('data'), 'id');

        $all = $this->asOwner()->getJson('/api/admin/bug-reports');
        $this->assertSame([$new->id, $seen->id, $old->id], $ids($all));
        $this->assertSame(50, $all->json('per_page'));
        $first = $all->json('data.2');
        $this->assertSame(['id' => $reporter->id, 'name' => $reporter->name, 'email' => 'ada@example.com', 'role' => 'user'], $first['user']);
        $this->assertArrayNotHasKey('problem_context', $first);
        $this->assertArrayNotHasKey('screenshot_path', $first);
        $this->assertTrue($all->json('data.0.has_screenshot'));

        $this->assertSame([$seen->id], $ids($this->asOwner()->getJson('/api/admin/bug-reports?status=fixed')));
        $this->assertSame([$new->id, $old->id], $ids($this->asOwner()->getJson('/api/admin/bug-reports?seen=unseen')));
        $this->assertSame([$seen->id], $ids($this->asOwner()->getJson('/api/admin/bug-reports?search=100%25_')));
        $this->assertSame([$old->id], $ids($this->asOwner()->getJson('/api/admin/bug-reports?search=ada@')));
        $this->assertSame([$new->id], $ids($this->asOwner()->getJson('/api/admin/bug-reports?screenshot=1')));
        $this->assertSame([$seen->id, $old->id], $ids($this->asOwner()->getJson('/api/admin/bug-reports?screenshot=0')));
        $this->assertSame([$seen->id, $old->id], $ids($this->asOwner()->getJson('/api/admin/bug-reports?from='.now()->subDays(3)->toDateString().'&to='.now()->subDay()->toDateString())));
        $this->asOwner()->getJson('/api/admin/bug-reports?status=open')->assertUnprocessable();
    }

    public function test_summary_counts_new_unseen_reports_and_opening_one_marks_it_seen(): void
    {
        $report = $this->report(['problem_context' => ['code' => 'print(1)']]);
        $this->report(['status' => 'in_progress']);
        $this->report(['seen_at' => now()]);
        $this->asOwner()->getJson('/api/admin/bug-reports/summary')->assertExactJson(['unseen' => 1]);

        $detail = $this->asOwner()->getJson("/api/admin/bug-reports/{$report->id}")->assertOk();

        $this->assertSame('print(1)', $detail->json('problem_context.code'));
        $this->assertNotNull($detail->json('seen_at'));
        $this->assertArrayNotHasKey('screenshot_path', $detail->json());
        $this->asOwner()->getJson('/api/admin/bug-reports/summary')->assertExactJson(['unseen' => 0]);
        // Seen is not an audited action.
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_status_and_note_update_with_an_audit_entry_for_the_status_only(): void
    {
        $report = $this->report();

        $this->asOwner()->patchJson("/api/admin/bug-reports/{$report->id}", ['admin_note' => 'Looking at the submit flow.'])
            ->assertOk()->assertJsonPath('admin_note', 'Looking at the submit flow.')->assertJsonPath('status', 'new')
            ->assertJsonPath('handler.email', $this->owner->email);
        $this->assertNull($report->refresh()->status_changed_at);
        $this->assertSame(0, AdminAuditLog::count());

        $this->asOwner()->patchJson("/api/admin/bug-reports/{$report->id}", ['status' => 'fixed'])->assertOk()->assertJsonPath('status', 'fixed');

        $report->refresh();
        $this->assertNotNull($report->status_changed_at);
        $this->assertSame($this->owner->id, $report->handled_by);
        $audit = AdminAuditLog::sole();
        $this->assertSame(['bug_report.status', 'bug_report', $report->id, $report->id], [$audit->action, $audit->target_type, $audit->target_id, $audit->target_label]);
        $this->assertSameJson(['from' => 'new', 'to' => 'fixed'], $audit->details);
        $this->assertStringNotContainsString('Something broke', json_encode($audit->getAttributes()));

        $this->asOwner()->patchJson("/api/admin/bug-reports/{$report->id}", ['status' => 'closed'])->assertUnprocessable();
    }

    public function test_the_screenshot_is_served_as_an_image_that_is_never_sniffed_or_cached(): void
    {
        $report = $this->report([], true);

        $response = $this->asOwner()->get("/api/admin/bug-reports/{$report->id}/screenshot");

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->asOwner()->get('/api/admin/bug-reports/'.$this->report()->id.'/screenshot')->assertNotFound();
    }

    public function test_delete_removes_the_row_and_the_file_with_an_audit_entry(): void
    {
        $report = $this->report([], true);
        $path = $report->screenshot_path;

        $this->asOwner()->deleteJson("/api/admin/bug-reports/{$report->id}")->assertNoContent();

        $this->assertSame(0, BugReport::count());
        Storage::disk('local')->assertMissing($path);
        $audit = AdminAuditLog::sole();
        $this->assertSame(['bug_report.delete', $report->id], [$audit->action, $audit->target_id]);
    }

    public function test_prune_deletes_closed_reports_after_30_days_and_any_after_180_with_their_files(): void
    {
        $fixedOld = $this->report(['status' => 'fixed', 'status_changed_at' => now()->subDays(31)], true);
        $wontFixOld = $this->report(['status' => 'wont_fix', 'status_changed_at' => now()->subDays(31)]);
        $fixedRecent = $this->report(['status' => 'fixed', 'status_changed_at' => now()->subDays(29)]);
        $openOld = $this->report(['status' => 'in_progress', 'status_changed_at' => now()->subDays(60)]);
        $ancient = $this->report(['created_at' => now()->subDays(181)], true);
        $keep = $this->report(['created_at' => now()->subDays(179)]);

        $this->artisan('bugreports:prune')->assertSuccessful();

        $this->assertEqualsCanonicalizing([$fixedRecent->id, $openOld->id, $keep->id], BugReport::pluck('id')->all());
        Storage::disk('local')->assertMissing($fixedOld->screenshot_path);
        Storage::disk('local')->assertMissing($ancient->screenshot_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertNotNull($wontFixOld);
    }

    public function test_bugreports_prune_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('bugreports:prune')->assertSuccessful();
    }
}
