<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// position orders a problem's tests (seed order, then admin additions); it is not sent, like before.
#[Fillable(['problem_id', 'input', 'expected_output', 'is_hidden', 'position'])]
#[Hidden(['position'])]
class TestCase extends Model
{
    use HasUuids;

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
