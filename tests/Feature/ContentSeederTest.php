<?php

namespace Tests\Feature;

use App\Models\Lesson;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_warm_up_lesson_with_problem_and_test_cases(): void
    {
        $this->seed(ContentSeeder::class);

        $lesson = Lesson::where('slug', 'warm-up')->firstOrFail();
        $problem = $lesson->problems()->firstOrFail();

        $this->assertSame('algorithms', $lesson->module->slug);
        $this->assertSame('Double the number', $problem->title);
        $this->assertSame(10, $problem->points);
        $this->assertSame(2, $problem->testCases()->where('is_hidden', false)->count());
        $this->assertSame(2, $problem->testCases()->where('is_hidden', true)->count());
    }
}
