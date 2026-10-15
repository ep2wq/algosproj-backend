<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\Problem;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_five_modules_and_fifteen_problems_with_enough_tests(): void
    {
        $this->seed(ContentSeeder::class);

        $this->assertSame(
            ['basics', 'arrays-and-strings', 'stacks-and-queues', 'linked-lists', 'sorting-and-searching'],
            Module::orderBy('sort_order')->pluck('slug')->all(),
        );
        $this->assertSame(15, Problem::count());

        foreach (Problem::with('testCases')->get() as $problem) {
            $this->assertGreaterThanOrEqual(2, $problem->testCases->where('is_hidden', false)->count(), $problem->title);
            $this->assertGreaterThanOrEqual(3, $problem->testCases->where('is_hidden', true)->count(), $problem->title);
            $this->assertSame(['easy' => 10, 'medium' => 20, 'hard' => 30][$problem->difficulty], $problem->points, $problem->title);
        }
    }

    public function test_every_lesson_has_a_body_of_at_least_150_words(): void
    {
        $this->seed(ContentSeeder::class);

        $this->assertSame(15, Lesson::count());

        foreach (Lesson::all() as $lesson) {
            $words = count(preg_split('/\s+/', trim((string) $lesson->body), -1, PREG_SPLIT_NO_EMPTY));
            $this->assertGreaterThanOrEqual(150, $words, $lesson->slug);
        }
    }

    public function test_keeps_the_warm_up_lesson(): void
    {
        $this->seed(ContentSeeder::class);

        $lesson = Lesson::where('slug', 'warm-up')->firstOrFail();

        $this->assertSame('basics', $lesson->module->slug);
        $this->assertSame('Double the number', $lesson->problems()->firstOrFail()->title);
    }
}
