<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $limit = min(max((int) $request->query('limit', 50), 1), 100);

        $users = User::where('points', '>', 0)
            ->orderByDesc('points')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['name', 'points']);

        // Same rank rule as /leaderboard/me: 1 + number of users with more points.
        $rank = 0;
        $previousPoints = null;

        return response()->json($users->values()->map(function ($user, $index) use (&$rank, &$previousPoints) {
            if ($user->points !== $previousPoints) {
                $rank = $index + 1;
                $previousPoints = $user->points;
            }

            return ['rank' => $rank, 'name' => $user->name, 'points' => $user->points];
        }));
    }

    public function me(Request $request)
    {
        $points = $request->user()->points;

        return response()->json([
            'rank' => 1 + User::where('points', '>', $points)->count(),
            'points' => $points,
        ]);
    }
}
