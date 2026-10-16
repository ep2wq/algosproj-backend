<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Completion;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Problem;
use App\Models\TestCase as ProblemTestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $user;

    // Normal users the user routes act on; recreated with the content.
    private User $target;

    private User $bannedTarget;

    private Module $module;

    private Module $emptyModule;

    private Lesson $lesson;

    private Lesson $emptyLesson;

    private Problem $problem;

    private ProblemTestCase $testCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'owner']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create();
        $this->setUpContent();
    }

    private function setUpTargets(): void
    {
        $this->target = User::factory()->create(['points' => 20]);
        $this->bannedTarget = User::factory()->create(['banned_at' => now(), 'ban_reason' => 'Spam']);
    }

    // A module with a lesson with a problem with one visible and one hidden test, plus an
    // empty module and an empty lesson.
    private function setUpContent(): void
    {
        $this->module = Module::create(['slug' => 'basics', 'title' => 'Basics', 'track' => 'basics', 'sort_order' => 1]);
        $this->emptyModule = Module::create(['slug' => 'empty', 'title' => 'Empty', 'track' => 'algorithms', 'sort_order' => 2]);
        $this->lesson = $this->module->lessons()->create(['slug' => 'warm-up', 'title' => 'Warm-up', 'body' => 'Old text', 'sort_order' => 1]);
        $this->emptyLesson = $this->module->lessons()->create(['slug' => 'no-problems', 'title' => 'No problems', 'sort_order' => 2]);
        $this->problem = $this->lesson->problems()->create(['slug' => 'warm-up', 'title' => 'Double', 'description' => 'Double it', 'difficulty' => 'easy', 'points' => 10]);
        $this->testCase = $this->problem->testCases()->create(['input' => 'VISIBLE-IN', 'expected_output' => 'VISIBLE-OUT', 'is_hidden' => false]);
        $this->problem->testCases()->create(['input' => 'HIDDEN-IN', 'expected_output' => 'HIDDEN-OUT', 'is_hidden' => true]);
        $this->setUpTargets();
    }

    // Each request starts clean: no token from an earlier request and no cached user.
    private function send(string $method, string $uri, array $data = [], ?User $as = null): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        if ($as) {
            $this->issuedTokens[] = $token = $as->createToken('api')->plainTextToken;
            $this->withToken($token);
        }

        return $this->json($method, $uri, $data);
    }

    // Every token send() created, to check that none ends up in a log.
    private array $issuedTokens = [];

    private function asAdmin(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->send($method, $uri, $data, $this->admin);
    }

    // Every admin route with a valid request, the status an admin gets and the status an owner gets.
    public function routes(): array
    {
        $m = $this->module->id;
        $l = $this->lesson->id;
        $p = $this->problem->id;
        $t = $this->testCase->id;
        $u = $this->target->id;

        return [
            ['GET', '/api/admin/modules', [], 200, 200],
            ['POST', '/api/admin/modules', ['title' => 'Graphs', 'slug' => 'graphs', 'sort_order' => 3], 201, 201],
            ['PUT', "/api/admin/modules/$m", ['title' => 'Start here', 'slug' => 'basics', 'sort_order' => 1], 200, 200],
            ['DELETE', "/api/admin/modules/{$this->emptyModule->id}", [], 204, 204],
            ['GET', "/api/admin/modules/$m/lessons", [], 200, 200],
            ['POST', '/api/admin/lessons', ['module_id' => $m, 'title' => 'New', 'slug' => 'new-lesson', 'body' => 'Text', 'sort_order' => 3], 201, 201],
            ['GET', "/api/admin/lessons/$l", [], 200, 200],
            ['PUT', "/api/admin/lessons/$l", ['module_id' => $m, 'title' => 'Warm-up!', 'slug' => 'warm-up', 'body' => 'New text', 'sort_order' => 1], 200, 200],
            ['DELETE', "/api/admin/lessons/{$this->emptyLesson->id}", [], 204, 204],
            ['GET', '/api/admin/problems', [], 200, 200],
            ['POST', '/api/admin/problems', ['title' => 'Triple', 'slug' => 'triple', 'description' => 'Triple it', 'difficulty' => 'hard'], 201, 201],
            ['GET', "/api/admin/problems/$p", [], 200, 200],
            ['PUT', "/api/admin/problems/$p", ['lesson_id' => $l, 'title' => 'Double it', 'slug' => 'warm-up', 'description' => 'Double', 'difficulty' => 'easy'], 200, 200],
            ['DELETE', "/api/admin/problems/$p", [], 204, 204],
            ['GET', "/api/admin/problems/$p/test-cases", [], 200, 200],
            ['POST', "/api/admin/problems/$p/test-cases", ['input' => '1', 'expected_output' => '2', 'is_hidden' => true], 201, 201],
            ['PUT', "/api/admin/problems/$p/test-cases/$t", ['input' => '3', 'expected_output' => '6', 'is_hidden' => false], 200, 200],
            ['DELETE', "/api/admin/problems/$p/test-cases/$t", [], 204, 204],
            ['GET', '/api/admin/users', [], 200, 200],
            ['GET', "/api/admin/users/$u", [], 200, 200],
            ['POST', "/api/admin/users/$u/ban", ['reason' => 'Spam'], 200, 200],
            ['POST', "/api/admin/users/{$this->bannedTarget->id}/unban", [], 200, 200],
            ['POST', "/api/admin/users/$u/logout-everywhere", [], 200, 200],
            ['PUT', "/api/admin/users/$u/role", ['role' => 'admin'], 403, 200],
            ['POST', "/api/admin/users/$u/points", ['amount' => 5, 'reason' => 'Bonus'], 403, 200],
            ['POST', "/api/admin/users/$u/reset-progress", [], 403, 200],
            ['DELETE', "/api/admin/users/$u", ['confirm_email' => $this->target->email], 403, 204],
            ['GET', "/api/admin/users/$u/activity", [], 200, 200],
            ['GET', '/api/admin/audit-log', [], 403, 200],
            ['GET', '/api/admin/activity', [], 403, 200],
        ];
    }

    private function snapshot(): array
    {
        return [
            Module::all()->toArray(), Lesson::all()->toArray(), Problem::all()->toArray(), ProblemTestCase::all()->toArray(),
            DB::table('users')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(), Completion::all()->toArray(),
        ];
    }

    public function test_the_route_list_covers_every_admin_route(): void
    {
        $registered = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/admin'))
            ->map(fn ($route) => $route->methods()[0].' /'.$route->uri())
            ->sort()->values()->all();

        $tested = collect($this->routes())
            ->map(fn ($route) => $this->pattern($route[0], $route[1]))
            ->sort()->values()->all();

        $this->assertSame($registered, $tested);
    }

    // "PUT /api/admin/modules/5" -> "PUT /api/admin/modules/{module}", as the router names it.
    private function pattern(string $method, string $uri): string
    {
        return $method.' '.preg_replace(
            ['/modules\/\d+/', '/lessons\/\d+/', '/problems\/\d+/', '/test-cases\/\d+/', '/users\/\d+/'],
            ['modules/{module}', 'lessons/{lesson}', 'problems/{problem}', 'test-cases/{testCase}', 'users/{user}'],
            $uri,
        );
    }

    // The audit action every write route must log. A new write route fails the test below until
    // it is added here, and then fails the one after it until it writes that row.
    private const AUDIT_ACTIONS = [
        'POST /api/admin/modules' => 'module.create',
        'PUT /api/admin/modules/{module}' => 'module.update',
        'DELETE /api/admin/modules/{module}' => 'module.delete',
        'POST /api/admin/lessons' => 'lesson.create',
        'PUT /api/admin/lessons/{lesson}' => 'lesson.update',
        'DELETE /api/admin/lessons/{lesson}' => 'lesson.delete',
        'POST /api/admin/problems' => 'problem.create',
        'PUT /api/admin/problems/{problem}' => 'problem.update',
        'DELETE /api/admin/problems/{problem}' => 'problem.delete',
        'POST /api/admin/problems/{problem}/test-cases' => 'test_case.create',
        'PUT /api/admin/problems/{problem}/test-cases/{testCase}' => 'test_case.update',
        'DELETE /api/admin/problems/{problem}/test-cases/{testCase}' => 'test_case.delete',
        'POST /api/admin/users/{user}/ban' => 'user.ban',
        'POST /api/admin/users/{user}/unban' => 'user.unban',
        'POST /api/admin/users/{user}/logout-everywhere' => 'user.logout_everywhere',
        'PUT /api/admin/users/{user}/role' => 'user.role_change',
        'POST /api/admin/users/{user}/points' => 'user.points_adjust',
        'POST /api/admin/users/{user}/reset-progress' => 'user.reset_progress',
        'DELETE /api/admin/users/{user}' => 'user.delete',
    ];

    public function test_every_admin_write_route_has_an_audit_action(): void
    {
        $writeRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/admin') && $route->methods()[0] !== 'GET')
            ->map(fn ($route) => $route->methods()[0].' /'.$route->uri())
            ->sort()->values()->all();

        $this->assertSame($writeRoutes, collect(self::AUDIT_ACTIONS)->keys()->sort()->values()->all());
    }

    public function test_every_successful_admin_write_logs_one_row_with_its_action_and_actor(): void
    {
        foreach ($this->routes() as $i => $ignored) {
            [$method, $uri, $data, $adminStatus, $ownerStatus] = $this->routes()[$i];
            if ($method === 'GET') {
                continue;
            }
            // Admins where they are allowed, otherwise the owner.
            [$actor, $status] = $adminStatus === 403 ? [$this->owner, $ownerStatus] : [$this->admin, $adminStatus];
            $before = AdminAuditLog::max('id') ?? 0;

            $this->send($method, $uri, $data, $actor)->assertStatus($status);

            $rows = AdminAuditLog::where('id', '>', $before)->get();
            $this->assertCount(1, $rows, "$method $uri");
            $this->assertSame(self::AUDIT_ACTIONS[$this->pattern($method, $uri)], $rows[0]->action, "$method $uri");
            $this->assertSame($actor->id, $rows[0]->actor_id);
            $this->assertSame($actor->email, $rows[0]->actor_email);
            $this->assertSame('127.0.0.1', $rows[0]->ip);
            $this->assertNotNull($rows[0]->target_id);
            $this->assertNotNull($rows[0]->target_label);

            $this->refreshContent();
        }

        // No row holds a password, a password hash, a token, a lesson body, a test or the delete confirmation field.
        $all = AdminAuditLog::all()->toJson();
        foreach (['password', $this->owner->password, 'Old text', 'New text', 'VISIBLE-IN', 'confirm_email'] as $secret) {
            $this->assertStringNotContainsString($secret, $all);
        }
        $this->assertNotEmpty($this->issuedTokens);
        foreach ($this->issuedTokens as $token) {
            $this->assertStringNotContainsString(explode('|', $token)[1], $all);
            $this->assertStringNotContainsString(hash('sha256', explode('|', $token)[1]), $all);
        }
    }

    public function test_rejected_admin_writes_log_nothing(): void
    {
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->send('POST', "/api/admin/users/{$otherAdmin->id}/ban", ['reason' => 'x'], $this->admin)->assertForbidden();
        $this->send('POST', "/api/admin/users/{$this->target->id}/points", ['amount' => 5, 'reason' => 'x'], $this->admin)->assertForbidden();
        $this->send('POST', "/api/admin/users/{$this->owner->id}/ban", ['reason' => 'x'], $this->owner)->assertForbidden();
        $this->send('POST', "/api/admin/users/{$this->bannedTarget->id}/ban", ['reason' => 'x'], $this->admin)->assertStatus(409);
        $this->asAdmin('DELETE', "/api/admin/modules/{$this->module->id}")->assertStatus(409);
        $this->asAdmin('POST', '/api/admin/modules', [])->assertUnprocessable();
        $this->send('POST', "/api/admin/users/{$this->target->id}/points", ['amount' => -999, 'reason' => 'x'], $this->owner)->assertUnprocessable();
        $this->send('DELETE', "/api/admin/users/{$this->target->id}", ['confirm_email' => 'wrong@example.com'], $this->owner)->assertUnprocessable();
        $this->send('POST', '/api/admin/modules', ['title' => 'X', 'slug' => 'x', 'sort_order' => 1], $this->user)->assertForbidden();

        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_an_update_logs_which_fields_changed_with_short_values_and_never_the_content(): void
    {
        $longTitle = str_repeat('t', 255);
        $this->asAdmin('PUT', "/api/admin/lessons/{$this->lesson->id}", ['module_id' => $this->module->id, 'title' => $longTitle, 'slug' => 'warm-up', 'body' => 'SECRET BODY', 'sort_order' => 1])->assertOk();
        $this->asAdmin('PUT', "/api/admin/problems/{$this->problem->id}/test-cases/{$this->testCase->id}", ['input' => 'SECRET-IN', 'expected_output' => 'SECRET-OUT', 'is_hidden' => true])->assertOk();

        [$lessonRow, $testRow] = AdminAuditLog::orderBy('id')->get();
        $this->assertSame(['title' => ['old' => 'Warm-up', 'new' => str_repeat('t', 200)], 'body' => 'changed'], $lessonRow->details['changes']);
        $this->assertSame($longTitle, $lessonRow->target_label);
        $this->assertSame(['input' => 'changed', 'expected_output' => 'changed', 'is_hidden' => ['old' => false, 'new' => true]], $testRow->details['changes']);
        $this->assertStringNotContainsString('SECRET', AdminAuditLog::all()->toJson());
    }

    public function test_the_artisan_command_logs_a_console_row(): void
    {
        $this->artisan('user:make-admin', ['email' => $this->user->email])->assertExitCode(0);

        $row = AdminAuditLog::sole();
        $this->assertNull($row->actor_id);
        $this->assertSame('console', $row->actor_email);
        $this->assertSame('user.role_change', $row->action);
        $this->assertSame(['old' => 'user', 'new' => 'admin'], $row->details);
        $this->assertSame($this->user->email, $row->target_label);
    }

    public function test_the_audit_log_is_newest_first_filtered_paginated_and_read_only(): void
    {
        $this->asAdmin('POST', '/api/admin/modules', ['title' => 'A', 'slug' => 'a', 'sort_order' => 1])->assertCreated();
        $this->send('POST', "/api/admin/users/{$this->target->id}/points", ['amount' => 5, 'reason' => 'x'], $this->owner)->assertOk();
        $this->artisan('user:make-admin', ['email' => $this->user->email])->assertExitCode(0);

        $get = fn (string $query = '') => $this->send('GET', "/api/admin/audit-log$query", [], $this->owner)->assertOk();
        $this->assertSame(['user.role_change', 'user.points_adjust', 'module.create'], array_column($get()->json('data'), 'action'));
        $this->assertSame(['module.create'], array_column($get('?action=module.create')->json('data'), 'action'));
        $this->assertSame(['user.points_adjust'], array_column($get("?actor={$this->owner->email}")->json('data'), 'action'));
        $this->assertSame(['module.create'], array_column($get("?actor={$this->admin->id}")->json('data'), 'action'));
        $this->assertSame(['user.role_change'], array_column($get('?actor=console')->json('data'), 'action'));
        $today = now()->format('Y-m-d');
        $this->assertCount(3, $get("?from=$today&to=$today")->json('data'));
        $this->assertCount(0, $get('?to=2000-01-01')->json('data'));
        $this->send('GET', '/api/admin/audit-log?from=2026-02-01&to=2026-01-01', [], $this->owner)->assertUnprocessable()->assertJsonValidationErrors('to');

        AdminAuditLog::insert(array_fill(0, 60, ['actor_email' => 'console', 'action' => 'x', 'created_at' => now()]));
        $get()->assertJsonPath('per_page', 50)->assertJsonPath('total', 63)->assertJsonCount(50, 'data');

        // Nothing but GET touches the audit log.
        $methods = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'audit'))->flatMap->methods()->unique()->values()->all();
        $this->assertSame(['GET', 'HEAD'], $methods);
    }

    public function test_every_admin_route_is_401_for_guests_403_for_users_and_works_for_admins_and_owners(): void
    {
        for ($i = 0; $i < count($this->routes()); $i++) {
            // Built again each time: the content and target users (and their ids) are recreated.
            [$method, $uri, $data, $adminStatus] = $this->routes()[$i];
            $before = $this->snapshot();

            $this->send($method, $uri, $data)->assertStatus(401);
            $this->send($method, $uri, $data, $this->user)->assertStatus(403);
            $this->assertSame($before, $this->snapshot(), "$method $uri changed data for a non-admin");

            $this->asAdmin($method, $uri, $data)->assertStatus($adminStatus);
            if ($adminStatus === 403) {
                $this->assertSame($before, $this->snapshot(), "$method $uri changed data for an admin on an owner-only route");
            }

            $this->refreshContent();
            [$method, $uri, $data, , $ownerStatus] = $this->routes()[$i];
            $this->send($method, $uri, $data, $this->owner)->assertStatus($ownerStatus);

            // Start the next route from the original content.
            $this->refreshContent();
        }
    }

    private function refreshContent(): void
    {
        ProblemTestCase::query()->delete();
        Problem::query()->delete();
        Lesson::query()->delete();
        Module::query()->delete();
        User::whereKey([$this->target->id, $this->bannedTarget->id])->delete();
        $this->setUpContent();
    }

    public function test_a_non_admin_gets_403_even_for_ids_that_do_not_exist(): void
    {
        $this->send('GET', '/api/admin/problems/99999', [], $this->user)->assertStatus(403);
        $this->send('PUT', '/api/admin/problems/99999/test-cases/99999', [], $this->user)->assertStatus(403);
        $this->send('GET', '/api/admin/problems/99999')->assertStatus(401);
    }

    public function test_admin_creates_a_module_lesson_problem_and_tests_and_the_public_pages_show_them(): void
    {
        $moduleId = $this->asAdmin('POST', '/api/admin/modules', ['title' => 'Graphs', 'slug' => 'graphs', 'sort_order' => 5])
            ->assertCreated()->assertJsonPath('track', 'algorithms')->json('id');
        $lessonId = $this->asAdmin('POST', '/api/admin/lessons', ['module_id' => $moduleId, 'title' => 'BFS', 'slug' => 'bfs', 'body' => "## Example\nWalk the graph.", 'sort_order' => 1])
            ->assertCreated()->json('id');
        // points sent by the client are ignored; they follow difficulty.
        $problemId = $this->asAdmin('POST', '/api/admin/problems', ['lesson_id' => $lessonId, 'title' => 'Shortest path', 'slug' => 'shortest-path', 'description' => 'Find it', 'difficulty' => 'medium', 'points' => 999])
            ->assertCreated()->assertJsonPath('points', 20)->json('id');
        $this->asAdmin('POST', "/api/admin/problems/$problemId/test-cases", ['input' => "  1 2\n", 'expected_output' => '3', 'is_hidden' => false])
            ->assertCreated()->assertJsonPath('input', "  1 2\n");
        $this->asAdmin('POST', "/api/admin/problems/$problemId/test-cases", ['input' => 'SECRET-IN', 'expected_output' => 'SECRET-OUT', 'is_hidden' => true])
            ->assertCreated();

        // The admin API returns hidden test cases.
        $this->asAdmin('GET', "/api/admin/problems/$problemId/test-cases")->assertOk()
            ->assertJsonCount(2)->assertJsonPath('1.expected_output', 'SECRET-OUT')->assertJsonPath('1.is_hidden', true);

        $this->flushHeaders();
        $this->getJson('/api/modules')->assertOk()->assertJsonPath('2.slug', 'graphs')->assertJsonPath('2.lessons.0.slug', 'bfs');
        $this->getJson('/api/lessons/bfs')->assertOk()
            ->assertJsonPath('body', "## Example\nWalk the graph.")
            ->assertJsonPath('problems.0.points', 20)
            ->assertJsonCount(1, 'problems.0.test_cases')
            ->assertJsonPath('problems.0.test_cases.0.input', "  1 2\n");
        $this->getJson('/api/challenges/shortest-path')->assertOk()->assertJsonPath('topic.slug', 'graphs');
        $this->assertContains('shortest-path', array_column($this->getJson('/api/challenges')->json(), 'slug'));
    }

    public function test_admin_edits_show_on_the_public_pages_at_once(): void
    {
        $this->asAdmin('PUT', "/api/admin/lessons/{$this->lesson->id}", ['module_id' => $this->module->id, 'title' => 'Warm-up again', 'slug' => 'warm-up', 'body' => 'New text', 'sort_order' => 1])
            ->assertOk();
        $this->asAdmin('PUT', "/api/admin/problems/{$this->problem->id}", ['lesson_id' => $this->lesson->id, 'title' => 'Double it now', 'slug' => 'warm-up', 'description' => 'Double', 'difficulty' => 'hard'])
            ->assertOk()->assertJsonPath('points', 30);

        $this->flushHeaders();
        $this->getJson('/api/lessons/warm-up')->assertOk()
            ->assertJsonPath('title', 'Warm-up again')
            ->assertJsonPath('body', 'New text')
            ->assertJsonPath('problems.0.title', 'Double it now')
            ->assertJsonPath('problems.0.points', 30);
    }

    public function test_a_problem_can_be_standalone(): void
    {
        $this->asAdmin('POST', '/api/admin/problems', ['title' => 'Alone', 'slug' => 'alone', 'description' => 'On its own', 'difficulty' => 'easy'])
            ->assertCreated()->assertJsonPath('lesson_id', null)->assertJsonPath('points', 10);

        // Moving a problem out of its lesson.
        $this->asAdmin('PUT', "/api/admin/problems/{$this->problem->id}", ['lesson_id' => null, 'title' => 'Double', 'slug' => 'warm-up', 'description' => 'Double it', 'difficulty' => 'easy'])
            ->assertOk()->assertJsonPath('lesson_id', null);

        $this->flushHeaders();
        $this->getJson('/api/challenges/alone')->assertOk()->assertJsonPath('topic', null);
    }

    public function test_lesson_show_includes_the_full_body(): void
    {
        $this->asAdmin('GET', "/api/admin/lessons/{$this->lesson->id}")->assertOk()
            ->assertJsonPath('body', 'Old text')->assertJsonPath('module_id', $this->module->id);
    }

    public function test_slugs_must_be_lowercase_letters_digits_and_hyphens(): void
    {
        foreach (['Upper', 'has space', 'under_score', 'dot.ted', 'ümlaut', ''] as $slug) {
            $this->asAdmin('POST', '/api/admin/modules', ['title' => 'X', 'slug' => $slug, 'sort_order' => 1])
                ->assertUnprocessable()->assertJsonValidationErrors('slug');
            $this->asAdmin('POST', '/api/admin/lessons', ['module_id' => $this->module->id, 'title' => 'X', 'slug' => $slug, 'sort_order' => 1])
                ->assertUnprocessable()->assertJsonValidationErrors('slug');
            $this->asAdmin('POST', '/api/admin/problems', ['title' => 'X', 'slug' => $slug, 'description' => 'D', 'difficulty' => 'easy'])
                ->assertUnprocessable()->assertJsonValidationErrors('slug');
        }

        $this->asAdmin('POST', '/api/admin/modules', ['title' => 'X', 'slug' => 'dp-2', 'sort_order' => 1])->assertCreated();
    }

    public function test_slugs_must_be_unique(): void
    {
        $this->asAdmin('POST', '/api/admin/modules', ['title' => 'X', 'slug' => 'basics', 'sort_order' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->asAdmin('PUT', "/api/admin/modules/{$this->emptyModule->id}", ['title' => 'X', 'slug' => 'basics', 'sort_order' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->asAdmin('POST', '/api/admin/lessons', ['module_id' => $this->module->id, 'title' => 'X', 'slug' => 'warm-up', 'sort_order' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->asAdmin('PUT', "/api/admin/lessons/{$this->emptyLesson->id}", ['module_id' => $this->module->id, 'title' => 'X', 'slug' => 'warm-up', 'sort_order' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->asAdmin('POST', '/api/admin/problems', ['title' => 'X', 'slug' => 'warm-up', 'description' => 'D', 'difficulty' => 'easy'])
            ->assertUnprocessable()->assertJsonValidationErrors('slug');

        // Keeping its own slug is fine.
        $this->asAdmin('PUT', "/api/admin/modules/{$this->module->id}", ['title' => 'Basics', 'slug' => 'basics', 'sort_order' => 1])->assertOk();
    }

    public function test_difficulty_must_be_easy_medium_or_hard(): void
    {
        $this->asAdmin('POST', '/api/admin/problems', ['title' => 'X', 'slug' => 'x', 'description' => 'D', 'difficulty' => 'extreme'])
            ->assertUnprocessable()->assertJsonValidationErrors('difficulty');
        $this->asAdmin('PUT', "/api/admin/problems/{$this->problem->id}", ['title' => 'X', 'slug' => 'warm-up', 'description' => 'D', 'difficulty' => 'Easy'])
            ->assertUnprocessable()->assertJsonValidationErrors('difficulty');
    }

    public function test_required_fields_lengths_and_references_are_checked(): void
    {
        $this->asAdmin('POST', '/api/admin/modules', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'slug', 'sort_order']);
        $this->asAdmin('POST', '/api/admin/modules', ['title' => str_repeat('a', 256), 'slug' => str_repeat('a', 101), 'sort_order' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'slug', 'sort_order']);
        $this->asAdmin('POST', '/api/admin/lessons', ['module_id' => 99999, 'title' => 'X', 'slug' => 'x', 'body' => str_repeat('a', 20001), 'sort_order' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors(['module_id', 'body']);
        $this->asAdmin('POST', '/api/admin/lessons', ['module_id' => $this->module->id, 'title' => 'X', 'slug' => 'x', 'body' => str_repeat('a', 20000), 'sort_order' => 1])
            ->assertCreated();
        $this->asAdmin('POST', '/api/admin/problems', ['lesson_id' => 99999, 'title' => 'X', 'slug' => 'x', 'description' => str_repeat('a', 5001), 'difficulty' => 'easy'])
            ->assertUnprocessable()->assertJsonValidationErrors(['lesson_id', 'description']);
        $this->asAdmin('POST', "/api/admin/problems/{$this->problem->id}/test-cases", ['input' => '1'])
            ->assertUnprocessable()->assertJsonValidationErrors(['expected_output', 'is_hidden']);
    }

    public function test_a_test_field_may_be_at_most_20000_bytes(): void
    {
        $uri = "/api/admin/problems/{$this->problem->id}/test-cases";

        // 10,001 two-byte characters: under 20,000 characters but over 20,000 bytes.
        $this->asAdmin('POST', $uri, ['input' => str_repeat('é', 10001), 'expected_output' => '1', 'is_hidden' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('input');
        $this->asAdmin('POST', $uri, ['input' => '1', 'expected_output' => str_repeat('a', 20001), 'is_hidden' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('expected_output');
        $this->asAdmin('POST', $uri, ['input' => str_repeat('a', 20000), 'expected_output' => '1', 'is_hidden' => true])
            ->assertCreated();
    }

    public function test_a_problems_tests_may_total_at_most_25000_bytes(): void
    {
        $uri = "/api/admin/problems/{$this->problem->id}/test-cases";
        // The two existing tests hold 41 bytes.
        $this->asAdmin('POST', $uri, ['input' => str_repeat('a', 20000), 'expected_output' => '1', 'is_hidden' => true])->assertCreated();

        $this->asAdmin('POST', $uri, ['input' => str_repeat('b', 4958), 'expected_output' => '1', 'is_hidden' => true])
            ->assertCreated();
        $this->assertSame(25000, $this->problem->testCases->sum(fn ($t) => strlen($t->input) + strlen($t->expected_output)));

        $this->asAdmin('POST', $uri, ['input' => '', 'expected_output' => '1', 'is_hidden' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('test_cases')
            ->assertJsonPath('message', 'All test inputs and expected outputs of a problem may total at most 25,000 bytes; this change would make 25001.');

        // Updating replaces the old case's bytes: growing an existing case past the total fails too.
        $this->asAdmin('PUT', "$uri/{$this->testCase->id}", ['input' => 'VISIBLE-IN!', 'expected_output' => 'VISIBLE-OUT', 'is_hidden' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('test_cases');
        $this->asAdmin('PUT', "$uri/{$this->testCase->id}", ['input' => 'VISIBLE-I', 'expected_output' => 'VISIBLE-OUT', 'is_hidden' => true])
            ->assertOk();
    }

    public function test_a_problem_may_have_at_most_50_tests(): void
    {
        for ($i = 3; $i <= 50; $i++) {
            $this->problem->testCases()->create(['input' => (string) $i, 'expected_output' => (string) $i, 'is_hidden' => true]);
        }

        $this->asAdmin('POST', "/api/admin/problems/{$this->problem->id}/test-cases", ['input' => '51', 'expected_output' => '51', 'is_hidden' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('test_cases')
            ->assertJsonPath('message', 'A problem can have at most 50 test cases.');
        $this->assertSame(50, $this->problem->testCases()->count());
    }

    public function test_deleting_a_module_with_lessons_is_refused(): void
    {
        $this->asAdmin('DELETE', "/api/admin/modules/{$this->module->id}")
            ->assertStatus(409)->assertJsonPath('message', 'This module has lessons. Move or delete its lessons first.');
        $this->assertModelExists($this->module);
    }

    public function test_deleting_a_lesson_with_problems_is_refused(): void
    {
        $this->asAdmin('DELETE', "/api/admin/lessons/{$this->lesson->id}")
            ->assertStatus(409)->assertJsonPath('message', 'This lesson has problems. Move or delete its problems first.');
        $this->assertModelExists($this->lesson);
    }

    public function test_deleting_a_solved_problem_is_refused_but_its_tests_can_be_deleted(): void
    {
        Completion::create(['user_id' => $this->user->id, 'problem_id' => $this->problem->id]);

        $this->asAdmin('DELETE', "/api/admin/problems/{$this->problem->id}")
            ->assertStatus(409)->assertJsonPath('message', 'Users have already solved this problem, so it cannot be deleted. Edit it instead.');
        $this->assertModelExists($this->problem);

        $this->asAdmin('DELETE', "/api/admin/problems/{$this->problem->id}/test-cases/{$this->testCase->id}")->assertNoContent();
        $this->assertModelMissing($this->testCase);
    }

    public function test_deleting_an_unsolved_problem_deletes_its_tests(): void
    {
        $this->asAdmin('DELETE', "/api/admin/problems/{$this->problem->id}")->assertNoContent();

        $this->assertModelMissing($this->problem);
        $this->assertSame(0, ProblemTestCase::count());
    }

    public function test_a_test_case_of_another_problem_is_404(): void
    {
        $other = Problem::create(['slug' => 'other', 'title' => 'Other', 'description' => 'D', 'difficulty' => 'easy', 'points' => 10]);
        $otherCase = $other->testCases()->create(['input' => 'O', 'expected_output' => 'O', 'is_hidden' => true]);
        $data = ['input' => 'X', 'expected_output' => 'X', 'is_hidden' => false];

        // Right test case, wrong problem; and the other way round.
        $this->asAdmin('PUT', "/api/admin/problems/{$other->id}/test-cases/{$this->testCase->id}", $data)->assertNotFound();
        $this->asAdmin('DELETE', "/api/admin/problems/{$other->id}/test-cases/{$this->testCase->id}")->assertNotFound();
        $this->asAdmin('PUT', "/api/admin/problems/{$this->problem->id}/test-cases/{$otherCase->id}", $data)->assertNotFound();
        $this->asAdmin('DELETE', "/api/admin/problems/{$this->problem->id}/test-cases/{$otherCase->id}")->assertNotFound();
        // A problem that does not exist.
        $this->asAdmin('GET', '/api/admin/problems/99999/test-cases')->assertNotFound();
        $this->asAdmin('PUT', "/api/admin/problems/99999/test-cases/{$this->testCase->id}", $data)->assertNotFound();

        $this->assertSame('VISIBLE-IN', $this->testCase->refresh()->input);
        $this->assertModelExists($otherCase);
    }

    public function test_hidden_tests_never_appear_in_public_responses_after_admin_edits(): void
    {
        $this->asAdmin('POST', "/api/admin/problems/{$this->problem->id}/test-cases", ['input' => 'HIDDEN-NEW-IN', 'expected_output' => 'HIDDEN-NEW-OUT', 'is_hidden' => true])->assertCreated();
        // A visible test turned hidden disappears from the public pages.
        $this->asAdmin('PUT', "/api/admin/problems/{$this->problem->id}/test-cases/{$this->testCase->id}", ['input' => 'HIDDEN-NOW-IN', 'expected_output' => 'HIDDEN-NOW-OUT', 'is_hidden' => true])->assertOk();

        foreach ([null, $this->user, $this->admin] as $as) {
            foreach (['/api/lessons/warm-up', '/api/challenges', '/api/challenges/warm-up', '/api/modules', '/api/leaderboard?limit=100'] as $uri) {
                $body = $this->send('GET', $uri, [], $as)->assertOk()->getContent();
                $this->assertStringNotContainsString('HIDDEN', $body, $uri);
                $this->assertStringNotContainsString('"role"', $body, $uri);
            }
        }
    }
}
