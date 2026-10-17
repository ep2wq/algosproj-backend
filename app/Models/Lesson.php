<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['module_id', 'slug', 'title', 'title_ar', 'body', 'body_ar', 'sort_order'])]
class Lesson extends Model
{
    use HasArabicText;

    public $timestamps = false;

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function problems(): HasMany
    {
        return $this->hasMany(Problem::class);
    }
}
