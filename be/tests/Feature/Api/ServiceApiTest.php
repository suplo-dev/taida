<?php

namespace Tests\Feature\Api;

use App\Models\Industry;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_the_tree_of_published_root_services(): void
    {
        $pillar = Service::factory()->create(['sort_order' => 0]);
        Service::factory()->create(['parent_id' => $pillar->id, 'sort_order' => 0]);
        Service::factory()->create(['parent_id' => $pillar->id, 'sort_order' => 1]);

        $response = $this->getJson('/api/v1/services?locale=vi')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonCount(2, 'data.0.children');
        $response->assertJsonStructure([
            'data' => [['id', 'slug', 'name', 'excerpt', 'icon', 'isFeatured', 'cover', 'children']],
        ]);
    }

    public function test_index_can_return_a_flat_list(): void
    {
        $pillar = Service::factory()->create();
        Service::factory()->create(['parent_id' => $pillar->id]);

        $this->getJson('/api/v1/services?flat=1&locale=vi')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_hides_drafts_and_scheduled_services(): void
    {
        Service::factory()->create();
        Service::factory()->draft()->create();
        Service::factory()->scheduled()->create();

        $this->getJson('/api/v1/services?locale=vi')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_each_locale_serves_its_own_name_and_slug(): void
    {
        $service = Service::factory()->create();
        $vi = $service->translations()->where('locale', 'vi')->sole();
        $en = $service->translations()->where('locale', 'en')->sole();

        $this->getJson('/api/v1/services?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $vi->slug)
            ->assertJsonPath('data.0.name', $vi->name);

        $this->getJson('/api/v1/services?locale=en')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $en->slug)
            ->assertJsonPath('data.0.name', $en->name);
    }

    public function test_a_missing_translation_falls_back_to_the_primary_locale(): void
    {
        $service = Service::factory()->create();
        $service->translations()->where('locale', 'en')->delete();
        $vi = $service->translations()->where('locale', 'vi')->sole();

        $this->getJson('/api/v1/services?locale=en')
            ->assertOk()
            ->assertJsonPath('data.0.name', $vi->name);
    }

    public function test_show_returns_the_body_children_and_linked_industries(): void
    {
        $service = Service::factory()->create();
        Service::factory()->create(['parent_id' => $service->id]);
        $industry = Industry::factory()->create();
        $service->industries()->attach($industry);

        $slug = $service->translations()->where('locale', 'vi')->sole()->slug;

        $this->getJson("/api/v1/services/{$slug}?locale=vi")
            ->assertOk()
            ->assertJsonPath('data.id', $service->id)
            ->assertJsonCount(1, 'data.children')
            ->assertJsonCount(1, 'data.industries')
            ->assertJsonStructure(['data' => ['body', 'parent', 'meta' => ['title', 'description']]]);
    }

    public function test_show_rejects_a_slug_belonging_to_another_locale(): void
    {
        $service = Service::factory()->create();
        $englishSlug = $service->translations()->where('locale', 'en')->sole()->slug;

        $this->getJson("/api/v1/services/{$englishSlug}?locale=vi")->assertNotFound();
        $this->getJson("/api/v1/services/{$englishSlug}?locale=en")->assertOk();
    }

    public function test_show_hides_a_draft_service(): void
    {
        $service = Service::factory()->draft()->create();
        $slug = $service->translations()->where('locale', 'vi')->sole()->slug;

        $this->getJson("/api/v1/services/{$slug}?locale=vi")->assertNotFound();
    }
}
