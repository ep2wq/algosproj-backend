<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

use function Illuminate\Support\defer;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'email_verified_at', 'banned_at', 'ban_reason', 'terms_accepted_at', 'terms_version'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    // role ('user', 'admin' or 'owner') is not fillable. An owner changes admins through the API;
    // owners only change through `php artisan user:make-admin`. terms_accepted_at and
    // terms_version are not fillable either: only register sets them. email_verified_at is
    // hidden: responses carry only the email_verified boolean (AuthController).
    protected $attributes = [
        'points' => 0,
        'role' => 'user',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'points' => 'integer',
            'banned_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }

    // Logs the user out everywhere: deletes their session rows (the database session driver) and
    // gives them a new remember token, so no "remember me" cookie works any more. Returns the
    // number of sessions deleted.
    public function endSessions(): int
    {
        $this->setRememberToken(Str::random(60));
        $this->save();

        return DB::table(config('session.table'))->where('user_id', $this->id)->delete();
    }

    // The verification email goes out after the response (README "Email").
    public function sendEmailVerificationNotification(): void
    {
        $this->notifyAfterResponse(new VerifyEmail);
    }

    // Sends a mail notification once the response has been sent (no queue worker runs), in the
    // language of the request that asked for it. A failure never reaches the user: it is logged
    // without the address, the link or the token, and the user can ask again.
    public function notifyAfterResponse(Notification $notification): void
    {
        $locale = app()->getLocale();

        defer(function () use ($notification, $locale) {
            try {
                $this->notify($notification->locale($locale));
            } catch (Throwable $e) {
                self::logMailFailure(class_basename($notification), $e);
            }
        });
    }

    public static function logMailFailure(string $notification, Throwable $e): void
    {
        // Only class names: a mailer's error text can contain the address.
        Log::warning('Mail could not be sent', ['notification' => $notification, 'error' => $e::class]);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(Completion::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class);
    }
}
