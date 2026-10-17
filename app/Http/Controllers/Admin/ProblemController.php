<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Problem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProblemController extends Controller
{
    // Points always follow difficulty; they are never sent by the admin.
    private const POINTS = [
        'easy' => 10,
        'medium' => 20,
        'hard' => 30,
    ];

    public function index()
    {
        return response()->json(Problem::orderBy('id')
            ->get(['id', 'lesson_id', 'slug', 'title', 'difficulty', 'points']));
    }

    public function show(Problem $problem)
    {
        return response()->json($problem);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $problem = DB::transaction(function () use ($request, $data) {
            $problem = Problem::create($data);
            AdminAuditLog::record($request->user(), 'problem.create', $problem, $problem->title);

            return $problem;
        });

        return response()->json($problem, 201);
    }

    public function update(Request $request, Problem $problem)
    {
        $problem->fill($this->validated($request, $problem));
        $changes = AdminAuditLog::changes($problem);
        DB::transaction(function () use ($request, $problem, $changes) {
            $problem->save();
            AdminAuditLog::record($request->user(), 'problem.update', $problem, $problem->title, ['changes' => $changes]);
        });

        return response()->json($problem);
    }

    public function destroy(Request $request, Problem $problem)
    {
        if ($problem->completions()->exists()) {
            return response()->json(['message' => 'Users have already solved this problem, so it cannot be deleted. Edit it instead.'], 409);
        }

        // Its test cases and submissions are deleted with it (foreign keys cascade).
        DB::transaction(function () use ($request, $problem) {
            $problem->delete();
            AdminAuditLog::record($request->user(), 'problem.delete', $problem, $problem->title);
        });

        return response()->noContent();
    }

    private function validated(Request $request, ?Problem $problem = null): array
    {
        $data = $request->validate([
            // Optional: a problem without a lesson is a standalone challenge.
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            // Arabic is optional; an empty value is stored as NULL (not translated).
            'title_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', Rule::unique('problems')->ignore($problem)],
            // TEXT columns: 5,000 characters are at most 20,000 bytes (4 bytes each), so the
            // character limit also keeps them under the 65,535-byte limit.
            'description' => ['required', 'string', 'max:5000'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'difficulty' => ['required', 'string', Rule::in(array_keys(self::POINTS))],
        ], ['slug.regex' => 'The slug may only contain lowercase letters, digits and hyphens.']);

        return $data + ['lesson_id' => null, 'points' => self::POINTS[$data['difficulty']]];
    }
}
