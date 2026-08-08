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
        $base = Str::slug($source) ?: Str::random(8);
        $slug = $base;
        $suffix = 2;

        while (static::taken($table, $foreignKey, $locale, $slug, $ownerId)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
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
