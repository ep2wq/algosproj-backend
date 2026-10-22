<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'banned_at', 'ban_reason'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // role ('user', 'admin' or 'owner') is not fillable. An owner changes admins through the API;
    // owners only change through `php artisan user:make-admin`.
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
