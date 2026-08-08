<?php

namespace Tests\Feature\Api;

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Industry;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_industries_expose_their_linked_services(): void
    {
        $industry = Industry::factory()->create();
        $service = Service::factory()->create();
        $industry->services()->attach($service);

        $slug = $industry->translations()->where('locale', 'vi')->sole()->slug;

        $this->getJson("/api/v1/industries/{$slug}?locale=vi")
            ->assertOk()
            ->assertJsonCount(1, 'data.services')
            ->assertJsonPath('data.services.0.id', $service->id);
    }

    public function test_categories_count_only_published_posts(): void
    {
        $category = Category::factory()->create();
        Post::factory()->count(2)->create(['category_id' => $category->id]);
        Post::factory()->draft()->create(['category_id' => $category->id]);

        $this->getJson('/api/v1/categories?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.0.postCount', 2);
    }

    public function test_a_page_resolves_by_key_and_by_localised_slug(): void
    {
        $page = Page::factory()->create(['key' => 'about-us']);
        $viSlug = $page->translations()->where('locale', 'vi')->sole()->slug;
        $enSlug = $page->translations()->where('locale', 'en')->sole()->slug;

        $this->getJson('/api/v1/pages/about-us?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.slug', $viSlug);

        $this->getJson("/api/v1/pages/{$viSlug}?locale=vi")
            ->assertOk()
            ->assertJsonPath('data.key', 'about-us');

        $this->getJson("/api/v1/pages/{$enSlug}?locale=en")
            ->assertOk()
            ->assertJsonPath('data.key', 'about-us');
    }

    public function test_a_draft_page_is_not_served(): void
    {
        Page::factory()->draft()->create(['key' => 'secret']);

        $this->getJson('/api/v1/pages/secret?locale=vi')->assertNotFound();
    }

    public function test_menus_are_returned_per_location_with_children(): void
    {
        $parent = MenuItem::factory()->create();
        MenuItem::factory()->create(['parent_id' => $parent->id]);
        MenuItem::factory()->footer()->create();

        $this->getJson('/api/v1/menus/header?locale=vi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.children');

        $this->getJson('/api/v1/menus/footer?locale=vi')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_an_unknown_menu_location_is_rejected(): void
    {
        $this->getJson('/api/v1/menus/sidebar?locale=vi')->assertNotFound();
    }

    public function test_settings_are_resolved_for_the_active_locale(): void
    {
        Setting::create(['key' => 'hotline', 'value' => '1900 1234']);
        Setting::create(['key' => 'address', 'value' => ['vi' => 'Hà Nội', 'en' => 'Hanoi']]);
        Setting::create(['key' => 'social', 'value' => ['linkedin' => 'https://example.com']]);

        $this->getJson('/api/v1/settings?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.hotline', '1900 1234')
            ->assertJsonPath('data.address', 'Hà Nội')
            ->assertJsonPath('data.social.linkedin', 'https://example.com');

        $this->getJson('/api/v1/settings?locale=en')
            ->assertOk()
            ->assertJsonPath('data.address', 'Hanoi');
    }

    public function test_detail_endpoints_expose_the_slug_of_every_locale(): void
    {
        // The language switcher builds the other locale's URL from this. Serving
        // only the active locale's slug made it reuse the current one, and the
        // two languages never share a slug — every switch landed on a 404.
        $post = Post::factory()->create();
        $post->translations()->where('locale', 'vi')->update(['slug' => 'tin-tieng-viet']);
        $post->translations()->where('locale', 'en')->update(['slug' => 'english-news']);

        $this->getJson('/api/v1/posts/tin-tieng-viet?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.slug', 'tin-tieng-viet')
            ->assertJsonPath('data.slugs.vi', 'tin-tieng-viet')
            ->assertJsonPath('data.slugs.en', 'english-news');

        // And the same record answers to its English slug, pointing back.
        $this->getJson('/api/v1/posts/english-news?locale=en')
            ->assertOk()
            ->assertJsonPath('data.slug', 'english-news')
            ->assertJsonPath('data.slugs.vi', 'tin-tieng-viet');
    }

    public function test_service_and_industry_details_expose_their_slugs_too(): void
    {
        $service = Service::factory()->create();
        $service->translations()->where('locale', 'vi')->update(['slug' => 'kiem-dinh']);
        $service->translations()->where('locale', 'en')->update(['slug' => 'inspection']);

        $this->getJson('/api/v1/services/kiem-dinh?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.slugs.en', 'inspection');

        $industry = Industry::factory()->create();
        $industry->translations()->where('locale', 'vi')->update(['slug' => 'hoa-chat']);
        $industry->translations()->where('locale', 'en')->update(['slug' => 'chemicals']);

        $this->getJson('/api/v1/industries/hoa-chat?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.slugs.en', 'chemicals');
    }

    public function test_the_logo_setting_is_expanded_from_a_media_id_into_a_usable_url(): void
    {
        $media = Media::factory()->create(['alt' => ['vi' => 'Logo Taida', 'en' => 'Taida logo']]);
        Setting::create(['key' => 'logo', 'value' => $media->id]);

        $this->getJson('/api/v1/settings?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.logo.url', $media->url)
            ->assertJsonPath('data.logo.thumbUrl', $media->thumb_url)
            ->assertJsonPath('data.logo.alt', 'Logo Taida');
    }

    public function test_a_logo_pointing_at_a_deleted_record_is_reported_as_absent(): void
    {
        // The frontend renders its bundled default on null. Leaving the dangling
        // id in place would instead put a broken image in the header.
        $media = Media::factory()->create();
        Setting::create(['key' => 'logo', 'value' => $media->id]);
        $media->delete();

        $this->getJson('/api/v1/settings?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.logo', null);
    }

    public function test_settings_have_a_null_logo_until_one_is_chosen(): void
    {
        Setting::create(['key' => 'hotline', 'value' => '1900 1234']);

        $this->getJson('/api/v1/settings?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.logo', null);
    }

    public function test_search_matches_the_active_locale_only(): void
    {
        $service = Service::factory()->create();
        $service->translations()->where('locale', 'vi')->update(['name' => 'Chứng nhận ISO']);
        $service->translations()->where('locale', 'en')->update(['name' => 'ISO Certification']);

        $this->getJson('/api/v1/search?q=Chứng&locale=vi')
            ->assertOk()
            ->assertJsonCount(1, 'data.services');

        $this->getJson('/api/v1/search?q=Chứng&locale=en')
            ->assertOk()
            ->assertJsonCount(0, 'data.services');
    }

    public function test_search_ignores_a_term_shorter_than_two_characters(): void
    {
        Service::factory()->create();

        $this->getJson('/api/v1/search?q=a&locale=vi')
            ->assertOk()
            ->assertJsonCount(0, 'data.services');
    }

    public function test_sitemap_lists_every_locale_slug_for_published_content(): void
    {
        $service = Service::factory()->create();
        Service::factory()->draft()->create();
        Page::factory()->create(['status' => ContentStatus::Published]);

        $response = $this->getJson('/api/v1/sitemap-urls?locale=vi')->assertOk();

        $services = array_values(array_filter($response->json('data'), fn (array $e) => $e['type'] === 'service'));
        $this->assertCount(1, $services);
        $this->assertSame($service->id, $services[0]['id']);
        $this->assertEqualsCanonicalizing(['vi', 'en'], array_keys($services[0]['slugs']));
    }
}
