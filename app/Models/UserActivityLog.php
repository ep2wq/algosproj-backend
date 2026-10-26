<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

// Append-only: rows are only created here and deleted by `activity:prune`. Never holds a
// password, a token or a request body.
#[Fillable(['user_id', 'email', 'event', 'ip', 'user_agent'])]
class UserActivityLog extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    // For register, login and logout: a logging failure is logged and never breaks the request.
    public static function record(?User $user, string $email, string $event, Request $request): void
    {
        try {
            self::write($user, $email, $event, $request);
        } catch (Throwable $e) {
            Log::error('Could not write user activity', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    // Throws on failure, so it can share a transaction with an admin action (ban, unban). Those
    // pass no request: the IP and browser would be the admin's, which the audit log already has.
    public static function write(?User $user, string $email, string $event, ?Request $request = null): void
    {
        self::create([
            'user_id' => $user?->id,
            'email' => mb_substr($email, 0, 255),
            'event' => $event,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent() === null ? null : mb_substr($request->userAgent(), 0, 255),
        ]);
    }
}
