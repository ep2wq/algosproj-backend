<?php

namespace Tests\Feature;

use App\Models\Completion;
use App\Models\Module;
use App\Models\Problem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChallengeTest extends TestCase
{
    use RefreshDatabase;

    private function problemIn(Module $module, string $slug, int $lessonOrder, string $difficulty = 'easy', int $points = 10): Problem
    {
        $lesson = $module->lessons()->create(['slug' => $slug, 'title' => ucfirst($slug), 'sort_order' => $lessonOrder]);
        $problem = $lesson->problems()->create(['slug' => $slug, 'title' => ucfirst($slug).' problem', 'description' => 'Solve '.$slug, 'difficulty' => $difficulty, 'points' => $points]);
        $problem->testCases()->createMany([
            ['input' => 'VISIBLE-IN-'.$slug, 'expected_output' => 'VISIBLE-OUT-'.$slug, 'is_hidden' => false],
            ['input' => 'HIDDEN-IN-'.$slug, 'expected_output' => 'HIDDEN-OUT-'.$slug, 'is_hidden' => true],
        ]);

        return $problem;
    }

    private function standalone(string $slug): Problem
    {
        $problem = Problem::create(['slug' => $slug, 'title' => 'Standalone '.$slug, 'description' => 'On its own', 'difficulty' => 'hard', 'points' => 30]);
        $problem->testCases()->createMany([
            ['input' => 'VISIBLE-IN-'.$slug, 'expected_output' => 'VISIBLE-OUT-'.$slug, 'is_hidden' => false],
            ['input' => 'HIDDEN-IN-'.$slug, 'expected_output' => 'HIDDEN-OUT-'.$slug, 'is_hidden' => true],
        ]);

        return $problem;
    }

    // Two modules created out of order, lessons out of order, and two standalone problems
    // created between them.
    private function seedChallenges(): array
    {
        $second = Module::create(['slug' => 'graphs', 'title' => 'Graphs', 'track' => 'algorithms', 'sort_order' => 2]);
        $first = Module::create(['slug' => 'basics', 'title' => 'Basics', 'track' => 'basics', 'sort_order' => 1]);
        $standaloneA = $this->standalone('standalone-a');
        $graphB = $this->problemIn($second, 'graph-b', 2, 'medium', 20);
        $basicsB = $this->problemIn($first, 'basics-b', 2);
        $graphA = $this->problemIn($second, 'graph-a', 1, 'hard', 30);
        $standaloneB = $this->standalone('standalone-b');
        $basicsA = $this->problemIn($first, 'basics-a', 1);

        return compact('basicsA', 'basicsB', 'graphA', 'graphB', 'standaloneA', 'standaloneB');
    }

    public function test_guest_sees_every_problem_in_module_then_lesson_order_with_standalone_last(): void
    {
        $this->seedChallenges();

        $response = $this->getJson('/api/challenges')->assertOk();

        $this->assertSame(
            ['basics-a', 'basics-b', 'graph-a', 'graph-b', 'standalone-a', 'standalone-b'],
            array_column($response->json(), 'slug'),
        );
        $this->assertSame([false, false, false, false, false, false], array_column($response->json(), 'solved'));
        $response->assertJsonPath('2', [
            'slug' => 'graph-a',
            'title' => 'Graph-a problem',
            'difficulty' => 'hard',
            'points' => 30,
            'topic' => ['slug' => 'graphs', 'title' => 'Graphs'],
            'lesson_slug' => 'graph-a',
            'solved' => false,
        ]);
        $response->assertJsonPath('4.topic', null)->assertJsonPath('4.lesson_slug', null);
    }

    public function test_solved_is_true_only_for_the_requesters_completions(): void
    {
        ['basicsB' => $basicsB, 'standaloneA' => $standaloneA, 'graphA' => $graphA] = $this->seedChallenges();
        $me = User::factory()->create();
        $other = User::factory()->create();
        Completion::create(['user_id' => $me->id, 'problem_id' => $basicsB->id]);
        Completion::create(['user_id' => $me->id, 'problem_id' => $standaloneA->id]);
        Completion::create(['user_id' => $other->id, 'problem_id' => $graphA->id]);

        $rows = $this->withToken($me->createToken('api')->plainTextToken)->getJson('/api/challenges')->assertOk()->json();

        $this->assertSame([false, true, false, false, true, false], array_column($rows, 'solved'));
    }

    public function test_list_uses_the_same_number_of_queries_for_any_number_of_problems(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;
        $module = Module::create(['slug' => 'basics', 'title' => 'Basics', 'track' => 'basics', 'sort_order' => 1]);
        $this->problemIn($module, 'p-1', 1);
        $this->standalone('s-1');

        $count = function () use ($token) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->withToken($token)->getJson('/api/challenges')->assertOk();
            DB::disableQueryLog();

            // Leave out the token, user and last_used_at queries of authentication.
            return collect(DB::getQueryLog())
                ->reject(fn ($query) => preg_match('/personal_access_tokens|from "users"/', $query['query']))
                ->count();
        };

        $few = $count();
        for ($i = 2; $i <= 20; $i++) {
            $this->problemIn(Module::create(['slug' => "m-$i", 'title' => "M $i", 'track' => 'basics', 'sort_order' => $i]), "p-$i", 1);
        }
        $this->standalone('s-2');

        // Problems, lessons, modules and the requester's completions.
        $this->assertSame(4, $few);
        $this->assertSame($few, $count());
    }

    public function test_challenge_shows_the_statement_and_visible_test_cases_only(): void
    {
        ['graphA' => $graphA] = $this->seedChallenges();

        $response = $this->getJson('/api/challenges/graph-a')->assertOk();

        $response->assertExactJson([
            'id' => $graphA->id,
            'title' => 'Graph-a problem',
            'description' => 'Solve graph-a',
            'difficulty' => 'hard',
            'points' => 30,
            'test_cases' => [[
                'id' => $graphA->testCases()->where('is_hidden', false)->value('id'),
                'input' => 'VISIBLE-IN-graph-a',
                'expected_output' => 'VISIBLE-OUT-graph-a',
            ]],
            'slug' => 'graph-a',
            'topic' => ['slug' => 'graphs', 'title' => 'Graphs'],
            'lesson_slug' => 'graph-a',
            'solved' => false,
        ]);
    }

    public function test_standalone_challenge_has_no_topic_or_lesson_and_shows_solved_for_its_solver(): void
    {
        ['standaloneB' => $standaloneB] = $this->seedChallenges();
        $me = User::factory()->create();
        Completion::create(['user_id' => $me->id, 'problem_id' => $standaloneB->id]);

        $this->getJson('/api/challenges/standalone-b')->assertOk()
            ->assertJsonPath('topic', null)
            ->assertJsonPath('lesson_slug', null)
            ->assertJsonPath('solved', false);

        $this->withToken($me->createToken('api')->plainTextToken)->getJson('/api/challenges/standalone-b')->assertOk()
            ->assertJsonPath('solved', true);
    }

    public function test_hidden_test_cases_never_appear_in_any_challenge_response(): void
    {
        $this->seedChallenges();

        $bodies = [$this->getJson('/api/challenges')->assertOk()->getContent()];
        foreach (['basics-a', 'basics-b', 'graph-a', 'graph-b', 'standalone-a', 'standalone-b'] as $slug) {
            $bodies[] = $this->getJson("/api/challenges/$slug")->assertOk()->getContent();
        }

        foreach ($bodies as $body) {
            $this->assertStringNotContainsString('HIDDEN', $body);
        }
    }

    public function test_unknown_challenge_returns_404(): void
    {
        $this->getJson('/api/challenges/nope')->assertNotFound();
    }
}
