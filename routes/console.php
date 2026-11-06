<?php

use App\Models\AdminAuditLog;
use App\Models\BannedIp;
use App\Models\BugReport;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserActivityLog;
use Database\Seeders\ContentImport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

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
        // An explicit server-side action by the owner, so it also counts as a verified email.
        if (! $user->hasVerifiedEmail()) {
            $user->email_verified_at = now();
        }
        $user->save();
        AdminAuditLog::record(null, 'user.role_change', $user, $user->email, ['old' => $old, 'new' => $role]);
    });

    $this->info(match ($role) {
        'user' => "$email is now a normal user.",
        'admin' => "$email is now an admin.",
        'owner' => "$email is now an owner.",
    });
    $this->info("The email $email is marked as verified.");

    return 0;
})->purpose('Make a user an admin, an owner with --owner, or a normal user with --remove');

// Scheduled daily below; the host needs a cron entry for `schedule:run` (README "Deploy checklist").
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

Schedule::command('activity:prune')->daily();

// Retention of security events and IP bans (config/security.php, README "Security events and IP bans").
// Scheduled daily below, like activity:prune.
Artisan::command('security:prune', function () {
    $days = (int) config('security.retention_days');
    $banDays = (int) config('security.ban_retention_days');

    $events = SecurityEvent::where('last_seen_at', '<', now()->subDays($days))->delete();
    $bans = BannedIp::whereNotNull('expires_at')->where('expires_at', '<', now()->subDays($banDays))->delete();
    $this->info("Deleted $events security events last seen more than $days days ago and $bans bans that expired more than $banDays days ago.");

    return 0;
})->purpose('Delete old security events and long-expired IP bans');

Schedule::command('security:prune')->daily();

// Retention of bug reports and their screenshots (config/bug_reports.php, README "Bug reports").
// Scheduled daily below, like activity:prune.
Artisan::command('bugreports:prune', function () {
    $closedDays = (int) config('bug_reports.closed_retention_days');
    $days = (int) config('bug_reports.retention_days');

    $reports = BugReport::where(fn ($query) => $query
        ->where(fn ($q) => $q->whereIn('status', ['fixed', 'wont_fix'])->where('status_changed_at', '<', now()->subDays($closedDays)))
        ->orWhere('created_at', '<', now()->subDays($days)))
        ->get(['id', 'screenshot_path']);
    $reports->each(fn (BugReport $report) => $report->deleteScreenshot());
    $deleted = BugReport::whereIn('id', $reports->pluck('id'))->delete();
    $this->info("Deleted $deleted bug reports closed more than $closedDays days ago or created more than $days days ago.");

    return 0;
})->purpose('Delete closed bug reports after 30 days and every bug report after 180 days, with their screenshots');

Schedule::command('bugreports:prune')->daily();

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
    } else {
        // The cached challenge list (ChallengeController::index) shows the new problems now.
        foreach (['en', 'ar'] as $locale) {
            Cache::forget("content.challenges.$locale");
        }
    }
    $this->info("Added: {$counts['modules']} modules, {$counts['lessons']} lessons, {$counts['problems']} problems, {$counts['test_cases']} test cases.");
    $this->info("Arabic fields filled: {$counts['arabic_filled']}.");
    $this->info("Arabic fields skipped because a value already exists: {$counts['arabic_skipped']}.");

    return 0;
})->purpose('Add new seed content and fill empty Arabic fields, never overwriting (--dry-run to preview)');
