<?php

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Models\UserActivityLog;
use Database\Seeders\ContentImport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
    // Audited like an admin action, with "console" as the actor.
    DB::transaction(function () use ($user, $role) {
        $old = $user->role;
        $user->role = $role;
        $user->save();
        AdminAuditLog::record(null, 'user.role_change', $user, $user->email, ['old' => $old, 'new' => $role]);
    });

    $this->info(match ($role) {
        'user' => "$email is now a normal user.",
        'admin' => "$email is now an admin.",
        'owner' => "$email is now an owner.",
    });

    return 0;
})->purpose('Make a user an admin, an owner with --owner, or a normal user with --remove');

// Not scheduled yet: run it daily in production (README "Deploy checklist").
Artisan::command('activity:prune {--days=90 : Delete rows older than this many days}', function () {
    $days = $this->option('days');

    if (! ctype_digit((string) $days) || (int) $days < 1) {
        $this->error('--days must be a whole number of at least 1.');

        return 1;
    }

    $deleted = UserActivityLog::where('created_at', '<', now()->subDays((int) $days))->delete();
    $this->info("Deleted $deleted activity rows older than $days days.");

    return 0;
})->purpose('Delete user activity log rows older than --days (default 90)');

// Adds seed content the database doesn't have yet and fills empty Arabic columns, in one
// transaction; never overwrites a non-empty value (README "content:sync"). Safe to run again.
Artisan::command('content:sync {--dry-run : Print the summary without writing anything}', function () {
    $dryRun = (bool) $this->option('dry-run');

    $counts = DB::transaction(function () use ($dryRun) {
        $counts = (new ContentImport)->run($dryRun);
        if (! $dryRun) {
            AdminAuditLog::record(null, 'content.sync', null, 'content', $counts);
        }

        return $counts;
    });

    if ($dryRun) {
        $this->warn('Dry run: nothing was written.');
    }
    $this->info("Added: {$counts['modules']} modules, {$counts['lessons']} lessons, {$counts['problems']} problems, {$counts['test_cases']} test cases.");
    $this->info("Arabic fields filled: {$counts['arabic_filled']}.");
    $this->info("Arabic fields skipped because a value already exists: {$counts['arabic_skipped']}.");

    return 0;
})->purpose('Add new seed content and fill empty Arabic fields, never overwriting (--dry-run to preview)');
