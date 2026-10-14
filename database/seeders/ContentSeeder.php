<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    private const POINTS = [
        'easy' => 10,
        'medium' => 20,
        'hard' => 30,
    ];

    public function run(): void
    {
        // Modules, lessons (one problem each) and test cases live in data/lessons.php.
        foreach (require __DIR__.'/data/lessons.php' as $moduleIndex => $moduleData) {
            $module = Module::create([
                'slug' => $moduleData['slug'],
                'title' => $moduleData['title'],
                'track' => $moduleData['track'],
                'sort_order' => $moduleIndex + 1,
            ]);

            foreach ($moduleData['lessons'] as $lessonIndex => $lessonData) {
                $lesson = $module->lessons()->create([
                    'slug' => $lessonData['slug'],
                    'title' => $lessonData['title'],
                    'body' => $lessonData['body'],
                    'sort_order' => $lessonIndex + 1,
                ]);

                $problemData = $lessonData['problem'];
                $problem = $lesson->problems()->create([
                    'title' => $problemData['title'],
                    'description' => $problemData['description'],
                    'difficulty' => $problemData['difficulty'],
                    'points' => self::POINTS[$problemData['difficulty']],
                ]);

                $problem->testCases()->createMany($problemData['test_cases']);
            }
        }
    }
}
