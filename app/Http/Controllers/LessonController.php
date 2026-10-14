<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Module;

class LessonController extends Controller
{
    public function modules()
    {
        $modules = Module::with(['lessons' => fn ($query) => $query
            ->select('id', 'module_id', 'slug', 'title', 'sort_order')
            ->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return response()->json($modules);
    }

    public function show(string $slug)
    {
        $lesson = Lesson::where('slug', $slug)
            ->with(['problems' => fn ($query) => $query->orderBy('id'), 'problems.visibleTestCases'])
            ->firstOrFail();

        return response()->json([
            'id' => $lesson->id,
            'module_id' => $lesson->module_id,
            'slug' => $lesson->slug,
            'title' => $lesson->title,
            'body' => $lesson->body,
            'problems' => $lesson->problems->map(fn ($problem) => [
                'id' => $problem->id,
                'title' => $problem->title,
                'description' => $problem->description,
                'difficulty' => $problem->difficulty,
                'points' => $problem->points,
                'test_cases' => $problem->visibleTestCases->map(fn ($testCase) => [
                    'id' => $testCase->id,
                    'input' => $testCase->input,
                    'expected_output' => $testCase->expected_output,
                ]),
            ]),
        ]);
    }
}
