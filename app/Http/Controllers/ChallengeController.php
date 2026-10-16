<?php

namespace App\Http\Controllers;

use App\Models\Completion;
use App\Models\Problem;

class ChallengeController extends Controller
{
    public function index()
    {
        // Module order, then lesson order; standalone problems (no lesson) last, oldest first.
        $problems = Problem::query()
            ->select('problems.id', 'problems.lesson_id', 'problems.slug', 'problems.title', 'problems.difficulty', 'problems.points')
            ->leftJoin('lessons', 'lessons.id', '=', 'problems.lesson_id')
            ->leftJoin('modules', 'modules.id', '=', 'lessons.module_id')
            ->with(['lesson:id,module_id,slug', 'lesson.module:id,slug,title'])
            ->orderByRaw('problems.lesson_id is null')
            ->orderBy('modules.sort_order')
            ->orderBy('lessons.sort_order')
            ->orderBy('problems.id')
            ->get();

        $solved = $this->solvedProblemIds();

        return response()->json($problems->map(fn ($problem) => [
            'slug' => $problem->slug,
            'title' => $problem->title,
            'difficulty' => $problem->difficulty,
            'points' => $problem->points,
            'topic' => $this->topic($problem),
            'lesson_slug' => $problem->lesson?->slug,
            'solved' => isset($solved[$problem->id]),
        ]));
    }

    public function show(string $slug)
    {
        $problem = Problem::where('slug', $slug)
            ->with(['visibleTestCases', 'lesson:id,module_id,slug', 'lesson.module:id,slug,title'])
            ->firstOrFail();

        $userId = auth('sanctum')->id();

        return response()->json($problem->editorData() + [
            'slug' => $problem->slug,
            'topic' => $this->topic($problem),
            'lesson_slug' => $problem->lesson?->slug,
            'solved' => $userId !== null && Completion::where('user_id', $userId)->where('problem_id', $problem->id)->exists(),
        ]);
    }

    private function topic(Problem $problem): ?array
    {
        $module = $problem->lesson?->module;

        return $module ? ['slug' => $module->slug, 'title' => $module->title] : null;
    }

    // The routes are public; a token, if sent, marks the requester's solved problems
    // (the same way the leaderboard marks is_you). Guests have none.
    private function solvedProblemIds(): array
    {
        $userId = auth('sanctum')->id();

        return $userId ? Completion::where('user_id', $userId)->pluck('problem_id')->flip()->all() : [];
    }
}
