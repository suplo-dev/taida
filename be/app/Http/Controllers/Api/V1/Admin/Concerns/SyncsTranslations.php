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
                    $this->slugFallbacks($model, $translations, $titleField, $locale),
                );
            }

            $model->translations()->updateOrCreate(['locale' => $locale], $attributes);
        }
    }

    /**
     * This record's title in the other locales, for when the slug cannot be
     * built from the title being saved — which is every Chinese title, since
     * `Str::slug()` strips Han characters and leaves nothing (see
     * SlugGenerator::base()).
     *
     * Order matters. The non-primary locales come first so a Chinese page
     * borrows its address from the ENGLISH name rather than the Vietnamese
     * one: `/zh/services/quality-assurance` reads to the same audience the
     * page is written for, and matches how the section paths are translated.
     * The primary locale is last because it is the one guaranteed to exist.
     *
     * Both the payload and what is already stored are consulted: an editor
     * saving from the CMS submits every locale at once, but a partial payload
     * must not silently lose the fallback.
     *
     * @param  array<string, array<string, mixed>>  $translations
     * @return list<string>
     */
    private function slugFallbacks(Model $model, array $translations, string $titleField, string $locale): array
    {
        /** @var list<string> $supported */
        $supported = config('app.supported_locales');
        $primary = $supported[0];

        $others = array_values(array_filter(
            $supported,
            fn (string $candidate): bool => $candidate !== $locale && $candidate !== $primary,
        ));

        if ($primary !== $locale) {
            $others[] = $primary;
        }

        $sources = [];

        foreach ($others as $other) {
            $sources[] = Arr::get($translations, "{$other}.{$titleField}");
            $sources[] = $model->translations()->where('locale', $other)->value($titleField);
        }

        return array_values(array_filter(
            array_map(fn ($value): string => is_string($value) ? $value : '', $sources),
            fn (string $value): bool => $value !== '',
        ));
    }
}
