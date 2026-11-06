<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\BugReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

// Owner only (README "Bug reports"). Status changes and deletes go to the audit log with ids
// only, never the report's text.
class BugReportController extends Controller
{
    // The list leaves out the large parts (code, request lists); the detail has everything.
    private const LIST_COLUMNS = ['id', 'user_id', 'contact_email', 'description', 'status', 'page_path', 'screenshot_path', 'seen_at', 'created_at'];

    private const CONTENT_TYPES = ['png' => 'image/png', 'webp' => 'image/webp', 'jpg' => 'image/jpeg'];

    // 50 per page, newest first.
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(BugReport::STATUSES)],
            'seen' => ['nullable', Rule::in(['seen', 'unseen'])],
            'search' => ['nullable', 'string', 'max:255'],
            'screenshot' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $query = BugReport::query()->select(self::LIST_COLUMNS)->with('user:id,name,email,role')
            ->orderByDesc('created_at')->orderByDesc('id');

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (isset($data['seen'])) {
            $data['seen'] === 'unseen' ? $query->whereNull('seen_at') : $query->whereNotNull('seen_at');
        }
        if (isset($data['search'])) {
            // % and _ in the search are literal; ! is the escape character in both MySQL and SQLite.
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $data['search']).'%';
            $query->where(fn ($q) => $q->whereRaw("description like ? escape '!'", [$like])
                ->orWhereRaw("page_path like ? escape '!'", [$like])
                ->orWhereRaw("contact_email like ? escape '!'", [$like]));
        }
        if (isset($data['screenshot'])) {
            $request->boolean('screenshot') ? $query->whereNotNull('screenshot_path') : $query->whereNull('screenshot_path');
        }
        // Whole days on created_at, in the app's timezone (UTC), both ends included.
        if (isset($data['from'])) {
            $query->where('created_at', '>=', $data['from'].' 00:00:00');
        }
        if (isset($data['to'])) {
            $query->where('created_at', '<', date('Y-m-d', strtotime($data['to'].' +1 day')).' 00:00:00');
        }

        return response()->json($query->paginate(50));
    }

    // For the badge in the admin navigation: new reports nobody has opened yet.
    public function summary()
    {
        return response()->json(['unseen' => BugReport::where('status', 'new')->whereNull('seen_at')->count()]);
    }

    // Opening a report marks it seen.
    public function show(BugReport $bugReport)
    {
        if ($bugReport->seen_at === null) {
            $bugReport->forceFill(['seen_at' => now()])->save();
        }

        return response()->json($bugReport->load('user:id,name,email,role', 'handler:id,name,email'));
    }

    public function update(Request $request, BugReport $bugReport)
    {
        $data = $request->validate([
            'status' => ['sometimes', 'required', Rule::in(BugReport::STATUSES)],
            'admin_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($request, $bugReport, $data) {
            $old = $bugReport->status;
            $bugReport->forceFill(array_intersect_key($data, array_flip(['status', 'admin_note'])) + ['handled_by' => $request->user()->id]);
            if ($bugReport->isDirty('status')) {
                $bugReport->status_changed_at = now();
                AdminAuditLog::record($request->user(), 'bug_report.status', $bugReport, $bugReport->id, ['from' => $old, 'to' => $bugReport->status]);
            }
            $bugReport->save();
        });

        return response()->json($bugReport->refresh()->load('user:id,name,email,role', 'handler:id,name,email'));
    }

    // The private file, always with an image content type of its own extension, never sniffed or cached.
    public function screenshot(BugReport $bugReport)
    {
        $disk = Storage::disk(BugReport::DISK);
        $type = self::CONTENT_TYPES[pathinfo((string) $bugReport->screenshot_path, PATHINFO_EXTENSION)] ?? null;
        abort_if($type === null || ! $disk->exists($bugReport->screenshot_path), 404);

        return $disk->response($bugReport->screenshot_path, 'screenshot', [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Request $request, BugReport $bugReport)
    {
        DB::transaction(function () use ($request, $bugReport) {
            $bugReport->delete();
            AdminAuditLog::record($request->user(), 'bug_report.delete', $bugReport, $bugReport->id, ['status' => $bugReport->status]);
        });
        $bugReport->deleteScreenshot();

        return response()->noContent();
    }
}
