<?php

namespace App\Support;

use App\Models\Industry;
use App\Models\Post;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
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

    private const SCHEDULE_CURSOR_KEY = 'publish:schedule-cursor';

    /**
     * Models that can be scheduled: published, but dated in the future.
     *
     * They need watching separately because the moment such a record goes live
     * comes with no database write at all — there is no model event to hang on
     * to. Page is absent on purpose: it publishes by status alone, with no date
     * column to schedule with.
     *
     * @var list<class-string<Model>>
     */
    private const SCHEDULED_MODELS = [
        Service::class,
        Industry::class,
        Post::class,
    ];

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
     * Two independent reasons, either one enough:
     *  - an editor changed something and has stopped typing for long enough;
     *  - a scheduled record has just reached its publish time.
     *
     * The second does NOT wait out the quiet period: there is nobody mid-edit
     * to wait for. Both still pass the cooldown, which guards GitHub's quota
     * rather than the editor.
     */
    public static function isDue(): bool
    {
        if (! config('publish.enabled')) {
            return false;
        }

        if (! self::editsHaveSettled() && ! self::scheduledContentWentLive()) {
            return false;
        }

        return self::cooldownElapsed();
    }

    /**
     * Whether anything reached the site since the last build without a save.
     *
     * This is the hole model events cannot cover: schedule a post for 8am and
     * at 8am no statement runs, no observer fires, and the post sits there
     * until somebody happens to edit something else. So ask the database.
     *
     * Self-clearing: `trigger()` moves the cursor forward, so the next pass
     * finds the record already behind it.
     */
    public static function scheduledContentWentLive(): bool
    {
        $since = self::scheduleCursor();

        if ($since === null) {
            return false;
        }

        foreach (self::SCHEDULED_MODELS as $model) {
            $wentLive = $model::query()
                ->published()
                ->where('published_at', '>', $since)
                ->exists();

            if ($wentLive) {
                return true;
            }
        }

        return false;
    }

    /**
     * Something changed, and the editor has stopped typing for long enough.
     */
    private static function editsHaveSettled(): bool
    {
        if (! self::isStale()) {
            return false;
        }

        $lastChange = self::timestamp(self::CHANGED_KEY);

        return $lastChange === null
            || $lastChange->diffInSeconds(Carbon::now()) >= config('publish.quiet_period');
    }

    /**
     * The previous build is far enough back to have probably finished.
     */
    private static function cooldownElapsed(): bool
    {
        $lastRun = self::lastPublishedAt();

        return $lastRun === null
            || $lastRun->diffInSeconds(Carbon::now()) >= config('publish.cooldown');
    }

    /**
     * The mark meaning "everything published before this is in the HTML that
     * is currently being served".
     *
     * The very first run only SETS the mark and returns null: at that point
     * there is no telling when the live HTML was generated, and guessing either
     * misses records or rebuilds for the entire back catalogue. From then on
     * the mark is simply the last build.
     */
    private static function scheduleCursor(): ?Carbon
    {
        $cursor = self::timestamp(self::SCHEDULE_CURSOR_KEY);

        if ($cursor === null) {
            Cache::forever(self::SCHEDULE_CURSOR_KEY, Carbon::now()->timestamp);
        }

        return $cursor;
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
                [
                    'ref' => config('publish.github.ref'),
                    // Content-only rebuild: see the note on `publish.github.inputs`.
                    'inputs' => config('publish.github.inputs', []),
                ],
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
        $now = Carbon::now();

        Cache::forget(self::STALE_KEY);
        Cache::forever(self::PUBLISHED_KEY, $now->timestamp);

        // Move the schedule mark along with it: the build about to run covers
        // everything due as of now. Without this, `scheduledContentWentLive()`
        // keeps answering true and queues another build every cooldown, forever.
        Cache::forever(self::SCHEDULE_CURSOR_KEY, $now->timestamp);

        return true;
    }

    private static function timestamp(string $key): ?Carbon
    {
        $value = Cache::get($key);

        return $value === null ? null : Carbon::createFromTimestamp($value);
    }
}
