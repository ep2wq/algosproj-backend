<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// For models with Arabic columns (title_ar, body_ar, description_ar) next to the English ones.
// The language of a request is the app locale, set by the content-lang middleware.
trait HasArabicText
{
    // An empty (or blank) Arabic value is stored as NULL, so "not translated" has one form.
    protected static function bootHasArabicText(): void
    {
        static::saving(function (Model $model) {
            foreach ($model->getAttributes() as $field => $value) {
                if (str_ends_with($field, '_ar') && is_string($value) && trim($value) === '') {
                    $model->setAttribute($field, null);
                }
            }
        });
    }

    // $field in the request's language: the Arabic column when Arabic was asked for and it is
    // filled, otherwise the English column.
    public function localized(string $field): ?string
    {
        return $this->localizedLang($field) === 'ar' ? $this->getAttribute($field.'_ar') : $this->getAttribute($field);
    }

    // The language localized() returns the text in: "ar" or "en".
    public function localizedLang(string $field): string
    {
        return app()->isLocale('ar') && filled($this->getAttribute($field.'_ar')) ? 'ar' : 'en';
    }
}
