<?php

namespace App\Http\Controllers\Api\V1\Admin\Concerns;

use App\Support\Locales;
use App\Support\SlugGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait SyncsTranslations
{
    /**
     * Writes the submitted per-locale content onto a model.
     *
     * A locale submitted without its title is treated as "not translated yet":
     * the row is removed so the public site falls back down the locale chain
     * instead of rendering a half-empty page.
     *
     * Locales are written in the order they are configured, not the order they
     * arrive in the payload, because a mirrored slug reads the row it copies —
     * writing Chinese before English would copy the previous save's address.
     *
     * @param  array<string, array<string, mixed>>  $translations
     */
    protected function syncTranslations(Model $model, array $translations, string $titleField, bool $hasSlug = true): void
    {
        $table = $model->translations()->getRelated()->getTable();
        $foreignKey = $model->translations()->getForeignKeyName();

        foreach (Locales::supported() as $locale) {
            if (! array_key_exists($locale, $translations)) {
                continue;
            }

            $attributes = $translations[$locale];

            if (blank(Arr::get($attributes, $titleField))) {
                $model->translations()->where('locale', $locale)->delete();

                continue;
            }

            if ($hasSlug) {
                $attributes['slug'] = $this->slugFor($model, $table, $foreignKey, $locale, $attributes, $titleField);
            }

            $model->translations()->updateOrCreate(['locale' => $locale], $attributes);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function slugFor(
        Model $model,
        string $table,
        string $foreignKey,
        string $locale,
        array $attributes,
        string $titleField,
    ): string {
        /*
         * A mirrored locale has no address of its own: it answers at the one it
         * borrows from, and any slug the client sent is ignored rather than
         * validated away, because the admin does not offer the field at all.
         * Still routed through `unique()` — mirroring cannot normally collide,
         * since the slugs being mirrored are themselves unique, but a record
         * with no English at all borrows the Vietnamese slug, and that can meet
         * another record's English one. Rare, and a suffix beats a 500.
         */
        $source = Locales::mirrorsSlug($locale)
            ? $this->mirroredSlug($model, $locale)
            : (Arr::get($attributes, 'slug') ?: $attributes[$titleField]);

        return SlugGenerator::unique($table, $foreignKey, $locale, $source, $model->getKey());
    }

    /**
     * The slug this locale copies: the nearest locale in its fallback chain
     * that has one.
     *
     * Read back from the database rather than from the payload so a partial
     * update — one locale submitted on its own — copies what is actually
     * stored. The loop above has already written the locales that come first.
     */
    private function mirroredSlug(Model $model, string $locale): string
    {
        foreach (Locales::fallbacks($locale) as $source) {
            $slug = $model->translations()->where('locale', $source)->value('slug');

            if (is_string($slug) && $slug !== '') {
                return $slug;
            }
        }

        return '';
    }
}
