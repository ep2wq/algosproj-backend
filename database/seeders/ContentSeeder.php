<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::create([
            'slug' => 'algorithms',
            'title' => 'Algorithms',
            'track' => 'algorithms',
            'sort_order' => 1,
        ]);

        $lesson = $module->lessons()->create([
            'slug' => 'warm-up',
            'title' => 'Warm-up',
            'body' => 'A first exercise to get used to reading input and printing output.',
            'sort_order' => 1,
        ]);

        $problem = $lesson->problems()->create([
            'title' => 'Double the number',
            'description' => 'Read an integer from standard input and print it doubled.',
            'difficulty' => 'easy',
            'points' => 10,
        ]);

        $problem->testCases()->createMany([
            ['input' => '2', 'expected_output' => '4', 'is_hidden' => false],
            ['input' => '21', 'expected_output' => '42', 'is_hidden' => false],
            ['input' => '-7', 'expected_output' => '-14', 'is_hidden' => true],
            ['input' => '1000000', 'expected_output' => '2000000', 'is_hidden' => true],
        ]);
    }
}
