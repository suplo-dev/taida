<?php

namespace App\Models\Concerns;

/**
 * A model whose content ends up in the static HTML of the public site.
 *
 * ContentObserver asks these two questions before deciding a save is worth a
 * rebuild. The site is prerendered, so a build costs minutes; saving a draft,
 * changing a password, or pressing Save without editing anything leaves every
 * byte of that HTML identical.
 */
interface RendersPublicOutput
{
    /**
     * Whether this record is on the public site as things stand right now.
     *
     * Drafts and posts scheduled for a future date both answer false: editing
     * them cannot change the HTML a visitor is looking at.
     */
    public function isPubliclyVisible(): bool;

    /**
     * The columns whose values reach the rendered page.
     *
     * Listed explicitly rather than by exclusion, so a column added later opts
     * out by default. Missing one only makes the site slow to catch up; the
     * opposite mistake makes every audit column cost a build.
     *
     * @return list<string>
     */
    public function publiclyRenderedAttributes(): array;
}
