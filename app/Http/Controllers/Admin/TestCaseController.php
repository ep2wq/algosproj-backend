<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Problem;
use App\Models\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'test_cases' => __('admin.test_cases_max', ['max' => self::MAX_CASES]),
            ]);
        }
        $this->checkTotalBytes($problem, $data);

        $testCase = DB::transaction(function () use ($request, $problem, $data) {
            $testCase = $problem->testCases()->create($data);
            AdminAuditLog::record($request->user(), 'test_case.create', $testCase, $this->label($problem, $testCase));

            return $testCase;
        });

        return response()->json($testCase, 201);
    }

    // Scoped binding (see routes/api.php): a test case of another problem is a 404.
    public function update(Request $request, Problem $problem, TestCase $testCase)
    {
        $data = $this->validated($request);
        $this->checkTotalBytes($problem, $data, $testCase);

        $testCase->fill($data);
        // Input and expected output are not logged, only that they changed.
        $changes = AdminAuditLog::changes($testCase, ['input', 'expected_output']);
        DB::transaction(function () use ($request, $problem, $testCase, $changes) {
            $testCase->save();
            AdminAuditLog::record($request->user(), 'test_case.update', $testCase, $this->label($problem, $testCase), ['changes' => $changes]);
        });

        return response()->json($testCase);
    }

    public function destroy(Request $request, Problem $problem, TestCase $testCase)
    {
        DB::transaction(function () use ($request, $problem, $testCase) {
            $testCase->delete();
            AdminAuditLog::record($request->user(), 'test_case.delete', $testCase, $this->label($problem, $testCase));
        });

        return response()->noContent();
    }

    private function label(Problem $problem, TestCase $testCase): string
    {
        return "Test case {$testCase->id} of {$problem->title}";
    }

    private function validated(Request $request): array
    {
        $maxBytes = function ($attribute, $value, $fail) {
            if (is_string($value) && strlen($value) > self::MAX_FIELD_BYTES) {
                $fail(__('admin.test_field_too_large', ['field' => str_replace('_', ' ', $attribute)]));
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
                'test_cases' => __('admin.test_cases_total', ['total' => $total]),
            ]);
        }
    }
}
