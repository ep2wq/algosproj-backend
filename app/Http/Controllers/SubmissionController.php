<?php

namespace App\Http\Controllers;

use App\Models\Completion;
use App\Models\Problem;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SubmissionController extends Controller
{
    private const STATUSES = ['passed', 'failed', 'error', 'timeout'];

    public function submit(Request $request, Problem $problem)
    {
        $data = $request->validate([
            'language' => ['required', 'string', Rule::in(config('services.sandbox.languages'))],
            'code' => ['required', 'string', 'max:65536'],
        ]);

        // All test cases, hidden ones included, are sent to the sandbox.
        $testCases = $problem->testCases()->orderBy('id')->get();

        try {
            $response = Http::timeout(60)
                ->withHeaders(['X-Sandbox-Secret' => config('services.sandbox.secret')])
                ->post(rtrim(config('services.sandbox.url'), '/').'/run', [
                    'language' => $data['language'],
                    'code' => $data['code'],
                    'test_cases' => $testCases->map(fn ($testCase) => [
                        'input' => $testCase->input,
                        'expected_output' => $testCase->expected_output,
                    ])->all(),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Sandbox unreachable', ['error' => $e->getMessage()]);

            return $this->runnerUnavailable();
        }

        if (! $response->successful() || ! in_array($response->json('status'), self::STATUSES, true)) {
            Log::warning('Sandbox returned an unusable response', ['status' => $response->status()]);

            return $this->runnerUnavailable();
        }

        $results = $response->json('results') ?? [];
        $passedCount = collect($results)->where('passed', true)->count();

        $user = $request->user();
        $user->submissions()->create([
            'problem_id' => $problem->id,
            'language' => $data['language'],
            'code' => $data['code'],
            'status' => $response->json('status'),
            'passed_count' => $passedCount,
            'total_count' => $testCases->count(),
            'runtime_ms' => $response->json('runtime_ms'),
        ]);

        $pointsAwarded = 0;
        if ($response->json('status') === 'passed') {
            $pointsAwarded = $this->awardCompletion($user, $problem);
        }

        return response()->json([
            'status' => $response->json('status'),
            'passed_count' => $passedCount,
            'total_count' => $testCases->count(),
            'runtime_ms' => $response->json('runtime_ms'),
            'compile_error' => $response->json('compile_error'),
            'points_awarded' => $pointsAwarded,
            // Hidden test cases only reveal whether they passed.
            'results' => collect($results)->map(function ($result, $index) use ($testCases) {
                $testCase = $testCases[$index] ?? null;

                if (! $testCase || $testCase->is_hidden) {
                    return ['passed' => (bool) ($result['passed'] ?? false)];
                }

                return [
                    'passed' => (bool) ($result['passed'] ?? false),
                    'input' => $testCase->input,
                    'expected' => $testCase->expected_output,
                    'actual' => $result['actual'] ?? null,
                    'error' => $result['error'] ?? null,
                ];
            })->values(),
        ]);
    }

    private function awardCompletion(User $user, Problem $problem): int
    {
        if (Completion::where('user_id', $user->id)->where('problem_id', $problem->id)->exists()) {
            return 0;
        }

        try {
            DB::transaction(function () use ($user, $problem) {
                Completion::create([
                    'user_id' => $user->id,
                    'problem_id' => $problem->id,
                    'completed_at' => now(),
                ]);
                User::whereKey($user->id)->increment('points', $problem->points);
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent submission already completed this problem.
            return 0;
        }

        return $problem->points;
    }

    private function runnerUnavailable()
    {
        return response()->json(['message' => 'Code runner unavailable, try again shortly'], 503);
    }
}
