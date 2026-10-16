<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModuleController extends Controller
{
    public function index()
    {
        // Each module with its lessons (no body), so the content list needs one request.
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
        $module = Module::create($this->validated($request) + ['track' => 'algorithms']);

        return response()->json($module, 201);
    }

    public function update(Request $request, Module $module)
    {
        $module->update($this->validated($request, $module));

        return response()->json($module);
    }

    public function destroy(Module $module)
    {
        if ($module->lessons()->exists()) {
            return response()->json(['message' => 'This module has lessons. Move or delete its lessons first.'], 409);
        }

        $module->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Module $module = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', Rule::unique('modules')->ignore($module)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ], ['slug.regex' => 'The slug may only contain lowercase letters, digits and hyphens.']);
    }
}
