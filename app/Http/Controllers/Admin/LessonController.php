<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    public function index(Module $module)
    {
        return response()->json($module->lessons()
            ->select('id', 'module_id', 'slug', 'title', 'sort_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get());
    }

    public function show(Lesson $lesson)
    {
        return response()->json($lesson);
    }

    public function store(Request $request)
    {
        return response()->json(Lesson::create($this->validated($request)), 201);
    }

    public function update(Request $request, Lesson $lesson)
    {
        $lesson->update($this->validated($request, $lesson));

        return response()->json($lesson);
    }

    public function destroy(Lesson $lesson)
    {
        if ($lesson->problems()->exists()) {
            return response()->json(['message' => 'This lesson has problems. Move or delete its problems first.'], 409);
        }

        $lesson->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Lesson $lesson = null): array
    {
        return $request->validate([
            'module_id' => ['required', 'integer', 'exists:modules,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', Rule::unique('lessons')->ignore($lesson)],
            // body is a TEXT column (65,535 bytes), so long non-ASCII text is checked in bytes too.
            'body' => ['nullable', 'string', 'max:20000', function ($attribute, $value, $fail) {
                if (is_string($value) && strlen($value) > 65535) {
                    $fail('The body may not be larger than 65,535 bytes.');
                }
            }],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ], ['slug.regex' => 'The slug may only contain lowercase letters, digits and hyphens.']);
    }
}
