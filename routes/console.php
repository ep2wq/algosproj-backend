<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The only way to make or remove an owner. The API can make admins, never owners.
Artisan::command('user:make-admin {email} {--owner : Make the user an owner} {--remove : Make the user a normal user again}', function (string $email) {
    if ($this->option('owner') && $this->option('remove')) {
        $this->error('Use either --owner or --remove, not both.');

        return 1;
    }

    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("No user has the email $email.");

        return 1;
    }

    $role = $this->option('remove') ? 'user' : ($this->option('owner') ? 'owner' : 'admin');
    $user->role = $role;
    $user->save();

    $this->info(match ($role) {
        'user' => "$email is now a normal user.",
        'admin' => "$email is now an admin.",
        'owner' => "$email is now an owner.",
    });

    return 0;
})->purpose('Make a user an admin, an owner with --owner, or a normal user with --remove');
