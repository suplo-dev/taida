<?php

namespace App\Enums;

use App\Models\Industry;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;

/**
 * What a menu item points at.
 *
 * Menu items used to store a typed-in URL per locale, which made every link a
 * chance to get the locale prefix or the slug wrong — and a wrong one is not a
 * broken link on one page, it is a failed publish for the whole site, because
 * the static build crawls what the menu renders. Storing the destination and
 * building the URL at render time removes the chance entirely: the slug is read
 * from the record, and the prefix from the locale being rendered.
 */
enum MenuTarget: string
{
    /** A page that exists in code, not in the database — see `SiteRoute`. */
    case Route = 'route';

    case Page = 'page';
    case Service = 'service';
    case Industry = 'industry';
    case Post = 'post';

    /** Somewhere off this site. The only kind that still stores a URL. */
    case External = 'external';

    /**
     * The model behind this target, or null for the kinds that have no record.
     *
     * @return class-string|null
     */
    public function model(): ?string
    {
        return match ($this) {
            self::Page => Page::class,
            self::Service => Service::class,
            self::Industry => Industry::class,
            self::Post => Post::class,
            self::Route, self::External => null,
        };
    }

    /** Targets that point at a database record, i.e. the ones needing an id. */
    public function isContent(): bool
    {
        return $this->model() !== null;
    }
}
