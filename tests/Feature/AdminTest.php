<?php

namespace Tests\Feature;

use App\Models\Completion;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Problem;
use App\Models\TestCase as ProblemTestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private Module $module;

    private Module $emptyModule;

    private Lesson $lesson;

    private Lesson $emptyLesson;

    private Problem $problem;

    private ProblemTestCase $testCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create();
        $this->setUpContent();
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
    }

    // Each request starts clean: no token from an earlier request and no cached user.
    private function send(string $method, string $uri, array $data = [], ?User $as = null): TestResponse
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        if ($as) {
            $this->withToken($as->createToken('api')->plainTextToken);
        }

        return $this->json($method, $uri, $data);
    }

    private function asAdmin(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->send($method, $uri, $data, $this->admin);
    }

    // Every admin route with a valid request and the status an admin gets.
    private function routes(): array
    {
        $m = $this->module->id;
        $l = $this->lesson->id;
        $p = $this->problem->id;
        $t = $this->testCase->id;

        return [
            ['GET', '/api/admin/modules', [], 200],
            ['POST', '/api/admin/modules', ['title' => 'Graphs', 'slug' => 'graphs', 'sort_order' => 3], 201],
            ['PUT', "/api/admin/modules/$m", ['title' => 'Start here', 'slug' => 'basics', 'sort_order' => 1], 200],
            ['DELETE', "/api/admin/modules/{$this->emptyModule->id}", [], 204],
            ['GET', "/api/admin/modules/$m/lessons", [], 200],
            ['POST', '/api/admin/lessons', ['module_id' => $m, 'title' => 'New', 'slug' => 'new-lesson', 'body' => 'Text', 'sort_order' => 3], 201],
            ['GET', "/api/admin/lessons/$l", [], 200],
            ['PUT', "/api/admin/lessons/$l", ['module_id' => $m, 'title' => 'Warm-up!', 'slug' => 'warm-up', 'body' => 'New text', 'sort_order' => 1], 200],
            ['DELETE', "/api/admin/lessons/{$this->emptyLesson->id}", [], 204],
            ['GET', '/api/admin/problems', [], 200],
            ['POST', '/api/admin/problems', ['title' => 'Triple', 'slug' => 'triple', 'description' => 'Triple it', 'difficulty' => 'hard'], 201],
            ['GET', "/api/admin/problems/$p", [], 200],
            ['PUT', "/api/admin/problems/$p", ['lesson_id' => $l, 'title' => 'Double it', 'slug' => 'warm-up', 'description' => 'Double', 'difficulty' => 'easy'], 200],
            ['DELETE', "/api/admin/problems/$p", [], 204],
            ['GET', "/api/admin/problems/$p/test-cases", [], 200],
            ['POST', "/api/admin/problems/$p/test-cases", ['input' => '1', 'expected_output' => '2', 'is_hidden' => true], 201],
            ['PUT', "/api/admin/problems/$p/test-cases/$t", ['input' => '3', 'expected_output' => '6', 'is_hidden' => false], 200],
            ['DELETE', "/api/admin/problems/$p/test-cases/$t", [], 204],
        ];
    }

    private function contentSnapshot(): array
    {
        return [Module::all()->toArray(), Lesson::all()->toArray(), Problem::all()->toArray(), ProblemTestCase::all()->toArray()];
    }

    public function test_the_route_list_covers_every_admin_route(): void
    {
        $registered = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/admin'))
            ->map(fn ($route) => $route->methods()[0].' /'.$route->uri())
            ->sort()->values()->all();

        $tested = collect($this->routes())
            ->map(fn ($route) => $route[0].' '.preg_replace(['/modules\/\d+/', '/lessons\/\d+/', '/problems\/\d+/', '/test-cases\/\d+/'], ['modules/{module}', 'lessons/{lesson}', 'problems/{problem}', 'test-cases/{testCase}'], $route[1]))
            ->sort()->values()->all();

        $this->assertSame($registered, $tested);
    }

    public function test_every_admin_route_is_401_for_guests_403_for_users_and_works_for_admins(): void
    {
        for ($i = 0; $i < count($this->routes()); $i++) {
            // Built again each time: the content (and its ids) is recreated after every route.
            [$method, $uri, $data, $status] = $this->routes()[$i];
            $before = $this->contentSnapshot();

            $this->send($method, $uri, $data)->assertStatus(401);
            $this->send($method, $uri, $data, $this->user)->assertStatus(403);
            $this->assertSame($before, $this->contentSnapshot(), "$method $uri changed content for a non-admin");

            $this->asAdmin($method, $uri, $data)->assertStatus($status);

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
