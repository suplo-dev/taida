<?php

namespace App\Observers;

use App\Support\ContentCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Drops the public read cache whenever an editor changes anything the site
 * renders, so published edits appear without waiting out the TTL.
 */
class ContentObserver
{
    public function saved(Model $model): void
    {
        ContentCache::flush();
    }

    public function deleted(Model $model): void
    {
        ContentCache::flush();
    }
}
