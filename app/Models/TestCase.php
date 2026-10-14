<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['problem_id', 'input', 'expected_output', 'is_hidden'])]
class TestCase extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
        ];
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }
}
