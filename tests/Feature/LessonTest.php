<?php

namespace Tests\Feature;

use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_modules_are_listed_with_lessons_in_sort_order_without_body(): void
    {
        $second = Module::create(['slug' => 'data-structures', 'title' => 'Data structures', 'track' => 'data-structures', 'sort_order' => 2]);
        $first = Module::create(['slug' => 'algorithms', 'title' => 'Algorithms', 'track' => 'algorithms', 'sort_order' => 1]);
        $first->lessons()->create(['slug' => 'b-lesson', 'title' => 'B', 'body' => 'BODY-MARKER', 'sort_order' => 2]);
        $first->lessons()->create(['slug' => 'a-lesson', 'title' => 'A', 'body' => 'BODY-MARKER', 'sort_order' => 1]);
        $second->lessons()->create(['slug' => 'c-lesson', 'title' => 'C', 'sort_order' => 1]);

        $response = $this->getJson('/api/modules')->assertOk();

        $response->assertJsonPath('0.slug', 'algorithms')
            ->assertJsonPath('1.slug', 'data-structures')
            ->assertJsonPath('0.lessons.0.slug', 'a-lesson')
            ->assertJsonPath('0.lessons.1.slug', 'b-lesson')
            ->assertJsonPath('1.lessons.0.slug', 'c-lesson');
        $this->assertStringNotContainsString('BODY-MARKER', $response->getContent());
    }

    public function test_lesson_shows_problems_with_visible_test_cases_only(): void
    {
        $module = Module::create(['slug' => 'algorithms', 'title' => 'Algorithms', 'track' => 'algorithms', 'sort_order' => 1]);
        $lesson = $module->lessons()->create(['slug' => 'warm-up', 'title' => 'Warm-up', 'body' => 'Lesson body', 'sort_order' => 1]);
        $problem = $lesson->problems()->create(['title' => 'Double', 'description' => 'Double it', 'difficulty' => 'easy', 'points' => 10]);
        $problem->testCases()->createMany([
            ['input' => 'VISIBLE-IN', 'expected_output' => 'VISIBLE-OUT', 'is_hidden' => false],
            ['input' => 'HIDDEN-IN', 'expected_output' => 'HIDDEN-OUT', 'is_hidden' => true],
        ]);

        $response = $this->getJson('/api/lessons/warm-up')->assertOk();

        $response->assertJsonPath('body', 'Lesson body')
            ->assertJsonPath('problems.0.title', 'Double')
            ->assertJsonCount(1, 'problems.0.test_cases')
            ->assertJsonPath('problems.0.test_cases.0.input', 'VISIBLE-IN');
        $this->assertStringNotContainsString('HIDDEN-IN', $response->getContent());
        $this->assertStringNotContainsString('HIDDEN-OUT', $response->getContent());
    }

    public function test_unknown_lesson_returns_404(): void
    {
        $this->getJson('/api/lessons/nope')->assertNotFound();
    }
}
