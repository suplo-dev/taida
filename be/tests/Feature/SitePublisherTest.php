<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Service;
use App\Support\SitePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The public site is static, so an edit is only visible once the site has been
 * generated again. These cover the machinery that notices an edit and asks
 * GitHub Actions to rebuild — and, just as importantly, the cases where it must
 * stay quiet.
 */
class SitePublisherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'publish.enabled' => true,
            'publish.github.repository' => 'taida/taida',
            'publish.github.token' => 'test-token',
            'publish.github.workflow' => 'deploy.yml',
            'publish.github.ref' => 'main',
            'publish.github.inputs' => ['api' => false],
            'publish.quiet_period' => 90,
            'publish.cooldown' => 300,
        ]);
    }

    public function test_saving_content_marks_the_site_out_of_date(): void
    {
        $this->assertFalse(SitePublisher::isStale());

        Service::factory()->create();

        $this->assertTrue(SitePublisher::isStale());
    }

    public function test_nothing_is_published_while_edits_are_still_arriving(): void
    {
        Http::fake();

        Service::factory()->create();

        // The editor saved a moment ago and may well save again; building now
        // would produce a site that is out of date before it finishes.
        $this->artisan('site:publish')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_it_publishes_once_the_edits_stop(): void
    {
        Http::fake(['api.github.com/*' => Http::response(status: 204)]);

        Service::factory()->create();

        $this->travel(91)->seconds();
        $this->artisan('site:publish')->assertSuccessful();

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/taida/taida/actions/workflows/deploy.yml/dispatches'
            && $request->method() === 'POST'
            && $request['ref'] === 'main');
    }

    public function test_it_asks_for_a_content_only_build(): void
    {
        Http::fake(['api.github.com/*' => Http::response(status: 204)]);

        Service::factory()->create();

        $this->artisan('site:publish', ['--force' => true])->assertSuccessful();

        // An edit changes rows, not code. Leaving the input out would let the
        // workflow fall back to its own default (`api: true`) and redeploy the
        // whole backend — vendor/, FTP upload, migrations — for a typo fix.
        Http::assertSent(fn ($request) => $request['inputs'] === ['api' => false]);
    }

    // ── Scheduled records ────────────────────────────────────────────────────
    //
    // The moment a scheduled record goes live comes with no database write, so
    // there is no model event to hang on to. `site:publish` has to ask.

    public function test_a_post_that_reaches_its_publish_time_triggers_a_build(): void
    {
        Http::fake(['api.github.com/*' => Http::response(status: 204)]);

        // A priming build, so there is a mark to compare against — as on the
        // real host.
        Service::factory()->create();
        $this->travel(91)->seconds();
        $this->artisan('site:publish')->assertSuccessful();
        Http::assertSentCount(1);

        // Dated two hours out. Saving it queues nothing: it is not on the site.
        Post::factory()->scheduled()->create(['published_at' => now()->addHours(2)]);
        $this->assertFalse(SitePublisher::isStale());

        // Its time arrives. Nobody saved anything, yet the site is now missing
        // a post.
        $this->travel(2)->hours();

        $this->artisan('site:publish')->assertSuccessful();

        Http::assertSentCount(2);
    }

    public function test_a_scheduled_post_does_not_trigger_a_build_before_its_time(): void
    {
        Http::fake(['api.github.com/*' => Http::response(status: 204)]);

        Service::factory()->create();
        $this->travel(91)->seconds();
        $this->artisan('site:publish');

        Post::factory()->scheduled()->create(['published_at' => now()->addHours(2)]);

        $this->travel(1)->hour();
        $this->artisan('site:publish')->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_a_post_that_went_live_is_built_exactly_once(): void
    {
        Http::fake(['api.github.com/*' => Http::response(status: 204)]);

        Service::factory()->create();
        $this->travel(91)->seconds();
        $this->artisan('site:publish');

        Post::factory()->scheduled()->create(['published_at' => now()->addHours(2)]);
        $this->travel(2)->hours();
        $this->artisan('site:publish');

        // The schedule mark has to move with that build. If it does not, the
        // same post queues another one every cooldown, forever.
        $this->travel(1)->hour();
        $this->artisan('site:publish')->assertSuccessful();

        Http::assertSentCount(2);
    }

    public function test_the_first_ever_run_only_sets_the_baseline(): void
    {
        Http::fake(['api.github.com/*' => Http::response(status: 204)]);

        // A back catalogue exists and `site:publish` has never run. There is no
        // telling when the live HTML was built, so none of it counts as having
        // just gone live.
        Service::factory()->create(['published_at' => now()->subMonth()]);
        cache()->forget('publish:stale-since');
        cache()->forget('publish:last-change');

        $this->artisan('site:publish')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_burst_of_edits_produces_a_single_build(): void
    {
        Http::fake(['api.github.com/*' => Http::response(status: 204)]);

        // Three saves a minute apart: the quiet period restarts each time, so
        // only the last one is followed by a build.
        Service::factory()->create();
        $this->travel(60)->seconds();
        Service::factory()->create();
        $this->travel(60)->seconds();
        Service::factory()->create();

        $this->artisan('site:publish')->assertSuccessful();
        Http::assertNothingSent();

        $this->travel(91)->seconds();
        $this->artisan('site:publish')->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_the_site_stops_being_stale_once_published(): void
    {
        Http::fake(['api.github.com/*' => Http::response(status: 204)]);

        Service::factory()->create();
        $this->travel(91)->seconds();
        $this->artisan('site:publish');

        $this->assertFalse(SitePublisher::isStale());
        $this->assertNotNull(SitePublisher::lastPublishedAt());

        // A second run with nothing new must not queue another build.
        $this->travel(400)->seconds();
        $this->artisan('site:publish')->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_a_failed_call_leaves_the_site_stale_so_the_next_run_retries(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401)]);

        Service::factory()->create();
        $this->travel(91)->seconds();

        $this->artisan('site:publish')->assertFailed();

        // The edit is still unpublished; forgetting it would strand the change
        // on the site until somebody happened to edit something else.
        $this->assertTrue(SitePublisher::isStale());
    }

    public function test_a_second_build_is_held_back_during_the_cooldown(): void
    {
        Http::fake(['api.github.com/*' => Http::response(status: 204)]);

        Service::factory()->create();
        $this->travel(91)->seconds();
        $this->artisan('site:publish');

        Service::factory()->create();
        $this->travel(91)->seconds();
        $this->artisan('site:publish')->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_force_ignores_both_the_quiet_period_and_the_cooldown(): void
    {
        Http::fake(['api.github.com/*' => Http::response(status: 204)]);

        Service::factory()->create();

        $this->artisan('site:publish', ['--force' => true])->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_nothing_happens_when_publishing_is_switched_off(): void
    {
        Http::fake();
        config(['publish.enabled' => false]);

        Service::factory()->create();
        $this->travel(91)->seconds();

        $this->artisan('site:publish')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_missing_token_is_reported_rather_than_silently_ignored(): void
    {
        Http::fake();
        config(['publish.github.token' => null]);

        Service::factory()->create();
        $this->travel(91)->seconds();

        $this->artisan('site:publish')->assertFailed();

        Http::assertNothingSent();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
