<?php

namespace App\Http\Controllers;

use App\Models\Completion;
use App\Models\Problem;
use Illuminate\Support\Facades\Cache;

class ChallengeController extends Controller
{
    public function index()
    {
        // The list is the same for everyone, so it is cached per language for 60 seconds, keyed by
        // problem id (plain arrays). Only `solved` depends on the requester and is added afterwards.
        $rows = Cache::remember('content.challenges.'.app()->getLocale(), 60, fn () => $this->challengeRows());
        $solved = $this->solvedProblemIds();

        return response()->json(collect($rows)
            ->map(fn ($row, $id) => $row + ['solved' => isset($solved[$id])])
            ->values());
    }

    private function challengeRows(): array
    {
        // Module order, then lesson order; standalone problems (no lesson) last, oldest first. The
        // id only breaks ties of the same second (UUIDs carry no order).
        return Problem::query()
            ->select('problems.id', 'problems.lesson_id', 'problems.slug', 'problems.title', 'problems.title_ar', 'problems.difficulty', 'problems.points')
            ->leftJoin('lessons', 'lessons.id', '=', 'problems.lesson_id')
            ->leftJoin('modules', 'modules.id', '=', 'lessons.module_id')
            ->with(['lesson:id,module_id,slug', 'lesson.module:id,slug,title,title_ar'])
            ->orderByRaw('problems.lesson_id is null')
            ->orderBy('modules.sort_order')
            ->orderBy('lessons.sort_order')
            ->orderBy('problems.created_at')
            ->orderBy('problems.id')
            ->get()
            ->mapWithKeys(fn ($problem) => [$problem->id => [
                'slug' => $problem->slug,
                'title' => $problem->localized('title'),
                'difficulty' => $problem->difficulty,
                'points' => $problem->points,
                'topic' => $this->topic($problem),
                'lesson_slug' => $problem->lesson?->slug,
            ]])
            ->all();
    }

    public function show(string $slug)
    {
        $problem = Problem::where('slug', $slug)
            ->with(['visibleTestCases', 'lesson:id,module_id,slug', 'lesson.module:id,slug,title,title_ar'])
            ->firstOrFail();

        $userId = auth('web')->id();

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

        return $module ? ['slug' => $module->slug, 'title' => $module->localized('title')] : null;
    }

    // The routes are public; a token, if sent, marks the requester's solved problems
    // (the same way the leaderboard marks is_you). Guests have none.
    private function solvedProblemIds(): array
    {
        $userId = auth('web')->id();

        return $userId ? Completion::where('user_id', $userId)->pluck('problem_id')->flip()->all() : [];
    }
}
