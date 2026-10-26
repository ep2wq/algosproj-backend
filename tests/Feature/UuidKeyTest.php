<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Completion;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Problem;
use App\Models\Submission;
use App\Models\TestCase as ProblemTestCase;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

// Every key is a UUIDv7 string (docs/uuid-decision.md). These tests pin what the change must keep:
// foreign keys and cascades, 404 for bad ids, authorization, sessions, and orders that never follow the id.
class UuidKeyTest extends TestCase
{
    use RefreshDatabase;

    private const UNKNOWN = '0199a000-0000-7000-8000-000000000000';

    private Module $module;

    private Lesson $lesson;

    private Problem $problem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Module::create(['slug' => 'basics', 'title' => 'Basics', 'track' => 'basics', 'sort_order' => 1]);
        $this->lesson = Lesson::create(['module_id' => $this->module->id, 'slug' => 'warm-up', 'title' => 'Warm-up', 'body' => 'Text', 'sort_order' => 1]);
        $this->problem = $this->problem('warm-up', $this->lesson);
    }

    private function problem(string $slug, ?Lesson $lesson = null): Problem
    {
        return Problem::create(['lesson_id' => $lesson?->id, 'slug' => $slug, 'title' => $slug, 'description' => 'D', 'difficulty' => 'easy', 'points' => 10]);
    }

    private function isV7(?string $id): bool
    {
        return Str::isUuid($id) && $id[14] === '7';
    }

    public function test_every_model_key_is_a_time_ordered_uuid(): void
    {
        $user = User::factory()->create();
        $rows = [
            $user, $this->module, $this->lesson, $this->problem,
            $this->problem->testCases()->create(['input' => '1', 'expected_output' => '1', 'is_hidden' => false, 'position' => 1]),
            Submission::create(['user_id' => $user->id, 'problem_id' => $this->problem->id, 'language' => 'python', 'code' => 'x', 'status' => 'failed', 'passed_count' => 0, 'total_count' => 1]),
            Completion::create(['user_id' => $user->id, 'problem_id' => $this->problem->id, 'completed_at' => now()]),
        ];
        AdminAuditLog::record($user, 'x', $user, 'x');
        UserActivityLog::write($user, $user->email, 'login');
        $rows[] = AdminAuditLog::sole();
        $rows[] = UserActivityLog::sole();

        foreach ($rows as $row) {
            $this->assertTrue($this->isV7($row->getKey()), $row::class);
            $this->assertSame('string', $row->getKeyType());
            $this->assertFalse($row->getIncrementing());
        }
        // Foreign keys hold the same strings.
        $this->assertSame($user->id, AdminAuditLog::sole()->target_id);
        $this->assertSame($this->module->id, $this->lesson->fresh()->module_id);

        // v7: ids made one after another sort in the order they were made.
        $ids = User::factory()->count(20)->create()->pluck('id')->all();
        $sorted = $ids;
        sort($sorted, SORT_STRING);
        $this->assertSame($ids, $sorted);
    }

    public function test_foreign_keys_are_enforced_in_the_test_database(): void
    {
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);

        foreach ([
            fn () => Lesson::create(['module_id' => self::UNKNOWN, 'slug' => 'x', 'title' => 'X', 'sort_order' => 1]),
            fn () => $this->problem('orphan', (new Lesson)->forceFill(['id' => self::UNKNOWN])),
            fn () => ProblemTestCase::create(['problem_id' => self::UNKNOWN, 'input' => '', 'expected_output' => '1', 'is_hidden' => false]),
            fn () => Completion::create(['user_id' => self::UNKNOWN, 'problem_id' => $this->problem->id]),
            fn () => UserActivityLog::write((new User)->forceFill(['id' => self::UNKNOWN]), 'x@example.com', 'login'),
        ] as $i => $insert) {
            try {
                $insert();
                $this->fail("insert $i was accepted");
            } catch (QueryException $e) {
                $this->assertStringContainsString('FOREIGN KEY', $e->getMessage());
            }
        }
    }

    public function test_deletes_cascade_or_keep_log_rows_as_before(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $other = User::factory()->create();
        $this->problem->testCases()->create(['input' => '1', 'expected_output' => '1', 'is_hidden' => false, 'position' => 1]);
        foreach ([$user, $other] as $who) {
            Submission::create(['user_id' => $who->id, 'problem_id' => $this->problem->id, 'language' => 'python', 'code' => 'x', 'status' => 'passed', 'passed_count' => 1, 'total_count' => 1]);
            Completion::create(['user_id' => $who->id, 'problem_id' => $this->problem->id]);
        }
        UserActivityLog::write($user, $user->email, 'login');
        AdminAuditLog::record($user, 'module.create', $this->module, 'Basics');

        // A user: submissions and completions go, log rows stay with the email and lose the link.
        $user->delete();
        $this->assertSame(0, Submission::where('user_id', $user->id)->count() + Completion::where('user_id', $user->id)->count());
        $this->assertSame(1, Submission::count());
        $this->assertSame(1, Completion::count());
        $this->assertSame(['user_id' => null, 'email' => 'ada@example.com'], UserActivityLog::sole()->only(['user_id', 'email']));
        $this->assertSame(['actor_id' => null, 'actor_email' => 'ada@example.com'], AdminAuditLog::sole()->only(['actor_id', 'actor_email']));

        // A module takes its lessons, their problems and everything under them.
        $this->module->delete();
        foreach (['lessons', 'problems', 'test_cases', 'submissions', 'completions'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        // The audit row only names its target; it has no foreign key to it.
        $this->assertSame($this->module->id, AdminAuditLog::sole()->target_id);
    }

    public function test_bad_and_unknown_ids_are_404_with_the_usual_json(): void
    {
        // As in production: without debug output a 404 is only {message}.
        config(['app.debug' => false]);
        $admin = User::factory()->create(['role' => 'admin']);
        $p = $this->problem->id;
        $case = $this->problem->testCases()->create(['input' => '1', 'expected_output' => '1', 'is_hidden' => false, 'position' => 1]);

        foreach (['abc', '1', '99999', "{$p}x", strtoupper(self::UNKNOWN), self::UNKNOWN] as $bad) {
            foreach ([
                ['GET', "/api/admin/modules/$bad/lessons"],
                ['PUT', "/api/admin/modules/$bad"],
                ['GET', "/api/admin/lessons/$bad"],
                ['GET', "/api/admin/problems/$bad"],
                ['GET', "/api/admin/problems/$bad/test-cases"],
                ['PUT', "/api/admin/problems/$p/test-cases/$bad"],
                ['DELETE', "/api/admin/problems/$bad/test-cases/{$case->id}"],
                ['GET', "/api/admin/users/$bad"],
                ['POST', "/api/admin/users/$bad/ban"],
                ['GET', "/api/admin/users/$bad/activity"],
                ['POST', "/api/problems/$bad/submit"],
            ] as [$method, $uri]) {
                $response = $this->actingAs($admin)->json($method, $uri, ['language' => 'python', 'code' => 'x']);
                $response->assertNotFound();
                $this->assertSame(['message'], array_keys($response->json()), "$method $uri");
            }
        }
        // Slugs are unchanged: an unknown one is the same 404.
        $this->getJson('/api/lessons/'.self::UNKNOWN)->assertNotFound()->assertJsonStructure(['message']);
        $this->getJson('/api/challenges/nope')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_a_valid_uuid_of_another_user_follows_the_same_rules(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'owner']);

        // Knowing a real id gives nothing: a user is refused before the id is even looked up.
        $this->actingAs($user)->getJson("/api/admin/users/{$owner->id}")->assertForbidden();
        $this->actingAs($user)->postJson("/api/admin/users/{$admin->id}/ban", ['reason' => 'x'])->assertForbidden();
        // Admins still can't act on admins or owners, or on themselves.
        $this->actingAs($admin)->postJson("/api/admin/users/{$otherAdmin->id}/ban", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($admin)->postJson("/api/admin/users/{$owner->id}/ban", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($admin)->postJson("/api/admin/users/{$admin->id}/ban", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($admin)->putJson("/api/admin/users/{$user->id}/role", ['role' => 'admin'])->assertForbidden();
        // An owner can, as before.
        $this->actingAs($owner)->putJson("/api/admin/users/{$user->id}/role", ['role' => 'admin'])->assertOk()->assertJsonPath('id', $user->id);
        $this->assertSame('admin', $user->fresh()->role);
    }

    public function test_sessions_are_stored_and_deleted_by_user_uuid(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'secret-password']);
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'secret-password'])->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertSame([$user->id], DB::table('sessions')->whereNotNull('user_id')->pluck('user_id')->all());

        $session = fn (User $who, string $id) => DB::table('sessions')->insert(['id' => $id, 'user_id' => $who->id, 'payload' => '', 'last_activity' => time()]);
        $session($user, 'second-device');
        $session($other, 'other-user');

        // A new client for the admin, as SessionTest::fresh() does.
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('auth.driver');
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->actingAs($admin)->postJson("/api/admin/users/{$user->id}/logout-everywhere")->assertOk();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());

        $session($user, 'third-device');
        $this->actingAs($admin)->postJson("/api/admin/users/{$user->id}/ban", ['reason' => 'Spam'])->assertOk();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->actingAs($admin)->postJson("/api/admin/users/{$user->id}/unban")->assertOk()->assertJsonPath('banned_at', null);
    }

    public function test_test_cases_follow_their_position_not_their_id(): void
    {
        config(['services.sandbox.url' => 'http://sandbox.test']);
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        // Made in reverse, so id order and position order disagree.
        foreach ([3, 2, 1] as $position) {
            $this->problem->testCases()->create(['input' => "in$position", 'expected_output' => "out$position", 'is_hidden' => $position === 2, 'position' => $position]);
        }
        $this->actingAs($admin)->postJson("/api/admin/problems/{$this->problem->id}/test-cases", ['input' => 'in4', 'expected_output' => 'out4', 'is_hidden' => false])->assertCreated();
        $this->assertSame(4, ProblemTestCase::where('input', 'in4')->value('position'));

        $this->actingAs($admin)->getJson("/api/admin/problems/{$this->problem->id}/test-cases")->assertOk()
            ->assertJsonPath('*.input', ['in1', 'in2', 'in3', 'in4'])
            ->assertJsonMissingPath('0.position');
        $this->getJson('/api/lessons/warm-up')->assertJsonPath('problems.0.test_cases.*.input', ['in1', 'in3', 'in4']);
        $this->getJson('/api/challenges/warm-up')->assertJsonPath('test_cases.*.input', ['in1', 'in3', 'in4']);

        Http::fake(['sandbox.test/*' => Http::response(['status' => 'failed', 'results' => [
            ['passed' => true, 'actual' => 'out1'], ['passed' => true, 'actual' => 'out2'], ['passed' => false, 'actual' => 'x'], ['passed' => true, 'actual' => 'out4'],
        ]])]);
        $this->actingAs($user)->postJson("/api/problems/{$this->problem->id}/submit", ['language' => 'python', 'code' => 'x'])->assertOk()
            ->assertJsonPath('results.0.input', 'in1')
            ->assertJsonPath('results.1', ['passed' => true])
            ->assertJsonPath('results.2.input', 'in3')
            ->assertJsonPath('results.2.passed', false);
        Http::assertSent(fn ($request) => array_column($request['test_cases'], 'input') === ['in1', 'in2', 'in3', 'in4']);
    }

    public function test_problems_and_users_are_ordered_by_creation_time_not_by_id(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        // Made first, so its id is the smallest, but dated last.
        $late = $this->problem('late-standalone');
        $early = $this->problem('early-standalone');
        DB::table('problems')->where('id', $late->id)->update(['created_at' => '2026-05-01 00:00:00']);
        DB::table('problems')->where('id', $early->id)->update(['created_at' => '2026-04-01 00:00:00']);
        DB::table('problems')->where('id', $this->problem->id)->update(['created_at' => '2026-06-01 00:00:00']);

        $this->getJson('/api/challenges')->assertJsonPath('*.slug', ['warm-up', 'early-standalone', 'late-standalone']);
        $this->actingAs($owner)->getJson('/api/admin/problems')->assertJsonPath('*.slug', ['early-standalone', 'late-standalone', 'warm-up'])
            ->assertJsonMissingPath('0.created_at');

        $older = User::factory()->create(['name' => 'Older']);
        $newer = User::factory()->create(['name' => 'Newer']);
        $older->forceFill(['created_at' => '2026-01-02 00:00:00'])->save();
        $newer->forceFill(['created_at' => '2026-01-01 00:00:00'])->save();
        $owner->forceFill(['created_at' => '2025-01-01 00:00:00'])->save();
        $this->actingAs($owner)->getJson('/api/admin/users')->assertJsonPath('data.*.name', ['Older', 'Newer', $owner->name]);
    }

    public function test_leaderboard_ties_in_the_same_second_share_a_rank_in_a_stable_order(): void
    {
        $user = User::factory()->create(['name' => 'Same A']);
        $users = [$user, User::factory()->create(['name' => 'Same B']), User::factory()->create(['name' => 'Same C'])];
        foreach ($users as $who) {
            $who->forceFill(['points' => 20, 'created_at' => '2026-01-01 00:00:00'])->save();
        }
        $top = User::factory()->create(['name' => 'Top']);
        $top->forceFill(['points' => 30, 'created_at' => '2026-02-01 00:00:00'])->save();

        $first = $this->getJson('/api/leaderboard')->assertOk()->json();
        $this->assertSame([1, 2, 2, 2], array_column($first, 'rank'));
        $this->assertSame('Top', $first[0]['name']);
        $this->assertSame($first, $this->getJson('/api/leaderboard')->json());
        $this->actingAs($user)->getJson('/api/leaderboard/me')->assertExactJson(['rank' => 2, 'points' => 20]);
    }

    public function test_id_fields_are_validated_as_uuids_and_filters_take_a_uuid(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $lesson = ['title' => 'X', 'slug' => 'x', 'sort_order' => 1];
        $problem = ['title' => 'X', 'slug' => 'x', 'description' => 'D', 'difficulty' => 'easy'];

        // An upper-case copy of a real id is refused too (MySQL would match it and store it as sent).
        foreach ([123, 'abc', self::UNKNOWN, strtoupper($this->module->id), strtoupper($this->lesson->id)] as $bad) {
            $this->actingAs($owner)->postJson('/api/admin/lessons', $lesson + ['module_id' => $bad])->assertUnprocessable()->assertJsonValidationErrors('module_id');
            $this->actingAs($owner)->postJson('/api/admin/problems', $problem + ['lesson_id' => $bad])->assertUnprocessable()->assertJsonValidationErrors('lesson_id');
        }
        $this->actingAs($owner)->postJson('/api/admin/lessons', $lesson + ['module_id' => $this->module->id])->assertCreated()->assertJsonPath('module_id', $this->module->id);
        $this->actingAs($owner)->postJson('/api/admin/problems', $problem + ['lesson_id' => $this->lesson->id])->assertCreated()->assertJsonPath('lesson_id', $this->lesson->id);

        $user = User::factory()->create(['email' => 'ada@example.com']);
        UserActivityLog::write($user, $user->email, 'login');
        $this->actingAs($owner)->getJson("/api/admin/activity?user={$user->id}")->assertJsonCount(1, 'data');
        $this->actingAs($owner)->getJson('/api/admin/activity?user=1')->assertJsonCount(0, 'data');
        $this->actingAs($owner)->getJson("/api/admin/audit-log?actor={$owner->id}")->assertJsonCount(2, 'data');
        $this->actingAs($owner)->getJson('/api/admin/audit-log?actor=1')->assertJsonCount(0, 'data');
    }

    public function test_content_sync_twice_on_the_real_seed_data_adds_nothing_the_second_time(): void
    {
        $this->module->lessons()->delete();
        $this->module->delete();

        $this->artisan('content:sync')->assertExitCode(0);
        $counts = fn () => [Module::count(), Lesson::count(), Problem::count(), ProblemTestCase::count()];
        $first = $counts();
        $ids = Problem::orderBy('slug')->pluck('id', 'slug')->all();

        $this->artisan('content:sync')->expectsOutput('Added: 0 modules, 0 lessons, 0 problems, 0 test cases.')->assertExitCode(0);
        $this->assertSame([19, 65, 65], array_slice($first, 0, 3));
        $this->assertSame($first, $counts());
        $this->assertSame($ids, Problem::orderBy('slug')->pluck('id', 'slug')->all());
        // Positions run 1..n per problem, in the file's order.
        $this->assertSame(0, ProblemTestCase::query()->where('position', '<', 1)->count());
        $this->assertSame(Problem::count(), ProblemTestCase::where('position', 1)->count());
    }
}
