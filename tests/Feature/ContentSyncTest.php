<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Problem;
use App\Models\TestCase as ProblemTestCase;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSyncTest extends TestCase
{
    use RefreshDatabase;

    private function contentSnapshot(): array
    {
        return [Module::all()->toArray(), Lesson::all()->toArray(), Problem::all()->toArray(), ProblemTestCase::all()->toArray()];
    }

    private function arabicFieldCount(): int
    {
        $count = 0;
        foreach ([require database_path('seeders/data/ar/modules.php'), require database_path('seeders/data/ar/lessons.php'), require database_path('seeders/data/ar/problems.php')] as $file) {
            foreach ($file as $fields) {
                $count += count(array_filter($fields, fn ($value) => filled($value)));
            }
        }

        return $count;
    }

    public function test_the_seeder_imports_english_and_arabic_through_the_same_code(): void
    {
        $this->seed(ContentSeeder::class);

        $this->assertSame(19, Module::count());
        $this->assertSame(65, Lesson::count());
        $this->assertSame('البداية', Module::where('slug', 'basics')->value('title_ar'));
        foreach (Lesson::where('module_id', Module::where('slug', 'basics')->value('id'))->with('problems')->get() as $lesson) {
            $this->assertNotEmpty($lesson->title_ar, $lesson->slug);
            $this->assertNotEmpty($lesson->body_ar, $lesson->slug);
            $this->assertNotEmpty($lesson->problems[0]->title_ar, $lesson->slug);
            $this->assertNotEmpty($lesson->problems[0]->description_ar, $lesson->slug);
        }
        // Untranslated lessons keep NULL, never "".
        $translated = array_keys(require database_path('seeders/data/ar/lessons.php'));
        $this->assertSame(0, Lesson::whereNotIn('slug', $translated)->whereNotNull('body_ar')->count());
        // The seeder writes no audit row; only the command does.
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_on_an_empty_database_it_imports_what_the_seeder_imports(): void
    {
        // Rows in creation order without their ids, which differ between two imports.
        $withoutIds = fn () => array_map(fn ($rows) => array_map(fn ($row) => array_diff_key($row, array_flip(['id', 'module_id', 'lesson_id', 'problem_id'])), $rows), $this->contentSnapshot());

        $this->seed(ContentSeeder::class);
        $seeded = $withoutIds();
        ProblemTestCase::query()->delete();
        Problem::query()->delete();
        Lesson::query()->delete();
        Module::query()->delete();

        $this->artisan('content:sync')
            ->expectsOutputToContain('Added: 19 modules, 65 lessons, 65 problems')
            ->expectsOutputToContain('Arabic fields filled: '.$this->arabicFieldCount().'.')
            ->assertExitCode(0);

        $this->assertSame($seeded, $withoutIds());
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $this->artisan('content:sync')->assertExitCode(0);
        $before = $this->contentSnapshot();

        $this->artisan('content:sync')
            ->expectsOutputToContain('Added: 0 modules, 0 lessons, 0 problems, 0 test cases.')
            ->expectsOutputToContain('Arabic fields filled: 0.')
            ->expectsOutputToContain('Arabic fields skipped because a value already exists: '.$this->arabicFieldCount().'.')
            ->assertExitCode(0);

        $this->assertSame($before, $this->contentSnapshot());
    }

    public function test_an_admin_edited_english_title_is_never_overwritten(): void
    {
        $this->seed(ContentSeeder::class);
        Lesson::where('slug', 'warm-up')->update(['title' => 'Edited by an admin']);
        Problem::where('slug', 'warm-up')->update(['description' => 'Edited statement']);
        Module::where('slug', 'basics')->update(['title' => 'Edited module']);

        $this->artisan('content:sync')->assertExitCode(0);

        $this->assertSame('Edited by an admin', Lesson::where('slug', 'warm-up')->value('title'));
        $this->assertSame('Edited statement', Problem::where('slug', 'warm-up')->value('description'));
        $this->assertSame('Edited module', Module::where('slug', 'basics')->value('title'));
    }

    public function test_an_arabic_field_an_admin_filled_is_never_overwritten(): void
    {
        $this->seed(ContentSeeder::class);
        Lesson::where('slug', 'warm-up')->update(['title_ar' => 'عنوان المشرف', 'body_ar' => 'نص المشرف']);
        Problem::where('slug', 'two-sum')->update(['title_ar' => 'ترجمة المشرف']);

        $this->artisan('content:sync')->assertExitCode(0);

        $this->assertSame('عنوان المشرف', Lesson::where('slug', 'warm-up')->value('title_ar'));
        $this->assertSame('نص المشرف', Lesson::where('slug', 'warm-up')->value('body_ar'));
        $this->assertSame('ترجمة المشرف', Problem::where('slug', 'two-sum')->value('title_ar'));
    }

    public function test_empty_arabic_fields_are_filled(): void
    {
        $this->seed(ContentSeeder::class);
        // As after `migrate` on an existing database: the Arabic columns are empty.
        Module::query()->update(['title_ar' => null]);
        Lesson::query()->update(['title_ar' => null, 'body_ar' => null]);
        Problem::query()->update(['title_ar' => null, 'description_ar' => null]);
        // An empty string written around the model counts as empty too.
        Lesson::where('slug', 'warm-up')->update(['title_ar' => '']);

        $this->artisan('content:sync')
            ->expectsOutputToContain('Added: 0 modules, 0 lessons, 0 problems, 0 test cases.')
            ->expectsOutputToContain('Arabic fields filled: '.$this->arabicFieldCount().'.')
            ->expectsOutputToContain('skipped because a value already exists: 0.')
            ->assertExitCode(0);

        $arabic = require database_path('seeders/data/ar/lessons.php');
        $this->assertSame($arabic['warm-up']['title'], Lesson::where('slug', 'warm-up')->value('title_ar'));
        $this->assertSame($arabic['sum-of-an-array']['body'], Lesson::where('slug', 'sum-of-an-array')->value('body_ar'));
        $this->assertSame('البداية', Module::where('slug', 'basics')->value('title_ar'));
    }

    public function test_a_new_lesson_in_the_seed_data_is_added_with_its_problem_and_tests(): void
    {
        $this->seed(ContentSeeder::class);
        // The database has every lesson but this one, as if it were added to the seed files later.
        $expectedTests = Problem::where('slug', 'maximum-of-an-array')->firstOrFail()->testCases()->orderBy('position')->get(['input', 'expected_output', 'is_hidden'])->toArray();
        Problem::where('slug', 'maximum-of-an-array')->delete();
        Lesson::where('slug', 'maximum-of-an-array')->delete();

        $this->artisan('content:sync')
            ->expectsOutputToContain('Added: 0 modules, 1 lessons, 1 problems, '.count($expectedTests).' test cases.')
            ->assertExitCode(0);

        $lesson = Lesson::where('slug', 'maximum-of-an-array')->firstOrFail();
        $problem = $lesson->problems()->firstOrFail();
        $this->assertSame('basics', $lesson->module->slug);
        $this->assertSame('Maximum of an array', $lesson->title);
        $this->assertSame((require database_path('seeders/data/ar/lessons.php'))['maximum-of-an-array']['title'], $lesson->title_ar);
        $this->assertSame('maximum-of-an-array', $problem->slug);
        $this->assertSame(10, $problem->points);
        $this->assertSame($expectedTests, $problem->testCases()->orderBy('position')->get(['input', 'expected_output', 'is_hidden'])->toArray());
    }

    public function test_the_test_cases_of_existing_problems_are_untouched(): void
    {
        $this->seed(ContentSeeder::class);
        $problem = Problem::where('slug', 'warm-up')->firstOrFail();
        $problem->testCases()->first()->update(['expected_output' => 'EDITED']);
        $problem->testCases()->orderByDesc('position')->first()->delete();
        $problem->testCases()->create(['input' => 'ADMIN-IN', 'expected_output' => 'ADMIN-OUT', 'is_hidden' => true]);
        $before = ProblemTestCase::orderBy('id')->get()->toArray();

        $this->artisan('content:sync')->expectsOutputToContain('0 test cases.')->assertExitCode(0);

        $this->assertSame($before, ProblemTestCase::orderBy('id')->get()->toArray());
    }

    public function test_dry_run_prints_the_summary_and_writes_nothing(): void
    {
        $this->seed(ContentSeeder::class);
        Lesson::query()->update(['title_ar' => null, 'body_ar' => null]);
        Problem::where('slug', 'warm-up')->delete();
        Lesson::where('slug', 'warm-up')->delete();
        $before = $this->contentSnapshot();
        // The new lesson's two fields and its problem's two, plus the title and body of every
        // other translated lesson.
        $filled = 4 + 2 * (count(require database_path('seeders/data/ar/lessons.php')) - 1);

        $this->artisan('content:sync', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run: nothing was written.')
            ->expectsOutputToContain('Added: 0 modules, 1 lessons, 1 problems, 6 test cases.')
            ->expectsOutputToContain("Arabic fields filled: $filled.")
            ->assertExitCode(0);

        $this->assertSame($before, $this->contentSnapshot());
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_a_run_records_one_console_audit_row_with_the_counts(): void
    {
        $this->artisan('content:sync')->assertExitCode(0);

        $row = AdminAuditLog::sole();
        $this->assertSame('content.sync', $row->action);
        $this->assertSame('console', $row->actor_email);
        $this->assertNull($row->actor_id);
        $this->assertNull($row->target_type);
        $this->assertNull($row->ip);
        $this->assertSame(['modules' => 19, 'lessons' => 65, 'problems' => 65], array_intersect_key($row->details, ['modules' => 0, 'lessons' => 0, 'problems' => 0]));
        $this->assertSame($this->arabicFieldCount(), $row->details['arabic_filled']);
        $this->assertSame(ProblemTestCase::count(), $row->details['test_cases']);
    }

    public function test_the_arabic_files_only_name_slugs_from_the_english_files(): void
    {
        $slugs = [];
        foreach (require database_path('seeders/data/lessons.php') as $module) {
            $slugs[] = $module['slug'];
            foreach ($module['lessons'] as $lesson) {
                $slugs[] = $lesson['slug'];
            }
        }

        foreach (['modules', 'lessons', 'problems'] as $name) {
            foreach (array_keys(require database_path("seeders/data/ar/$name.php")) as $slug) {
                $this->assertContains($slug, $slugs, "ar/$name.php: $slug");
            }
        }
    }
}
