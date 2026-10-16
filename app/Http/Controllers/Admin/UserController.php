<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['user', 'admin', 'owner'])],
            'banned' => ['nullable', 'boolean'],
        ]);

        $query = User::query()
            ->select(['id', 'name', 'email', 'role', 'points', 'banned_at', 'created_at'])
            ->withCount(['completions as solved_count'])
            ->orderByDesc('id');

        if (isset($data['search'])) {
            // % and _ in the search are literal; ! is the escape character in both MySQL and SQLite.
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $data['search']).'%';
            $query->where(fn ($q) => $q->whereRaw("name like ? escape '!'", [$like])
                ->orWhereRaw("email like ? escape '!'", [$like]));
        }
        if (isset($data['role'])) {
            $query->where('role', $data['role']);
        }
        if (isset($data['banned'])) {
            $request->boolean('banned') ? $query->whereNotNull('banned_at') : $query->whereNull('banned_at');
        }

        return response()->json($query->paginate(25)->through(fn (User $user) => $this->row($user)));
    }

    public function show(User $user)
    {
        $user->loadCount(['completions as solved_count']);

        $solved = $user->completions()->with('problem:id,title,slug')->orderByDesc('completed_at')->get()
            ->map(fn ($completion) => [
                'title' => $completion->problem->title,
                'slug' => $completion->problem->slug,
                'solved_at' => $completion->completed_at,
            ]);

        // Never the code: only the columns listed here are read.
        $submissions = $user->submissions()->with('problem:id,title,slug')->orderByDesc('id')->limit(20)
            ->get(['id', 'problem_id', 'language', 'status', 'passed_count', 'total_count', 'runtime_ms', 'created_at'])
            ->map(fn ($submission) => [
                'id' => $submission->id,
                'problem' => ['title' => $submission->problem->title, 'slug' => $submission->problem->slug],
                'language' => $submission->language,
                'status' => $submission->status,
                'passed_count' => $submission->passed_count,
                'total_count' => $submission->total_count,
                'runtime_ms' => $submission->runtime_ms,
                'created_at' => $submission->created_at,
            ]);

        return response()->json($this->row($user) + [
            'ban_reason' => $user->ban_reason,
            'solved' => $solved,
            'submissions' => $submissions,
        ]);
    }

    public function ban(Request $request, User $user)
    {
        $this->checkTarget($request, $user);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if ($user->banned_at !== null) {
            return response()->json(['message' => 'This user is already banned.'], 409);
        }

        DB::transaction(function () use ($request, $user, $data) {
            $user->forceFill(['banned_at' => now(), 'ban_reason' => $data['reason']])->save();
            $user->tokens()->delete();
            AdminAuditLog::record($request->user(), 'user.ban', $user, $user->email, ['reason' => AdminAuditLog::short($data['reason'])]);
        });

        return $this->rowResponse($user);
    }

    public function unban(Request $request, User $user)
    {
        $this->checkTarget($request, $user);

        if ($user->banned_at === null) {
            return response()->json(['message' => 'This user is not banned.'], 409);
        }

        DB::transaction(function () use ($request, $user) {
            $reason = $user->ban_reason;
            $user->forceFill(['banned_at' => null, 'ban_reason' => null])->save();
            AdminAuditLog::record($request->user(), 'user.unban', $user, $user->email, ['ban_reason' => AdminAuditLog::short($reason)]);
        });

        return $this->rowResponse($user);
    }

    public function logoutEverywhere(Request $request, User $user)
    {
        $this->checkTarget($request, $user);

        DB::transaction(function () use ($request, $user) {
            $deleted = $user->tokens()->delete();
            AdminAuditLog::record($request->user(), 'user.logout_everywhere', $user, $user->email, ['tokens_deleted' => $deleted]);
        });

        return $this->rowResponse($user);
    }

    // Owner only (routes/api.php). The API can make admins, never owners.
    public function updateRole(Request $request, User $user)
    {
        $this->checkTarget($request, $user);
        $data = $request->validate(['role' => ['required', Rule::in(['user', 'admin'])]]);

        DB::transaction(function () use ($request, $user, $data) {
            $old = $user->role;
            $user->role = $data['role'];
            $user->save();
            AdminAuditLog::record($request->user(), 'user.role_change', $user, $user->email, ['old' => $old, 'new' => $user->role]);
        });

        return $this->rowResponse($user);
    }

    // Owner only.
    public function adjustPoints(Request $request, User $user)
    {
        $this->checkTarget($request, $user);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'reason' => ['required', 'string', 'max:255'],
        ], ['amount.not_in' => 'The amount may not be zero.']);

        DB::transaction(function () use ($request, $user, $data) {
            // Locked, so a submission awarding points at the same moment can't be lost.
            $locked = User::lockForUpdate()->findOrFail($user->id);
            if ($locked->points + $data['amount'] < 0) {
                throw ValidationException::withMessages([
                    'amount' => "Points can't go below 0. This user has {$locked->points}.",
                ]);
            }
            $old = $locked->points;
            $locked->points += $data['amount'];
            $locked->save();
            AdminAuditLog::record($request->user(), 'user.points_adjust', $locked, $locked->email, [
                'amount' => $data['amount'],
                'reason' => AdminAuditLog::short($data['reason']),
                'old' => $old,
                'new' => $locked->points,
            ]);
        });

        return $this->rowResponse($user->refresh());
    }

    // Owner only. Submissions stay; completions go and points become 0.
    public function resetProgress(Request $request, User $user)
    {
        $this->checkTarget($request, $user);

        DB::transaction(function () use ($request, $user) {
            $old = $user->points;
            $deleted = $user->completions()->delete();
            $user->points = 0;
            $user->save();
            AdminAuditLog::record($request->user(), 'user.reset_progress', $user, $user->email, ['old_points' => $old, 'completions_deleted' => $deleted]);
        });

        return $this->rowResponse($user);
    }

    // Owner only. Foreign keys delete the user's submissions and completions and set the user to
    // null in both logs; tokens have no foreign key, so they are deleted here.
    public function destroy(Request $request, User $user)
    {
        $this->checkTarget($request, $user);
        $request->validate(['confirm_email' => ['required', 'string']]);

        if ($request->input('confirm_email') !== $user->email) {
            throw ValidationException::withMessages(['confirm_email' => "Type the user's exact email to confirm."]);
        }

        // The confirmation is not logged; the label is the deleted account's email.
        DB::transaction(function () use ($request, $user) {
            $user->tokens()->delete();
            $user->delete();
            AdminAuditLog::record($request->user(), 'user.delete', $user, $user->email, ['name' => AdminAuditLog::short($user->name), 'points' => $user->points]);
        });

        return response()->noContent();
    }

    // Who may act on whom. Every write above checks this first, so a refusal changes nothing.
    private function checkTarget(Request $request, User $target): void
    {
        $actor = $request->user();

        abort_if($target->id === $actor->id, 403, "You can't do this to your own account.");
        abort_if($target->role === 'owner', 403, 'Owners can only be changed with the artisan command.');
        abort_if($actor->role !== 'owner' && $target->role !== 'user', 403, 'Only an owner can do this to an admin.');
    }

    private function rowResponse(User $user)
    {
        return response()->json($this->row($user->loadCount(['completions as solved_count'])));
    }

    // Never the password or tokens.
    private function row(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'points' => $user->points,
            'banned_at' => $user->banned_at,
            'created_at' => $user->created_at,
            'solved_count' => $user->solved_count,
        ];
    }
}
