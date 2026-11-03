<?php

namespace Tests\Feature;

use App\Models\Module;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
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
        $this->getJson('/api/lessons/nope')->assertNotFound()->assertHeaderMissing('ETag');
    }

    public function test_modules_and_lesson_answer_304_to_a_matching_etag_per_language(): void
    {
        $module = Module::create(['slug' => 'algorithms', 'title' => 'Algorithms', 'title_ar' => 'الخوارزميات', 'track' => 'algorithms', 'sort_order' => 1]);
        $lesson = $module->lessons()->create(['slug' => 'warm-up', 'title' => 'Warm-up', 'title_ar' => 'تمهيد', 'body' => 'Lesson body', 'sort_order' => 1]);

        foreach (['/api/modules', '/api/lessons/warm-up'] as $path) {
            $response = $this->getJson($path)->assertOk()->assertHeader('Vary', 'Accept-Language');
            $etag = $response->headers->get('ETag');
            $this->assertNotEmpty($etag, $path);
            // Not public and no max-age: the browser asks again every time.
            $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
            $this->assertStringNotContainsString('max-age', $response->headers->get('Cache-Control'));
            $this->assertStringNotContainsString('public', $response->headers->get('Cache-Control'));

            $notModified = $this->getJson($path, ['If-None-Match' => $etag])->assertStatus(304);
            $this->assertSame('', $notModified->getContent(), $path);
            // The Arabic text differs, so the English ETag does not match it.
            $this->getJson($path, ['If-None-Match' => $etag, 'Accept-Language' => 'ar'])->assertOk();
        }

        // Changed content gets a new ETag, so the old one no longer matches.
        $old = $this->getJson('/api/lessons/warm-up')->headers->get('ETag');
        $lesson->update(['title' => 'Warm-up 2']);
        $this->getJson('/api/lessons/warm-up', ['If-None-Match' => $old])->assertOk()->assertJsonPath('title', 'Warm-up 2');
    }

    public function test_lazy_loading_a_relation_throws_outside_production(): void
    {
        // Laravel flags it only for models loaded together (an N+1), so two modules.
        Module::create(['slug' => 'algorithms', 'title' => 'Algorithms', 'track' => 'algorithms', 'sort_order' => 1]);
        Module::create(['slug' => 'graphs', 'title' => 'Graphs', 'track' => 'algorithms', 'sort_order' => 2]);

        $this->assertTrue(Model::preventsLazyLoading());
        $this->expectException(LazyLoadingViolationException::class);
        Module::all()->each(fn ($module) => $module->lessons);
    }
}
