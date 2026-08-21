<?php

namespace App\Enums;

/**
 * Pages the frontend defines in code rather than in the database: the home page
 * and the listing pages.
 *
 * The values are keys, not paths. Each locale spells the path differently
 * (/dich-vu, /en/services) and only the frontend knows that map — it lives in
 * `STATIC_ROUTES` in fe/shared/content-urls.ts, and these keys must match the
 * `key` field there. The API never builds a URL; it names a destination.
 */
enum SiteRoute: string
{
    case Home = 'home';
    case Services = 'services';
    case Industries = 'industries';
    case Insights = 'insights';
    case Search = 'search';
}
