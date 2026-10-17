<?php

namespace Tests\Feature;

use App\Http\Middleware\SetContentLanguage;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Problem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContentLanguageTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINTS = ['/api/modules', '/api/lessons/warm-up', '/api/challenges', '/api/challenges/warm-up'];

    private Module $module;

    private Lesson $lesson;

    private Problem $problem;

    protected function setUp(): void
    {
        parent::setUp();

        // Fully translated: the module, the lesson and its problem. Partly: the second lesson has
        // an Arabic title only, its problem an Arabic description only.
        $this->module = Module::create(['slug' => 'basics', 'title' => 'Basics', 'title_ar' => 'الأساسيات', 'track' => 'basics', 'sort_order' => 1]);
        $this->lesson = $this->module->lessons()->create(['slug' => 'warm-up', 'title' => 'Warm-up', 'title_ar' => 'الإحماء', 'body' => 'English body', 'body_ar' => 'نص عربي', 'sort_order' => 1]);
        $this->problem = $this->lesson->problems()->create(['slug' => 'warm-up', 'title' => 'Double', 'title_ar' => 'ضاعف', 'description' => 'Double it', 'description_ar' => 'ضاعفه', 'difficulty' => 'easy', 'points' => 10]);
        $this->problem->testCases()->createMany([
            ['input' => 'VISIBLE-IN', 'expected_output' => 'VISIBLE-OUT', 'is_hidden' => false],
            ['input' => 'HIDDEN-IN', 'expected_output' => 'HIDDEN-OUT', 'is_hidden' => true],
        ]);
        $partial = $this->module->lessons()->create(['slug' => 'partial', 'title' => 'Partial', 'title_ar' => 'جزئي', 'body' => 'Only English', 'sort_order' => 2]);
        $partial->problems()->create(['slug' => 'partial', 'title' => 'Partial problem', 'description' => 'English statement', 'description_ar' => 'نص المسألة', 'difficulty' => 'easy', 'points' => 10]);
    }

    public static function headers(): array
    {
        return [
            'ar' => ['ar', true],
            'ar-SA' => ['ar-SA', true],
            'ar with q values' => ['ar;q=0.9,en;q=0.8', true],
            'upper case' => ['AR-sa', true],
            'spaces' => [' ar , en', true],
            'en' => ['en', false],
            'en-US' => ['en-US', false],
            'fr' => ['fr', false],
            'Arabic second' => ['en,ar', false],
            'empty' => ['', false],
            'missing' => [null, false],
            'wildcard' => ['*', false],
            'arabic as a word' => ['arabic', false],
            'ar with an empty region' => ['ar-', false],
            'garbage' => ['@@@;;,,ar', false],
            'binary' => ["\x00\xff", false],
            'long' => [str_repeat('a', 5000), false],
        ];
    }

    #[DataProvider('headers')]
    public function test_arabic_only_when_the_first_language_is_ar(?string $header, bool $arabic): void
    {
        $this->assertSame($arabic, SetContentLanguage::wantsArabic($header));
    }

    public function test_arabic_header_returns_the_arabic_fields_with_their_language(): void
    {
        $headers = ['Accept-Language' => 'ar-SA,ar;q=0.9'];

        $this->getJson('/api/modules', $headers)
            ->assertJsonPath('0.title', 'الأساسيات')
            ->assertJsonPath('0.lessons.0.title', 'الإحماء');
        $this->getJson('/api/lessons/warm-up', $headers)
            ->assertJsonPath('title', 'الإحماء')
            ->assertJsonPath('body', 'نص عربي')
            ->assertJsonPath('body_lang', 'ar')
            ->assertJsonPath('problems.0.title', 'ضاعف')
            ->assertJsonPath('problems.0.description', 'ضاعفه')
            ->assertJsonPath('problems.0.description_lang', 'ar');
        $this->getJson('/api/challenges', $headers)
            ->assertJsonPath('0.title', 'ضاعف')
            ->assertJsonPath('0.topic.title', 'الأساسيات');
        $this->getJson('/api/challenges/warm-up', $headers)
            ->assertJsonPath('title', 'ضاعف')
            ->assertJsonPath('description', 'ضاعفه')
            ->assertJsonPath('description_lang', 'ar')
            ->assertJsonPath('topic.title', 'الأساسيات');
    }

    public function test_each_empty_arabic_field_falls_back_to_english_on_its_own(): void
    {
        $headers = ['Accept-Language' => 'ar'];

        $this->getJson('/api/lessons/partial', $headers)
            ->assertJsonPath('title', 'جزئي')
            ->assertJsonPath('body', 'Only English')
            ->assertJsonPath('body_lang', 'en')
            ->assertJsonPath('problems.0.title', 'Partial problem')
            ->assertJsonPath('problems.0.description', 'نص المسألة')
            ->assertJsonPath('problems.0.description_lang', 'ar');
        $this->getJson('/api/challenges/partial', $headers)
            ->assertJsonPath('title', 'Partial problem')
            ->assertJsonPath('description_lang', 'ar');
        $this->getJson('/api/modules', $headers)->assertJsonPath('0.lessons.1.title', 'جزئي');
    }

    public function test_english_is_the_same_without_a_header_with_en_and_with_other_languages(): void
    {
        foreach (self::ENDPOINTS as $uri) {
            $plain = $this->getJson($uri)->assertOk()->getContent();
            foreach (['en', 'en-US', 'fr', 'garbage;;', 'en,ar'] as $language) {
                $this->assertSame($plain, $this->getJson($uri, ['Accept-Language' => $language])->getContent(), "$uri $language");
            }
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $plain, $uri);
        }

        $this->getJson('/api/lessons/warm-up')
            ->assertJsonPath('title', 'Warm-up')
            ->assertJsonPath('body', 'English body')
            ->assertJsonPath('body_lang', 'en')
            ->assertJsonPath('problems.0.description_lang', 'en');
    }

    public function test_the_english_modules_list_keeps_its_fields(): void
    {
        $this->getJson('/api/modules')->assertExactJson([[
            'id' => $this->module->id,
            'slug' => 'basics',
            'title' => 'Basics',
            'track' => 'basics',
            'sort_order' => 1,
            'lessons' => [
                ['id' => $this->lesson->id, 'module_id' => $this->module->id, 'slug' => 'warm-up', 'title' => 'Warm-up', 'sort_order' => 1],
                ['id' => $this->lesson->id + 1, 'module_id' => $this->module->id, 'slug' => 'partial', 'title' => 'Partial', 'sort_order' => 2],
            ],
        ]]);
    }

    public function test_responses_vary_by_accept_language(): void
    {
        foreach ([...self::ENDPOINTS, '/api/lessons/nope'] as $uri) {
            foreach ([[], ['Accept-Language' => 'ar']] as $headers) {
                $vary = $this->getJson($uri, $headers)->headers->get('Vary');
                $this->assertContains('Accept-Language', array_map('trim', explode(',', (string) $vary)), $uri);
            }
        }
    }

    public function test_public_responses_never_name_an_arabic_column_or_a_hidden_test(): void
    {
        foreach (self::ENDPOINTS as $uri) {
            foreach ([[], ['Accept-Language' => 'ar']] as $headers) {
                $content = $this->getJson($uri, $headers)->getContent();
                $this->assertDoesNotMatchRegularExpression('/"[a-z_]+_ar"/', $content, $uri);
                $this->assertStringNotContainsString('HIDDEN-', $content, $uri);
            }
        }
    }

    public function test_arabic_needs_no_extra_queries(): void
    {
        foreach (self::ENDPOINTS as $uri) {
            $counts = [];
            foreach ([[], ['Accept-Language' => 'ar']] as $headers) {
                DB::flushQueryLog();
                DB::enableQueryLog();
                $this->getJson($uri, $headers)->assertOk();
                $counts[] = count(DB::getQueryLog());
                DB::disableQueryLog();
            }
            $this->assertSame($counts[0], $counts[1], $uri);
        }
    }

    public function test_an_empty_arabic_value_is_stored_as_null(): void
    {
        $this->lesson->update(['title_ar' => '', 'body_ar' => '   ']);
        $this->problem->update(['title_ar' => '', 'description_ar' => '']);
        $this->module->update(['title_ar' => '']);

        $this->assertNull($this->lesson->fresh()->title_ar);
        $this->assertNull($this->lesson->fresh()->body_ar);
        $this->assertNull($this->problem->fresh()->title_ar);
        $this->assertNull($this->problem->fresh()->description_ar);
        $this->assertNull($this->module->fresh()->title_ar);
    }
}
