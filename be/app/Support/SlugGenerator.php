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
     *
     * `$fallbackSources` are tried in order when `$source` yields nothing —
     * see `base()` for why that is the normal case in one of the languages.
     *
     * @param  list<string>  $fallbackSources
     */
    public static function unique(
        string $table,
        string $foreignKey,
        string $locale,
        string $source,
        ?int $ownerId = null,
        array $fallbackSources = [],
    ): string {
        $base = static::base($source, $fallbackSources);
        $slug = $base;
        $suffix = 2;

        while (static::taken($table, $foreignKey, $locale, $slug, $ownerId)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * `Str::slug()` keeps ASCII and drops everything else, so a title written
     * entirely in Chinese characters reduces to an EMPTY string — 质量保证 has
     * no ASCII in it at all. The old fallback was `Str::random(8)`, which is
     * fine as a last resort for a title of pure punctuation but wrong for a
     * whole language: every Chinese page would have carried an unreadable,
     * unguessable address like `/zh/services/x7fk2p9q`, and it would have
     * changed on every save.
     *
     * So the record's own name in the other languages is asked first. The
     * English title comes before the Vietnamese one (see the caller), which
     * makes the Chinese pages read `/zh/services/quality-assurance` — the same
     * words the /en URLs use, which is also how the section paths themselves
     * are translated. An editor who wants pinyin can still type the slug by
     * hand; this only decides what happens when the field is left blank.
     *
     * @param  list<string>  $fallbackSources
     */
    private static function base(string $source, array $fallbackSources): string
    {
        foreach ([$source, ...$fallbackSources] as $candidate) {
            $slug = Str::slug($candidate);

            if ($slug !== '') {
                return $slug;
            }
        }

        return Str::random(8);
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
