<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Publishing that can be dated: published, but not before `published_at`.
 *
 * Use instead of `Publishable`, not alongside it — this composes that trait and
 * narrows both of its answers. Only models whose table actually carries a
 * `published_at` column may use it, which is the whole point of it being
 * separate.
 *
 * A record here goes live with no database write of its own, so nothing fires a
 * model event at that moment; `SitePublisher::SCHEDULED_MODELS` watches these
 * on a timer instead, and a test keeps that list and this trait in step.
 */
trait Schedulable
{
    use Publishable {
        scopePublished as scopePublishedByStatus;
        isPublished as isPublishedByStatus;
    }

    public function scopePublished(Builder $query): void
    {
        $this->scopePublishedByStatus($query);

        $query->where(fn (Builder $scheduled) => $scheduled
            ->whereNull('published_at')
            ->orWhere('published_at', '<=', now()),
        );
    }

    public function isPublished(): bool
    {
        return $this->isPublishedByStatus()
            && ($this->published_at === null || $this->published_at->isPast());
    }
}
