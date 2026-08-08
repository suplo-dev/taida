<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for editorial resources, which are always submitted with
 * every locale in a single payload:
 *
 *   { ..., "translations": { "vi": {...}, "en": {...} } }
 *
 * The primary locale is mandatory; the others may be left blank and will fall
 * back to it when the public site renders.
 */
abstract class TranslatableRequest extends FormRequest
{
    /** Table holding the translations, e.g. `service_translations`. */
    abstract protected function translationTable(): string;

    /** Foreign key on that table, e.g. `service_id`. */
    abstract protected function translationForeignKey(): string;

    /** The field an editor must fill in, e.g. `name` or `title`. */
    abstract protected function titleField(): string;

    /**
     * Optional translated fields beyond the title and the slug.
     *
     * @return array<string, list<string>>
     */
    abstract protected function translationFields(): array;

    /** Whether the resource has a URL slug at all. */
    protected function hasSlug(): bool
    {
        return true;
    }

    /** The record being updated, if any. */
    abstract public function currentId(): ?int;

    /**
     * @return array<string, mixed>
     */
    protected function translationRules(): array
    {
        $rules = ['translations' => ['required', 'array']];
        $primary = config('app.supported_locales')[0];

        foreach (config('app.supported_locales') as $locale) {
            $isPrimary = $locale === $primary;

            $rules["translations.{$locale}"] = [$isPrimary ? 'required' : 'nullable', 'array'];
            $rules["translations.{$locale}.".$this->titleField()] = [
                $isPrimary ? 'required' : 'nullable', 'string', 'max:255',
            ];

            if ($this->hasSlug()) {
                $rules["translations.{$locale}.slug"] = [
                    'nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/',
                    Rule::unique($this->translationTable(), 'slug')
                        ->where('locale', $locale)
                        ->ignore($this->currentId(), $this->translationForeignKey()),
                ];
            }

            foreach ($this->translationFields() as $field => $fieldRules) {
                $rules["translations.{$locale}.{$field}"] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function translations(): array
    {
        return array_filter((array) $this->input('translations', []), 'is_array');
    }
}
