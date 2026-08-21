<?php

namespace App\Support;

use App\Models\Concerns\RendersPublicOutput;

/**
 * What has to happen when editorial content changes, in one place.
 *
 * The two halves have deliberately different thresholds. The read cache goes
 * on every write — it is one counter bump, and the CMS reads through the same
 * cache, so an editor must see their own draft at once. The static site is only
 * marked stale when the record is actually on it, because a build costs minutes
 * and a draft produces byte-identical HTML.
 *
 * ContentObserver covers writes to the record itself; pivot tables have no
 * model events, so those call sites come through here as well.
 */
class PublicContent
{
    public static function changed(RendersPublicOutput $record): void
    {
        ContentCache::flush();

        if ($record->isPubliclyVisible()) {
            SitePublisher::markStale();
        }
    }
}
