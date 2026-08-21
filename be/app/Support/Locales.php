<?php

namespace App\Support;

/**
 * The one place that answers "which locale does this one borrow from?".
 *
 * Five things used to each carry their own copy of "…otherwise the primary
 * locale": the translation reader, the slug lookup, media alt text, search and
 * settings. They have to agree — the slug lookup decides which URLs exist and
 * the translation reader decides what those URLs show, so a disagreement is a
 * page that renders at an address the router will not resolve, or a link the
 * static build follows into a 404.
 */
class Locales
{
    /** @return list<string> */
    public static function supported(): array
    {
        return config('app.supported_locales');
    }

    /** The locale content must always be authored in. */
    public static function primary(): string
    {
        return static::supported()[0];
    }

    /**
     * A locale followed by the ones it borrows from, nearest first.
     *
     * Always starts with `$locale` itself and always ends at the primary
     * locale, so callers can walk it without a special case for either end.
     *
     * @return list<string>
     */
    public static function chain(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $primary = static::primary();

        $chain = [$locale, ...config("app.locale_fallbacks.{$locale}", []), $primary];

        return array_values(array_unique(array_filter(
            $chain,
            fn (string $candidate): bool => in_array($candidate, static::supported(), true),
        )));
    }

    /**
     * The locales `$locale` borrows from, nearest first — the chain without
     * the locale itself.
     *
     * @return list<string>
     */
    public static function fallbacks(?string $locale = null): array
    {
        return array_values(array_slice(static::chain($locale), 1));
    }

    /**
     * Whether this locale takes its slug from another one instead of having its
     * own — see `app.mirrored_slug_locales` for why Chinese does.
     */
    public static function mirrorsSlug(?string $locale = null): bool
    {
        $locale ??= app()->getLocale();

        return in_array($locale, config('app.mirrored_slug_locales', []), true);
    }
}
