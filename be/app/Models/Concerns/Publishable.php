<?php

namespace App\Models\Concerns;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Draft/published lifecycle shared by every editorial model.
 */
trait Publishable
{
    /**
     * Only records the public site is allowed to show: marked published and
     * not scheduled for a future date.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published)
            ->where(fn (Builder $scheduled) => $scheduled
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()),
            );
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published
            && ($this->published_at === null || $this->published_at->isPast());
    }
}
