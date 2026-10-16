<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\Problem;
use App\Models\TestCase as ProblemTestCase;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_nineteen_modules_and_sixty_five_problems_with_enough_tests(): void
    {
        $this->seed(ContentSeeder::class);

        $this->assertSame(
            ['basics', 'programming-basics', 'core-programming', 'oop', 'algorithm-basics', 'arrays-and-strings', 'stacks-and-queues', 'linked-lists', 'hash-tables', 'trees-and-bsts', 'heaps', 'recursion-and-backtracking', 'graphs', 'tries-union-find', 'sorting-and-searching', 'greedy', 'dp-basics', 'dp-tables', 'bits-math'],
            Module::orderBy('sort_order')->pluck('slug')->all(),
        );
        $this->assertSame(65, Problem::count());

        foreach (Problem::with('testCases')->get() as $problem) {
            $this->assertGreaterThanOrEqual(2, $problem->testCases->where('is_hidden', false)->count(), $problem->title);
            $this->assertGreaterThanOrEqual(3, $problem->testCases->where('is_hidden', true)->count(), $problem->title);
            $this->assertSame(['easy' => 10, 'medium' => 20, 'hard' => 30][$problem->difficulty], $problem->points, $problem->title);
            $this->assertSame($problem->lesson->slug, $problem->slug, $problem->title);
        }
    }

    public function test_every_lesson_has_a_body_of_at_least_150_words(): void
    {
        $this->seed(ContentSeeder::class);

        $this->assertSame(65, Lesson::count());

        foreach (Lesson::all() as $lesson) {
            $words = count(preg_split('/\s+/', trim((string) $lesson->body), -1, PREG_SPLIT_NO_EMPTY));
            $this->assertGreaterThanOrEqual(150, $words, $lesson->slug);
        }
    }

    private function contentSnapshot(): array
    {
        return [Module::all()->toArray(), Lesson::all()->toArray(), Problem::all()->toArray(), ProblemTestCase::all()->toArray()];
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $this->seed(ContentSeeder::class);
        $before = $this->contentSnapshot();

        $this->artisan('db:seed', ['--class' => ContentSeeder::class])
            ->expectsOutputToContain('Content not imported: modules already exist.')
            ->assertExitCode(0);

        $this->assertSame($before, $this->contentSnapshot());
    }

    public function test_a_run_keeps_admin_edits(): void
    {
        $this->seed(ContentSeeder::class);
        Lesson::where('slug', 'warm-up')->update(['title' => 'Edited by an admin']);

        $this->seed(ContentSeeder::class);

        $this->assertSame('Edited by an admin', Lesson::where('slug', 'warm-up')->value('title'));
    }

    public function test_imports_nothing_when_any_module_exists(): void
    {
        Module::create(['slug' => 'own', 'title' => 'Own', 'track' => 'basics', 'sort_order' => 1]);

        $this->seed(ContentSeeder::class);

        $this->assertSame(['own'], Module::pluck('slug')->all());
        $this->assertSame(0, Lesson::count());
    }

    public function test_keeps_the_warm_up_lesson(): void
    {
        $this->seed(ContentSeeder::class);

        $lesson = Lesson::where('slug', 'warm-up')->firstOrFail();

        $this->assertSame('basics', $lesson->module->slug);
        $this->assertSame('Double the number', $lesson->problems()->firstOrFail()->title);
    }
}
