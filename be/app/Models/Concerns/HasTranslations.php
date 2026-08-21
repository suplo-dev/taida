<?php

namespace App\Models\Concerns;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Adds per-locale content stored in a sibling `*_translations` table.
 *
 * The consuming model declares which columns live on the translation with a
 * `$translatable` array; those names then read straight off the model, so
 * `$service->name` returns the value for the active locale and silently falls
 * back down the locale's chain (see `App\Support\Locales`) when a translation
 * has not been written yet.
 */
trait HasTranslations
{
    /**
     * Translation model class, resolved from the parent by convention:
     * `App\Models\Service` → `App\Models\ServiceTranslation`.
     */
    public static function translationModel(): string
    {
        return static::class.'Translation';
    }

    /** The locale content must always be authored in. */
    public static function primaryLocale(): string
    {
        return Locales::primary();
    }

    public function translations(): HasMany
    {
        return $this->hasMany(static::translationModel());
    }

    /**
     * The translation for a locale, falling back down its chain.
     */
    public function translate(?string $locale = null): ?Model
    {
        /** @var Collection<int, Model> $translations */
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        foreach (Locales::chain($locale) as $candidate) {
            $translation = $translations->firstWhere('locale', $candidate);

            if ($translation !== null) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * Eager-loads translations, keeping every locale the requested one can
     * borrow from so the fallback resolves without an extra query.
     */
    public function scopeWithTranslation(Builder $query, ?string $locale = null): void
    {
        $chain = Locales::chain($locale);

        // Eager-load constraints receive the relation, not a query builder.
        $query->with(['translations' => fn (Relation $translations) => $translations
            ->whereIn('locale', $chain),
        ]);
    }

    /**
     * Eager-loads every locale, not just the one being rendered. Detail
     * endpoints need this: a page has to know its own address in the other
     * language, and the slugs differ per locale.
     */
    public function scopeWithAllTranslations(Builder $query): void
    {
        $query->with('translations');
    }

    /**
     * This record's slug in each locale it has been translated into.
     *
     * The language switcher builds the other locale's URL from this. Without
     * it the current slug gets reused across the switch, which lands on a 404
     * — the two languages never share a slug.
     *
     * @return array<string, string>
     */
    public function translatedSlugs(): array
    {
        /** @var Collection<int, Model> $translations */
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->pluck('slug', 'locale')->all();
    }

    /**
     * Constrains to the record whose slug matches within a locale. Slugs are
     * unique per locale, so this identifies at most one record.
     *
     * A record with no translation for the locale is matched by the slug of
     * the nearest locale it borrows from — /zh/about-us reaches a page written
     * only in Vietnamese and English, because `translate()` will serve that
     * reader the English row and this must answer at the English row's
     * address. The two walk the same chain; if they disagreed, a URL the
     * language switcher emits would render nothing, and the static build
     * crawls its own links with `failOnError`, so one such record fails the
     * publish for the whole site in every language.
     *
     * Each fallback step is deliberately narrow: it applies only to records
     * with NOTHING in any nearer locale of the chain, so no single record is
     * ever reachable at two different addresses in the same locale.
     *
     * What it does not settle is a collision BETWEEN records — one record
     * translated into Chinese under the slug another record already carries in
     * English. Both branches match and `first()` decides on row order. That
     * needs a uniqueness check spanning the chain at write time, which the
     * admin validator does not do today; the per-locale unique index cannot
     * see it.
     */
    public function scopeWhereTranslatedSlug(Builder $query, string $slug, ?string $locale = null): void
    {
        $chain = Locales::chain($locale);

        $query->where(function (Builder $query) use ($slug, $chain): void {
            foreach ($chain as $index => $candidate) {
                // Locales nearer than this one in the chain would have been
                // used instead, so a row in any of them rules this step out.
                $nearer = array_slice($chain, 0, $index);

                $query->orWhere(function (Builder $match) use ($slug, $candidate, $nearer): void {
                    $match->whereHas('translations', fn (Builder $translations) => $translations
                        ->where('locale', $candidate)
                        ->where('slug', $slug),
                    );

                    foreach ($nearer as $locale) {
                        $match->whereDoesntHave('translations', fn (Builder $translations) => $translations
                            ->where('locale', $locale),
                        );
                    }
                });
            }
        });
    }

    /**
     * Reads translated columns as if they were the model's own.
     */
    public function getAttribute($key)
    {
        if (in_array($key, $this->translatable ?? [], true) && ! $this->hasAttribute($key)) {
            return $this->translate()?->getAttribute($key);
        }

        return parent::getAttribute($key);
    }
}
