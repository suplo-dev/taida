<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the static site in step with the database.
 *
 * The public site is prerendered, so an edit saved in the CMS is invisible
 * until the site is generated again. Every content write marks the site stale;
 * a scheduled command notices and asks GitHub Actions to rebuild.
 *
 * State lives in the cache rather than a table. It is deliberately disposable:
 * the worst a lost flag can do is skip one rebuild, and the only thing that
 * clears the cache is a deploy — which rebuilds the site anyway.
 */
class SitePublisher
{
    private const STALE_KEY = 'publish:stale-since';

    private const CHANGED_KEY = 'publish:last-change';

    private const PUBLISHED_KEY = 'publish:last-run';

    /**
     * Records that something the site renders has changed.
     *
     * Two timestamps, not one: the first tells us how long the site has been
     * out of date (and survives further edits), the second restarts the quiet
     * period on every save so a burst of edits collapses into one build.
     */
    public static function markStale(): void
    {
        $now = Carbon::now();

        Cache::forever(self::CHANGED_KEY, $now->timestamp);

        if (! Cache::has(self::STALE_KEY)) {
            Cache::forever(self::STALE_KEY, $now->timestamp);
        }
    }

    public static function isStale(): bool
    {
        return Cache::has(self::STALE_KEY);
    }

    public static function staleSince(): ?Carbon
    {
        return self::timestamp(self::STALE_KEY);
    }

    public static function lastPublishedAt(): ?Carbon
    {
        return self::timestamp(self::PUBLISHED_KEY);
    }

    /**
     * Whether a build should start right now.
     *
     * Held back while edits are still arriving, and while a build started
     * recently enough that it has probably not finished yet.
     */
    public static function isDue(): bool
    {
        if (! config('publish.enabled') || ! self::isStale()) {
            return false;
        }

        $lastChange = self::timestamp(self::CHANGED_KEY);

        if ($lastChange !== null && $lastChange->diffInSeconds(Carbon::now()) < config('publish.quiet_period')) {
            return false;
        }

        $lastRun = self::lastPublishedAt();

        return $lastRun === null
            || $lastRun->diffInSeconds(Carbon::now()) >= config('publish.cooldown');
    }

    /**
     * Asks GitHub Actions to run the publish workflow.
     *
     * Returns false and logs on failure rather than throwing: this runs from a
     * scheduled command and from model events, and a publishing problem must
     * never take down an editor's save.
     */
    public static function trigger(): bool
    {
        $repository = config('publish.github.repository');
        $token = config('publish.github.token');
        $workflow = config('publish.github.workflow');

        if (blank($repository) || blank($token)) {
            Log::warning('Bỏ qua xuất bản: chưa cấu hình PUBLISH_GITHUB_REPOSITORY hoặc PUBLISH_GITHUB_TOKEN.');

            return false;
        }

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(15)
            ->post(
                "https://api.github.com/repos/{$repository}/actions/workflows/{$workflow}/dispatches",
                ['ref' => config('publish.github.ref')],
            );

        if ($response->failed()) {
            Log::error('Không kích hoạt được workflow xuất bản.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        // Cleared only once GitHub has accepted the request; a failed call
        // leaves the site marked stale so the next tick tries again.
        Cache::forget(self::STALE_KEY);
        Cache::forever(self::PUBLISHED_KEY, Carbon::now()->timestamp);

        return true;
    }

    private static function timestamp(string $key): ?Carbon
    {
        $value = Cache::get($key);

        return $value === null ? null : Carbon::createFromTimestamp($value);
    }
}
