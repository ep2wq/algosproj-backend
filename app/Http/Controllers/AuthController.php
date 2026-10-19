<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create($data)->refresh();
        UserActivityLog::record($user, $user->email, 'register', $request);

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            // Only the attempted email is kept, never the password.
            UserActivityLog::record($user, $user->email ?? $data['email'], 'login_failed', $request);

            return response()->json(['message' => __('messages.invalid_credentials')], 401);
        }

        // Checked after the password, so the ban (and its reason) is only shown to the account holder.
        if ($user->banned_at !== null) {
            UserActivityLog::record($user, $user->email, 'login_blocked_banned', $request);

            return response()->json(['message' => __('messages.banned'), 'reason' => $user->ban_reason], 403);
        }

        UserActivityLog::record($user, $user->email, 'login', $request);

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        UserActivityLog::record($request->user(), $request->user()->email, 'logout', $request);
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('messages.logged_out')]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
