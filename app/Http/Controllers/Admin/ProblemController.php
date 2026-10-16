<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Problem;
use Illuminate\Http\Request;
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
        return response()->json(Problem::create($this->validated($request)), 201);
    }

    public function update(Request $request, Problem $problem)
    {
        $problem->update($this->validated($request, $problem));

        return response()->json($problem);
    }

    public function destroy(Problem $problem)
    {
        if ($problem->completions()->exists()) {
            return response()->json(['message' => 'Users have already solved this problem, so it cannot be deleted. Edit it instead.'], 409);
        }

        // Its test cases and submissions are deleted with it (foreign keys cascade).
        $problem->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Problem $problem = null): array
    {
        $data = $request->validate([
            // Optional: a problem without a lesson is a standalone challenge.
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', Rule::unique('problems')->ignore($problem)],
            'description' => ['required', 'string', 'max:5000'],
            'difficulty' => ['required', 'string', Rule::in(array_keys(self::POINTS))],
        ], ['slug.regex' => 'The slug may only contain lowercase letters, digits and hyphens.']);

        return $data + ['lesson_id' => null, 'points' => self::POINTS[$data['difficulty']]];
    }
}
