<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $limit = min(max((int) $request->query('limit', 50), 1), 100);

        // Banned users are left out of the list and of every rank. Equal points: the earlier sign-up
        // first; the id only breaks ties of the same second (UUIDs carry no order).
        $users = User::where('points', '>', 0)
            ->whereNull('banned_at')
            ->orderByDesc('points')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'name', 'points']);

        // The route is public; a logged-in session, if any, marks the requester's row. Ids are not returned.
        $meId = auth('web')->id();

        // Same rank rule as /leaderboard/me: 1 + number of users with more points.
        $rank = 0;
        $previousPoints = null;

        return response()->json($users->values()->map(function ($user, $index) use (&$rank, &$previousPoints, $meId) {
            if ($user->points !== $previousPoints) {
                $rank = $index + 1;
                $previousPoints = $user->points;
            }

            return ['rank' => $rank, 'name' => $user->name, 'points' => $user->points, 'is_you' => $user->id === $meId];
        }));
    }

    public function me(Request $request)
    {
        $points = $request->user()->points;

        return response()->json([
            'rank' => 1 + User::where('points', '>', $points)->whereNull('banned_at')->count(),
            'points' => $points,
        ]);
    }
}
