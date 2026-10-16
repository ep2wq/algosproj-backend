<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Problem;
use App\Models\TestCase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TestCaseController extends Controller
{
    private const MAX_CASES = 50;

    // All inputs plus expected outputs of one problem. Keeps the sandbox payload (base64 JSON,
    // about 120 KB) safe next to 64 KB of code.
    private const MAX_TOTAL_BYTES = 25000;

    private const MAX_FIELD_BYTES = 20000;

    // Admin only, so hidden test cases and their expected output are included.
    public function index(Problem $problem)
    {
        return response()->json($problem->testCases()->orderBy('id')->get());
    }

    public function store(Request $request, Problem $problem)
    {
        $data = $this->validated($request);

        if ($problem->testCases()->count() >= self::MAX_CASES) {
            throw ValidationException::withMessages([
                'test_cases' => 'A problem can have at most '.self::MAX_CASES.' test cases.',
            ]);
        }
        $this->checkTotalBytes($problem, $data);

        return response()->json($problem->testCases()->create($data), 201);
    }

    // Scoped binding (see routes/api.php): a test case of another problem is a 404.
    public function update(Request $request, Problem $problem, TestCase $testCase)
    {
        $data = $this->validated($request);
        $this->checkTotalBytes($problem, $data, $testCase);

        $testCase->update($data);

        return response()->json($testCase);
    }

    public function destroy(Problem $problem, TestCase $testCase)
    {
        $testCase->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        $maxBytes = function ($attribute, $value, $fail) {
            if (is_string($value) && strlen($value) > self::MAX_FIELD_BYTES) {
                $fail('The '.str_replace('_', ' ', $attribute).' may not be larger than 20,000 bytes.');
            }
        };

        $data = $request->validate([
            // Input may be empty (a problem that reads nothing). It is not trimmed (bootstrap/app.php).
            'input' => ['nullable', 'string', $maxBytes],
            'expected_output' => ['required', 'string', $maxBytes],
            'is_hidden' => ['required', 'boolean'],
        ]);

        return ['input' => $data['input'] ?? '', 'expected_output' => $data['expected_output'], 'is_hidden' => (bool) $data['is_hidden']];
    }

    private function checkTotalBytes(Problem $problem, array $data, ?TestCase $replacing = null): void
    {
        $total = strlen($data['input']) + strlen($data['expected_output']);
        foreach ($problem->testCases()->when($replacing, fn ($query) => $query->whereKeyNot($replacing->id))->get() as $testCase) {
            $total += strlen($testCase->input) + strlen($testCase->expected_output);
        }

        if ($total > self::MAX_TOTAL_BYTES) {
            throw ValidationException::withMessages([
                'test_cases' => "All test inputs and expected outputs of a problem may total at most 25,000 bytes; this change would make $total.",
            ]);
        }
    }
}
