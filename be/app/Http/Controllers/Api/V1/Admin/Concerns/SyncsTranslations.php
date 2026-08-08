<?php

namespace App\Http\Controllers\Api\V1\Admin\Concerns;

use App\Support\SlugGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait SyncsTranslations
{
    /**
     * Writes the submitted per-locale content onto a model.
     *
     * A locale submitted without its title is treated as "not translated yet":
     * the row is removed so the public site falls back to the primary locale
     * instead of rendering a half-empty page.
     *
     * @param  array<string, array<string, mixed>>  $translations
     */
    protected function syncTranslations(Model $model, array $translations, string $titleField, bool $hasSlug = true): void
    {
        $table = $model->translations()->getRelated()->getTable();
        $foreignKey = $model->translations()->getForeignKeyName();

        foreach ($translations as $locale => $attributes) {
            if (! in_array($locale, config('app.supported_locales'), true)) {
                continue;
            }

            if (blank(Arr::get($attributes, $titleField))) {
                $model->translations()->where('locale', $locale)->delete();

                continue;
            }

            if ($hasSlug) {
                $attributes['slug'] = SlugGenerator::unique(
                    $table,
                    $foreignKey,
                    $locale,
                    Arr::get($attributes, 'slug') ?: $attributes[$titleField],
                    $model->getKey(),
                );
            }

            $model->translations()->updateOrCreate(['locale' => $locale], $attributes);
        }
    }
}
