<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Module;

class LessonController extends Controller
{
    // Titles in the request's language (HasArabicText); the *_ar columns are never sent.
    public function modules()
    {
        $modules = Module::with(['lessons' => fn ($query) => $query
            ->select('id', 'module_id', 'slug', 'title', 'title_ar', 'sort_order')
            ->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return response()->json($modules->map(fn ($module) => [
            'id' => $module->id,
            'slug' => $module->slug,
            'title' => $module->localized('title'),
            'track' => $module->track,
            'sort_order' => $module->sort_order,
            'lessons' => $module->lessons->map(fn ($lesson) => [
                'id' => $lesson->id,
                'module_id' => $lesson->module_id,
                'slug' => $lesson->slug,
                'title' => $lesson->localized('title'),
                'sort_order' => $lesson->sort_order,
            ]),
        ]));
    }

    public function show(string $slug)
    {
        $lesson = Lesson::where('slug', $slug)
            ->with(['problems' => fn ($query) => $query->orderBy('created_at')->orderBy('id'), 'problems.visibleTestCases'])
            ->firstOrFail();

        return response()->json([
            'id' => $lesson->id,
            'module_id' => $lesson->module_id,
            'slug' => $lesson->slug,
            'title' => $lesson->localized('title'),
            'body' => $lesson->localized('body'),
            // The language body is in, so the page can set lang and dir when Arabic falls back.
            'body_lang' => $lesson->localizedLang('body'),
            'problems' => $lesson->problems->map(fn ($problem) => $problem->editorData()),
        ]);
    }
}
