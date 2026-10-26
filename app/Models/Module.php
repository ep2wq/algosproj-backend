<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'title', 'title_ar', 'track', 'sort_order'])]
class Module extends Model
{
    use HasArabicText, HasUuids;

    public $timestamps = false;

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
