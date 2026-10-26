<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ActivityController extends Controller
{
    // Admins and owners: one user's activity, newest first.
    public function forUser(User $user)
    {
        return response()->json($user->activityLogs()->orderByDesc('id')->paginate(25));
    }

    // Owner only: everyone's activity, newest first. Read-only, like the audit log.
    public function index(Request $request)
    {
        $data = $request->validate([
            'event' => ['nullable', 'string', 'max:255'],
            // A user id (UUID), or an email (also finds failed logins for unknown emails).
            'user' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $query = UserActivityLog::query()->orderByDesc('id');

        if (isset($data['event'])) {
            $query->where('event', $data['event']);
        }
        if (isset($data['user'])) {
            Str::isUuid($data['user']) ? $query->where('user_id', $data['user']) : $query->where('email', $data['user']);
        }
        // Whole days, in the app's timezone (UTC), both ends included.
        if (isset($data['from'])) {
            $query->where('created_at', '>=', $data['from'].' 00:00:00');
        }
        if (isset($data['to'])) {
            $query->where('created_at', '<', date('Y-m-d', strtotime($data['to'].' +1 day')).' 00:00:00');
        }

        return response()->json($query->paginate(50));
    }
}
