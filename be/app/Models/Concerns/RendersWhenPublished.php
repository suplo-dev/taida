<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Public-site behaviour for editorial models that carry their own
 * draft/published state.
 *
 * Visibility is the rule `Publishable` already applies in queries, asked of a
 * record that is already in memory — no point going back to the database to
 * learn something about the row we are holding.
 *
 * @phpstan-require-extends Model
 *
 * @phpstan-require-use Publishable
 */
trait RendersWhenPublished
{
    public function isPubliclyVisible(): bool
    {
        return $this->isPublished();
    }

    /**
     * These tables keep no audit trail — no `created_by`, no `updated_by` — so
     * every column an editor may write is a column the page renders, and the
     * fillable list is already exactly that set. Add an internal column later
     * and it must be kept out of `$fillable` anyway, which keeps this honest.
     *
     * The wording itself lives on the sibling translation rows.
     *
     * @return list<string>
     */
    public function publiclyRenderedAttributes(): array
    {
        return array_values($this->getFillable());
    }
}
