<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SlugGenerator
{
    /**
     * Builds a slug that is unique within a locale, appending a counter when
     * the desired one is taken. Rows belonging to `$ownerId` are ignored so
     * updating a record does not collide with its own slug.
     */
    public static function unique(
        string $table,
        string $foreignKey,
        string $locale,
        string $source,
        ?int $ownerId = null,
    ): string {
        $base = static::base($source);
        $slug = $base;
        $suffix = 2;

        while (static::taken($table, $foreignKey, $locale, $slug, $ownerId)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * `Str::slug()` keeps ASCII and drops everything else, so a source with no
     * ASCII in it at all reduces to an empty string. The random fallback is the
     * last resort for a title that is nothing but punctuation or emoji.
     *
     * It is deliberately NOT how Chinese pages get their address: a title in
     * Han characters would land here every time, and every Chinese page would
     * have carried an unreadable slug that changed on every save. Those locales
     * copy the address they fall back to instead — see
     * `app.mirrored_slug_locales` and `SyncsTranslations::mirroredSlug()`.
     */
    private static function base(string $source): string
    {
        $slug = Str::slug($source);

        return $slug !== '' ? $slug : Str::random(8);
    }

    private static function taken(string $table, string $foreignKey, string $locale, string $slug, ?int $ownerId): bool
    {
        return DB::table($table)
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->when($ownerId, fn ($query, int $id) => $query->where($foreignKey, '!=', $id))
            ->exists();
    }
}
