<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// created_at (set by the database) only orders problems; it is not sent, like before.
#[Fillable(['lesson_id', 'slug', 'title', 'title_ar', 'description', 'description_ar', 'difficulty', 'points'])]
#[Hidden(['created_at'])]
class Problem extends Model
{
    use HasArabicText, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class);
    }

    public function visibleTestCases(): HasMany
    {
        return $this->testCases()->where('is_hidden', false)->orderBy('position')->orderBy('id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(Completion::class);
    }

    // What the editor needs, shared by the lesson and challenge pages, in the request's language
    // (title_ar and description_ar must be loaded). Only visible test cases: load
    // visibleTestCases first, never testCases.
    public function editorData(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->localized('title'),
            'description' => $this->localized('description'),
            'description_lang' => $this->localizedLang('description'),
            'difficulty' => $this->difficulty,
            'points' => $this->points,
            'test_cases' => $this->visibleTestCases->map(fn ($testCase) => [
                'id' => $testCase->id,
                'input' => $testCase->input,
                'expected_output' => $testCase->expected_output,
            ]),
        ];
    }
}
