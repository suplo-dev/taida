<?php

namespace App\Observers;

use App\Models\Concerns\RendersPublicOutput;
use App\Support\ContentCache;
use App\Support\PublicContent;
use App\Support\SitePublisher;
use Illuminate\Database\Eloquent\Model;

/**
 * Runs whenever an editor changes anything the site renders.
 *
 * Two jobs with deliberately different thresholds:
 *
 *  - the read cache is dropped on EVERY write. It is one counter bump, and the
 *    CMS itself reads through the same cache, so an editor must see their own
 *    draft immediately.
 *  - the static site is only marked stale when the change actually alters the
 *    rendered HTML. A build costs minutes, and marking on every save means a
 *    half-written draft, a password change, or pressing Save without editing
 *    anything all buy a rebuild that produces byte-identical output.
 *
 * The second decision asks two questions, in this order:
 *
 *   1. Did a column that reaches the page change? If not, stop — no query yet.
 *   2. Is the record on the site now, or was it a moment ago? A draft edited
 *      into another draft is invisible either way.
 *
 * Publishing and unpublishing pass both: `status` (or `published_at`) changed,
 * and one of "is visible"/"was visible" holds.
 *
 * Nothing here calls GitHub. A network round-trip inside the save lifecycle
 * would add api.github.com's latency to every Save button, and an outage there
 * would surface as a 500 while writing a post. `site:publish` does the calling.
 *
 * Pivot tables fire no model events at all, so those writes report themselves
 * through SyncsPublicRelations instead — the same policy, reached differently.
 */
class ContentObserver
{
    public function created(Model&RendersPublicOutput $model): void
    {
        // A record created as a draft has nothing on the site to update.
        PublicContent::changed($model);
    }

    /**
     * `updated`, not `saved`: Eloquent skips the update entirely when a model
     * is not dirty, so re-saving an untouched form never reaches this.
     */
    public function updated(Model&RendersPublicOutput $model): void
    {
        ContentCache::flush();

        if (! $this->publicColumnsChanged($model)) {
            return;
        }

        if ($model->isPubliclyVisible() || $this->wasPubliclyVisible($model)) {
            SitePublisher::markStale();
        }
    }

    public function deleted(Model&RendersPublicOutput $model): void
    {
        // Deleting a draft changes nothing out there; deleting a live page does.
        PublicContent::changed($model);
    }

    /**
     * The overlap between "columns just written" and "columns the page shows".
     *
     * `getChanges()` is what actually reached the database in the save that
     * just happened, so a no-op write leaves it empty.
     */
    private function publicColumnsChanged(Model&RendersPublicOutput $model): bool
    {
        return array_intersect(
            array_keys($model->getChanges()),
            $model->publiclyRenderedAttributes(),
        ) !== [];
    }

    /**
     * Visibility as it stood BEFORE the save that just happened.
     *
     * Rebuilt from `getOriginal()`, which still holds the pre-save values: the
     * `updated` event fires before Eloquent calls `syncOriginal()`. Needed to
     * catch unpublishing — the record is invisible now, but the HTML on the
     * server is still showing it.
     */
    private function wasPubliclyVisible(Model&RendersPublicOutput $model): bool
    {
        /** @var Model&RendersPublicOutput $before */
        $before = $model->newFromBuilder($model->getOriginal());

        return $before->isPubliclyVisible();
    }
}
