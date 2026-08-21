<?php

namespace App\Models\Concerns;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Draft/published lifecycle shared by every editorial model.
 *
 * Status only. Models that can also be dated into the future use `Schedulable`,
 * which builds on this — the split is not decoration: `pages` has no
 * `published_at` column, so a scope that mentioned one threw
 * "Unknown column 'published_at'" the first time anything called
 * `Page::published()`. It stayed hidden because `Page` overrode `isPublished()`
 * but not the scope, and because the one query that needed it was written by
 * hand as `where('status', ...)` instead. Keeping the date clause out of here
 * means a model without the column cannot be asked about one.
 */
trait Publishable
{
    /** Only records the public site is allowed to show. */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published);
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }
}
