<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ModuleController extends Controller
{
    public function index()
    {
        // Each module with its lessons (no body), so the content list needs one request. The id
        // only makes equal sort_order values come back in a stable order.
        return response()->json(Module::with(['lessons' => fn ($query) => $query
            ->select('id', 'module_id', 'slug', 'title', 'sort_order')
            ->orderBy('sort_order')
            ->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get());
    }

    public function store(Request $request)
    {
        // New modules join the Algorithms track; the admin area does not edit tracks.
        $data = $this->validated($request) + ['track' => 'algorithms'];
        $module = DB::transaction(function () use ($request, $data) {
            $module = Module::create($data);
            AdminAuditLog::record($request->user(), 'module.create', $module, $module->title);

            return $module;
        });

        return response()->json($module, 201);
    }

    public function update(Request $request, Module $module)
    {
        $module->fill($this->validated($request, $module));
        $changes = AdminAuditLog::changes($module);
        DB::transaction(function () use ($request, $module, $changes) {
            $module->save();
            AdminAuditLog::record($request->user(), 'module.update', $module, $module->title, ['changes' => $changes]);
        });

        return response()->json($module);
    }

    public function destroy(Request $request, Module $module)
    {
        if ($module->lessons()->exists()) {
            return response()->json(['message' => __('admin.module_has_lessons')], 409);
        }

        DB::transaction(function () use ($request, $module) {
            $module->delete();
            AdminAuditLog::record($request->user(), 'module.delete', $module, $module->title);
        });

        return response()->noContent();
    }

    private function validated(Request $request, ?Module $module = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            // Arabic is optional; an empty value is stored as NULL (not translated).
            'title_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', Rule::unique('modules')->ignore($module)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ], ['slug.regex' => __('admin.slug_format')]);
    }
}
