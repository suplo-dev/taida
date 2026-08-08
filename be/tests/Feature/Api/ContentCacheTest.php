<?php

namespace Tests\Feature\Api;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_repeated_request_is_served_from_cache(): void
    {
        Service::factory()->create();

        $this->getJson('/api/v1/services?locale=vi')->assertOk();

        DB::enableQueryLog();
        $this->getJson('/api/v1/services?locale=vi')->assertOk();

        $this->assertSame([], DB::getQueryLog(), 'The cached response should not hit the database.');
    }

    public function test_locales_are_cached_separately(): void
    {
        $service = Service::factory()->create();
        $vi = $service->translations()->where('locale', 'vi')->sole();
        $en = $service->translations()->where('locale', 'en')->sole();

        $this->getJson('/api/v1/services?locale=vi')
            ->assertJsonPath('data.0.name', $vi->name);

        $this->getJson('/api/v1/services?locale=en')
            ->assertJsonPath('data.0.name', $en->name);
    }

    public function test_saving_content_invalidates_the_cache(): void
    {
        $service = Service::factory()->create();

        $this->getJson('/api/v1/services?locale=vi')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Service::factory()->create();

        $this->getJson('/api/v1/services?locale=vi')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_editing_only_a_translation_invalidates_the_cache(): void
    {
        $service = Service::factory()->create();

        $this->getJson('/api/v1/services?locale=vi')->assertOk();

        $service->translations()->where('locale', 'vi')->sole()->update(['name' => 'Tên đã đổi']);

        $this->getJson('/api/v1/services?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Tên đã đổi');
    }
}
