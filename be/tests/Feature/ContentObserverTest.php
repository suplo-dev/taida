<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Industry;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Post;
use App\Models\Service;
use App\Models\Tag;
use App\Models\User;
use App\Support\SitePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regenerating the site takes minutes, so "is this edit worth a build?" has to
 * be answered correctly. These pin both directions: what MUST queue a build,
 * and what must not.
 *
 * The line falls in two places — whether the record is actually on the site,
 * and whether the column that changed reaches the page.
 */
class ContentObserverTest extends TestCase
{
    use RefreshDatabase;

    // ── Must not queue a build ───────────────────────────────────────────────

    public function test_creating_a_draft_changes_nothing_on_the_public_site(): void
    {
        Service::factory()->draft()->create();

        $this->assertFalse(SitePublisher::isStale());
    }

    public function test_editing_a_draft_changes_nothing_on_the_public_site(): void
    {
        $post = Post::factory()->draft()->create();
        $this->forgetStaleFlag();

        $post->update(['is_featured' => true]);

        $this->assertFalse(SitePublisher::isStale());
    }

    public function test_editing_the_text_of_a_draft_changes_nothing(): void
    {
        $post = Post::factory()->draft()->create();
        $this->forgetStaleFlag();

        // The translation carries the wording, but it is as invisible as its
        // parent while that parent is a draft.
        $post->translations()->first()->update(['title' => 'Tiêu đề nháp']);

        $this->assertFalse(SitePublisher::isStale());
    }

    public function test_deleting_a_draft_changes_nothing_on_the_public_site(): void
    {
        $post = Post::factory()->draft()->create();
        $this->forgetStaleFlag();

        $post->delete();

        $this->assertFalse(SitePublisher::isStale());
    }

    public function test_a_scheduled_post_is_not_on_the_site_yet(): void
    {
        Post::factory()->scheduled()->create();

        $this->assertFalse(SitePublisher::isStale());
    }

    public function test_saving_without_changing_anything_does_not_queue_a_build(): void
    {
        $post = Post::factory()->create();
        $this->forgetStaleFlag();

        $post->save();

        $this->assertFalse(SitePublisher::isStale());
    }

    public function test_a_password_change_does_not_queue_a_build(): void
    {
        $author = User::factory()->create();
        Post::factory()->create(['author_id' => $author->id]);
        $this->forgetStaleFlag();

        $author->update(['password' => 'mat-khau-moi']);

        $this->assertFalse(SitePublisher::isStale());
    }

    public function test_renaming_someone_who_has_signed_nothing_live_does_not_queue_a_build(): void
    {
        $user = User::factory()->create();
        $this->forgetStaleFlag();

        $user->update(['name' => 'Tên mới']);

        $this->assertFalse(SitePublisher::isStale());
    }

    public function test_uploading_a_picture_nobody_uses_yet_does_not_queue_a_build(): void
    {
        // Editors upload images while a draft is still being written; the build
        // belongs to the moment the picture is attached, not to the upload.
        Media::factory()->create();

        $this->assertFalse(SitePublisher::isStale());
    }

    // ── Must queue a build ───────────────────────────────────────────────────

    public function test_publishing_a_draft_queues_a_build(): void
    {
        $post = Post::factory()->draft()->create();
        $this->forgetStaleFlag();

        $post->update(['status' => ContentStatus::Published, 'published_at' => now()]);

        $this->assertTrue(SitePublisher::isStale());
    }

    public function test_unpublishing_queues_a_build(): void
    {
        $post = Post::factory()->create();
        $this->forgetStaleFlag();

        // It is invisible after the save — but the HTML on the server is still
        // showing it, so the site has to be rebuilt to take it down.
        $post->update(['status' => ContentStatus::Draft]);

        $this->assertTrue(SitePublisher::isStale());
    }

    public function test_editing_the_text_of_a_live_post_queues_a_build(): void
    {
        $post = Post::factory()->create();
        $this->forgetStaleFlag();

        $post->translations()->first()->update(['title' => 'Tiêu đề đã sửa']);

        $this->assertTrue(SitePublisher::isStale());
    }

    public function test_deleting_a_live_post_queues_a_build(): void
    {
        $post = Post::factory()->create();
        $this->forgetStaleFlag();

        $post->delete();

        $this->assertTrue(SitePublisher::isStale());
    }

    public function test_renaming_an_author_with_a_live_post_queues_a_build(): void
    {
        $author = User::factory()->create();
        Post::factory()->create(['author_id' => $author->id]);
        $this->forgetStaleFlag();

        $author->update(['name' => 'Tên mới']);

        $this->assertTrue(SitePublisher::isStale());
    }

    public function test_reordering_the_menu_queues_a_build(): void
    {
        $item = MenuItem::factory()->create();
        $this->forgetStaleFlag();

        $item->update(['sort_order' => 99]);

        $this->assertTrue(SitePublisher::isStale());
    }

    public function test_changing_the_alt_text_of_a_live_cover_queues_a_build(): void
    {
        $media = Media::factory()->create();
        Post::factory()->create(['cover_media_id' => $media->id]);
        $this->forgetStaleFlag();

        $media->update(['alt' => ['vi' => 'Mô tả ảnh mới']]);

        $this->assertTrue(SitePublisher::isStale());
    }

    // ── Pivot tables ─────────────────────────────────────────────────────────
    //
    // Attaching a tag writes a pivot row and nothing else: no model event fires
    // on either side. These go through SyncsPublicRelations instead.

    public function test_tagging_a_live_post_queues_a_build(): void
    {
        $post = Post::factory()->create();
        $tag = Tag::factory()->create();
        $this->forgetStaleFlag();

        $post->syncPublicRelation('tags', [$tag->id]);

        $this->assertTrue(SitePublisher::isStale());
    }

    public function test_untagging_a_live_post_queues_a_build(): void
    {
        $post = Post::factory()->create();
        $tag = Tag::factory()->create();
        $post->syncPublicRelation('tags', [$tag->id]);
        $this->forgetStaleFlag();

        $post->syncPublicRelation('tags', []);

        $this->assertTrue(SitePublisher::isStale());
    }

    public function test_tagging_a_draft_does_not_queue_a_build(): void
    {
        $post = Post::factory()->draft()->create();
        $tag = Tag::factory()->create();
        $this->forgetStaleFlag();

        $post->syncPublicRelation('tags', [$tag->id]);

        $this->assertFalse(SitePublisher::isStale());
    }

    public function test_saving_the_same_tags_again_does_not_queue_a_build(): void
    {
        $post = Post::factory()->create();
        $tag = Tag::factory()->create();
        $post->syncPublicRelation('tags', [$tag->id]);
        $this->forgetStaleFlag();

        // Re-submitting the form without touching the tag picker attaches and
        // detaches nothing.
        $post->syncPublicRelation('tags', [$tag->id]);

        $this->assertFalse(SitePublisher::isStale());
    }

    public function test_linking_a_live_service_to_an_industry_queues_a_build(): void
    {
        $service = Service::factory()->create();
        $industry = Industry::factory()->create();
        $this->forgetStaleFlag();

        // The industry page lists its services, so the link changes that page.
        $service->syncPublicRelation('industries', [$industry->id]);

        $this->assertTrue(SitePublisher::isStale());
    }

    /**
     * Drops the flag left by the setup, so each test measures only its own act.
     */
    private function forgetStaleFlag(): void
    {
        cache()->forget('publish:stale-since');
        cache()->forget('publish:last-change');

        $this->assertFalse(SitePublisher::isStale());
    }
}
