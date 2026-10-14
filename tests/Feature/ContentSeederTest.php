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

    public function test_seeds_four_modules_and_twelve_problems_with_enough_tests(): void
    {
        $this->seed(ContentSeeder::class);

        $this->assertSame(
            ['basics', 'arrays-and-strings', 'stacks-and-queues', 'sorting-and-searching'],
            Module::orderBy('sort_order')->pluck('slug')->all(),
        );
        $this->assertSame(12, Problem::count());

        foreach (Problem::with('testCases')->get() as $problem) {
            $this->assertGreaterThanOrEqual(2, $problem->testCases->where('is_hidden', false)->count(), $problem->title);
            $this->assertGreaterThanOrEqual(3, $problem->testCases->where('is_hidden', true)->count(), $problem->title);
            $this->assertSame(['easy' => 10, 'medium' => 20, 'hard' => 30][$problem->difficulty], $problem->points, $problem->title);
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
