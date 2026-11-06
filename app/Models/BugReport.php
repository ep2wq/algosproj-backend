<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

// A report from the "Report a bug" dialog (README "Bug reports"). Everything is sanitized by
// BugReportController before it is stored. The screenshot lives on the private disk; its path is
// never sent, only has_screenshot.
#[Fillable(['user_id', 'contact_email', 'description', 'page_path', 'page_title', 'app_version', 'language', 'theme',
    'user_agent', 'viewport', 'timezone', 'error_context', 'failed_requests', 'console_errors', 'problem_context',
    'included', 'screenshot_path'])]
#[Hidden(['screenshot_path'])]
class BugReport extends Model
{
    use HasUuids;

    const STATUSES = ['new', 'in_progress', 'fixed', 'wont_fix'];

    // The private disk (storage/app/private), never served by the web server.
    const DISK = 'local';

    protected $appends = ['has_screenshot'];

    protected function casts(): array
    {
        return [
            'error_context' => 'array',
            'failed_requests' => 'array',
            'console_errors' => 'array',
            'problem_context' => 'array',
            'included' => 'array',
            'seen_at' => 'datetime',
            'status_changed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function getHasScreenshotAttribute(): bool
    {
        return $this->screenshot_path !== null;
    }

    public function deleteScreenshot(): void
    {
        if ($this->screenshot_path !== null) {
            Storage::disk(self::DISK)->delete($this->screenshot_path);
        }
    }
}
