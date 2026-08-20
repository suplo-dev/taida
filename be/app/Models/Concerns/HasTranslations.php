<?php

namespace App\Models\Concerns;

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
 * back to the primary locale when a translation has not been written yet.
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
        return config('app.supported_locales')[0];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(static::translationModel());
    }

    /**
     * The translation for a locale, falling back to the primary locale.
     */
    public function translate(?string $locale = null): ?Model
    {
        $locale ??= app()->getLocale();

        /** @var Collection<int, Model> $translations */
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', static::primaryLocale());
    }

    /**
     * Eager-loads translations, keeping both the requested locale and the
     * primary one so the fallback resolves without an extra query.
     */
    public function scopeWithTranslation(Builder $query, ?string $locale = null): void
    {
        $locale ??= app()->getLocale();

        // Eager-load constraints receive the relation, not a query builder.
        $query->with(['translations' => fn (Relation $translations) => $translations
            ->whereIn('locale', array_unique([$locale, static::primaryLocale()])),
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
     * A record with no translation for the locale is matched by its PRIMARY
     * slug instead. That mirrors what the rest of the model already does —
     * `translate()` falls back, so a listing under /zh shows the Vietnamese
     * name and links to the Vietnamese slug — and without it those links lead
     * to a 404. That is not a cosmetic gap: the static build crawls its own
     * links with `failOnError`, so one untranslated record would fail the
     * publish for the whole site, in every language.
     *
     * The fallback is deliberately narrow. It applies only to records with
     * NOTHING in the active locale, so an exact match always wins and a slug
     * that belongs to one record in the active locale can never be shadowed by
     * another record that happens to use the same slug in the primary one.
     */
    public function scopeWhereTranslatedSlug(Builder $query, string $slug, ?string $locale = null): void
    {
        $locale ??= app()->getLocale();
        $primary = static::primaryLocale();

        $query->where(function (Builder $query) use ($slug, $locale, $primary): void {
            $query->whereHas('translations', fn (Builder $translations) => $translations
                ->where('locale', $locale)
                ->where('slug', $slug),
            );

            if ($locale === $primary) {
                return;
            }

            $query->orWhere(fn (Builder $untranslated) => $untranslated
                ->whereHas('translations', fn (Builder $translations) => $translations
                    ->where('locale', $primary)
                    ->where('slug', $slug),
                )
                ->whereDoesntHave('translations', fn (Builder $translations) => $translations
                    ->where('locale', $locale),
                ),
            );
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
