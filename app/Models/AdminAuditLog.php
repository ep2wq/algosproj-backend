<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// Append-only: rows are only ever created, never updated or deleted by the app. Call record()
// inside the same DB transaction as the action it describes.
#[Fillable(['actor_id', 'actor_email', 'action', 'target_type', 'target_id', 'target_label', 'details', 'ip'])]
class AdminAuditLog extends Model
{
    const UPDATED_AT = null;

    private const MAX_VALUE_LENGTH = 200;

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    // $actor is the logged-in admin or owner, or null for an artisan command ("console").
    // $target is null for an action on no single row (content:sync).
    public static function record(?User $actor, string $action, ?Model $target, string $label, ?array $details = null): void
    {
        self::create([
            'actor_id' => $actor?->id,
            'actor_email' => $actor?->email ?? 'console',
            'action' => $action,
            'target_type' => $target ? Str::snake(class_basename($target)) : null,
            'target_id' => $target?->getKey(),
            'target_label' => mb_substr($label, 0, 255),
            'details' => $details,
            'ip' => $actor ? request()->ip() : null,
        ]);
    }

    // The fields an unsaved fill() changed, each {old, new} cut to 200 characters. Fields in
    // $contentOnly (lesson bodies, test input and output) are only marked "changed".
    public static function changes(Model $model, array $contentOnly = []): array
    {
        $changes = [];
        foreach (array_keys($model->getDirty()) as $field) {
            $changes[$field] = in_array($field, $contentOnly, true)
                ? 'changed'
                : ['old' => self::short($model->getOriginal($field)), 'new' => self::short($model->getAttribute($field))];
        }

        return $changes;
    }

    public static function short(mixed $value): mixed
    {
        return is_string($value) ? mb_substr($value, 0, self::MAX_VALUE_LENGTH) : $value;
    }
}
