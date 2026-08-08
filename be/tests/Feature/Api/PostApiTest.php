<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_paginates_newest_first(): void
    {
        Post::factory()->count(5)->sequence(
            ['published_at' => now()->subDays(5)],
            ['published_at' => now()->subDays(4)],
            ['published_at' => now()->subDays(3)],
            ['published_at' => now()->subDays(2)],
            ['published_at' => now()->subDay()],
        )->create();

        $response = $this->getJson('/api/v1/posts?per_page=2&locale=vi')->assertOk();

        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.total', 5);
        $response->assertJsonPath('meta.last_page', 3);

        $newest = Post::query()->latest('published_at')->first();
        $response->assertJsonPath('data.0.id', $newest->id);
    }

    public function test_index_hides_drafts_and_scheduled_posts(): void
    {
        Post::factory()->create();
        Post::factory()->draft()->create();
        Post::factory()->scheduled()->create();

        $this->getJson('/api/v1/posts?locale=vi')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_filters_by_category_slug_in_the_active_locale(): void
    {
        $category = Category::factory()->create();
        Post::factory()->count(2)->create(['category_id' => $category->id]);
        Post::factory()->create();

        $viSlug = $category->translations()->where('locale', 'vi')->sole()->slug;

        $this->getJson("/api/v1/posts?category={$viSlug}&locale=vi")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        // The Vietnamese slug must not resolve while browsing in English.
        $this->getJson("/api/v1/posts?category={$viSlug}&locale=en")
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_index_filters_by_tag_and_featured_flag(): void
    {
        $tag = Tag::factory()->create();
        $tagged = Post::factory()->featured()->create();
        $tagged->tags()->attach($tag);
        Post::factory()->create();

        $tagSlug = $tag->translations()->where('locale', 'vi')->sole()->slug;

        $this->getJson("/api/v1/posts?tag={$tagSlug}&locale=vi")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/posts?featured=1&locale=vi')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_per_page_is_capped(): void
    {
        Post::factory()->count(3)->create();

        $this->getJson('/api/v1/posts?per_page=500&locale=vi')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 48);
    }

    public function test_show_returns_body_author_and_tags(): void
    {
        $author = User::factory()->create();
        $tag = Tag::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id]);
        $post->tags()->attach($tag);

        $slug = $post->translations()->where('locale', 'vi')->sole()->slug;

        $this->getJson("/api/v1/posts/{$slug}?locale=vi")
            ->assertOk()
            ->assertJsonPath('data.author.name', $author->name)
            ->assertJsonCount(1, 'data.tags')
            ->assertJsonStructure(['data' => ['body', 'meta' => ['title', 'description']]]);
    }

    public function test_related_returns_posts_from_the_same_category_excluding_itself(): void
    {
        $category = Category::factory()->create();
        $post = Post::factory()->create(['category_id' => $category->id]);
        Post::factory()->count(2)->create(['category_id' => $category->id]);
        Post::factory()->create();

        $slug = $post->translations()->where('locale', 'vi')->sole()->slug;

        $response = $this->getJson("/api/v1/posts/{$slug}/related?locale=vi")->assertOk();

        $response->assertJsonCount(2, 'data');
        $this->assertNotContains($post->id, array_column($response->json('data'), 'id'));
    }

    public function test_show_returns_404_for_an_unknown_slug(): void
    {
        $this->getJson('/api/v1/posts/khong-ton-tai?locale=vi')->assertNotFound();
    }
}
