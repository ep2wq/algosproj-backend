<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// No API endpoint can change a role; this command is the only way.
Artisan::command('user:make-admin {email} {--remove : Make the user a normal user again}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("No user has the email $email.");

        return 1;
    }

    $user->role = $this->option('remove') ? 'user' : 'admin';
    $user->save();

    $this->info($this->option('remove') ? "$email is now a normal user." : "$email is now an admin.");

    return 0;
})->purpose('Give a user the admin role, or remove it with --remove');
