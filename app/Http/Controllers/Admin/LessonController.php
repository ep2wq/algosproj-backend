<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $data = $this->validated($request);
        $lesson = DB::transaction(function () use ($request, $data) {
            $lesson = Lesson::create($data);
            AdminAuditLog::record($request->user(), 'lesson.create', $lesson, $lesson->title);

            return $lesson;
        });

        return response()->json($lesson, 201);
    }

    public function update(Request $request, Lesson $lesson)
    {
        $lesson->fill($this->validated($request, $lesson));
        // The body itself is not logged, only that it changed.
        $changes = AdminAuditLog::changes($lesson, ['body']);
        DB::transaction(function () use ($request, $lesson, $changes) {
            $lesson->save();
            AdminAuditLog::record($request->user(), 'lesson.update', $lesson, $lesson->title, ['changes' => $changes]);
        });

        return response()->json($lesson);
    }

    public function destroy(Request $request, Lesson $lesson)
    {
        if ($lesson->problems()->exists()) {
            return response()->json(['message' => 'This lesson has problems. Move or delete its problems first.'], 409);
        }

        DB::transaction(function () use ($request, $lesson) {
            $lesson->delete();
            AdminAuditLog::record($request->user(), 'lesson.delete', $lesson, $lesson->title);
        });

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
