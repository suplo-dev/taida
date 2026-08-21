<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Public visibility for a `*Translation` row, which has no state of its own:
 * the Vietnamese text of a draft service is as invisible as the draft.
 *
 * Every column a translation owns is content — that is the entire point of the
 * table — so the fillable list doubles as the rendered list. Adding a column
 * there is opting it in, which is the right default here and the opposite of
 * the parent models.
 *
 * @phpstan-require-extends Model
 */
trait RendersWithParent
{
    public function isPubliclyVisible(): bool
    {
        $parent = $this->{$this->publicParentRelation()};

        return $parent instanceof RendersPublicOutput && $parent->isPubliclyVisible();
    }

    /**
     * @return list<string>
     */
    public function publiclyRenderedAttributes(): array
    {
        return array_values($this->getFillable());
    }

    /**
     * `PostTranslation` → `post`, by the same convention HasTranslations uses
     * to find the translation class in the first place.
     */
    private function publicParentRelation(): string
    {
        return Str::camel(Str::beforeLast(class_basename(static::class), 'Translation'));
    }
}
