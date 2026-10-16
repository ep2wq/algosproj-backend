<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    // Owner only. Read-only: there is no endpoint that edits or deletes audit rows.
    public function index(Request $request)
    {
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:255'],
            // An actor id, or an email ("console" for the artisan command).
            'actor' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $query = AdminAuditLog::query()->orderByDesc('id');

        if (isset($data['action'])) {
            $query->where('action', $data['action']);
        }
        if (isset($data['actor'])) {
            ctype_digit($data['actor']) ? $query->where('actor_id', (int) $data['actor']) : $query->where('actor_email', $data['actor']);
        }
        // Whole days, in the app's timezone (UTC), both ends included.
        if (isset($data['from'])) {
            $query->where('created_at', '>=', $data['from'].' 00:00:00');
        }
        if (isset($data['to'])) {
            $query->where('created_at', '<', date('Y-m-d', strtotime($data['to'].' +1 day')).' 00:00:00');
        }

        return response()->json($query->paginate(50));
    }
}
