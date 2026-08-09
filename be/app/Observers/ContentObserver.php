<?php

namespace App\Observers;

use App\Support\ContentCache;
use App\Support\SitePublisher;
use Illuminate\Database\Eloquent\Model;

/**
 * Runs whenever an editor changes anything the site renders: drops the public
 * read cache so the API answers with the new content straight away, and marks
 * the static site as out of date so it gets generated again.
 */
class ContentObserver
{
    public function saved(Model $model): void
    {
        $this->contentChanged();
    }

    public function deleted(Model $model): void
    {
        $this->contentChanged();
    }

    private function contentChanged(): void
    {
        ContentCache::flush();

        // Only a flag and a timestamp — the build is started later by
        // `site:publish`, so saving stays as fast as it was.
        SitePublisher::markStale();
    }
}
