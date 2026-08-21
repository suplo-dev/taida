<?php

namespace App\Models\Concerns;

use App\Support\PublicContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Attaching a tag to a post writes a pivot row and nothing else: no model event
 * fires on either side, so an observer never hears about it and the site keeps
 * serving the old tag list.
 *
 * Going through here instead of calling `sync()` directly keeps the reporting
 * attached to the write, so a call site added later cannot quietly skip it.
 *
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements RendersPublicOutput
 */
trait SyncsPublicRelations
{
    /**
     * @param  list<int|string>  $ids
     */
    public function syncPublicRelation(string $relation, array $ids): void
    {
        /** @var BelongsToMany<covariant \Illuminate\Database\Eloquent\Model, $this> $related */
        $related = $this->{$relation}();

        $changes = $related->sync($ids);

        // `sync()` reports what it attached, detached and updated. Re-saving a
        // form with the same tags touches nothing, and must not cost a build.
        if (array_filter($changes) !== []) {
            PublicContent::changed($this);
        }
    }
}
