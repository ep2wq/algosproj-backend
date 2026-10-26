<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\Problem;

// The one import path for course content, used by ContentSeeder and `php artisan content:sync`.
// English lives in data/lessons.php and data/lesson_bodies.php, Arabic in data/ar/*.php, all keyed
// by slug. Adds the modules, lessons, problems (with their test cases) that don't exist yet, and
// fills an Arabic column only while it is empty. It never changes a non-empty value and never
// touches the test cases of an existing problem. Run it inside a transaction.
class ContentImport
{
    private const POINTS = [
        'easy' => 10,
        'medium' => 20,
        'hard' => 30,
    ];

    private bool $dryRun = false;

    private array $counts = [];

    // What was done, or with $dryRun what would be done (nothing is written).
    public function run(bool $dryRun = false): array
    {
        $this->dryRun = $dryRun;
        $this->counts = ['modules' => 0, 'lessons' => 0, 'problems' => 0, 'test_cases' => 0, 'arabic_filled' => 0, 'arabic_skipped' => 0];

        $data = __DIR__.'/data';
        $bodies = require "$data/lesson_bodies.php";
        $arModules = require "$data/ar/modules.php";
        $arLessons = require "$data/ar/lessons.php";
        $arProblems = require "$data/ar/problems.php";

        foreach (require "$data/lessons.php" as $moduleIndex => $moduleData) {
            [$module] = $this->sync(Module::class, 'modules', [
                'slug' => $moduleData['slug'],
                'title' => $moduleData['title'],
                'track' => $moduleData['track'],
                'sort_order' => $moduleIndex + 1,
            ], ['title_ar' => $arModules[$moduleData['slug']]['title'] ?? null]);

            foreach ($moduleData['lessons'] as $lessonIndex => $lessonData) {
                $slug = $lessonData['slug'];
                [$lesson] = $this->sync(Lesson::class, 'lessons', [
                    'module_id' => $module?->id,
                    'slug' => $slug,
                    'title' => $lessonData['title'],
                    'body' => $bodies[$slug],
                    'sort_order' => $lessonIndex + 1,
                ], [
                    'title_ar' => $arLessons[$slug]['title'] ?? null,
                    'body_ar' => $arLessons[$slug]['body'] ?? null,
                ]);

                $problemData = $lessonData['problem'];
                [$problem, $created] = $this->sync(Problem::class, 'problems', [
                    'lesson_id' => $lesson?->id,
                    // One problem per lesson, so the lesson slug is the challenge slug.
                    'slug' => $slug,
                    'title' => $problemData['title'],
                    'description' => $problemData['description'],
                    'difficulty' => $problemData['difficulty'],
                    'points' => self::POINTS[$problemData['difficulty']],
                ], [
                    'title_ar' => $arProblems[$slug]['title'] ?? null,
                    'description_ar' => $arProblems[$slug]['description'] ?? null,
                ]);

                // Test cases only come with a new problem.
                if ($created) {
                    $this->counts['test_cases'] += count($problemData['test_cases']);
                    // position keeps the file's order (ids are UUIDs and carry none).
                    $problem?->testCases()->createMany(array_map(
                        fn ($testCase, $index) => $testCase + ['position' => $index + 1],
                        $problemData['test_cases'],
                        array_keys($problemData['test_cases']),
                    ));
                }
            }
        }

        return $this->counts;
    }

    // Finds the row by slug. A missing row is created from $english and $arabic; an existing row
    // only gets the Arabic values whose column is still empty. Returns [row, created]; the row is
    // null in a dry run when it would have been created.
    private function sync(string $model, string $counter, array $english, array $arabic): array
    {
        $arabic = array_filter($arabic, fn ($value) => filled($value));
        $row = $model::where('slug', $english['slug'])->first();

        if (! $row) {
            $this->counts[$counter]++;
            $this->counts['arabic_filled'] += count($arabic);

            return [$this->dryRun ? null : $model::create($english + $arabic), true];
        }

        foreach ($arabic as $field => $value) {
            if (filled($row->getAttribute($field))) {
                $this->counts['arabic_skipped']++;
            } else {
                $row->setAttribute($field, $value);
                $this->counts['arabic_filled']++;
            }
        }
        if (! $this->dryRun) {
            $row->save();
        }

        return [$row, false];
    }
}
