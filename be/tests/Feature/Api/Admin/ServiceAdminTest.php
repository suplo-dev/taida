<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\UserRole;
use App\Models\Industry;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = User::factory()->create(['role' => UserRole::Editor]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'status' => 'published',
            'sort_order' => 1,
            'is_featured' => true,
            'translations' => [
                'vi' => ['name' => 'Chứng nhận ISO', 'excerpt' => 'Mô tả ngắn'],
                'en' => ['name' => 'ISO Certification', 'excerpt' => 'Short description'],
            ],
            ...$overrides,
        ];
    }

    public function test_creating_a_service_writes_both_locales_and_derives_slugs(): void
    {
        $response = $this->actingAs($this->editor)
            ->postJson('/api/v1/admin/services', $this->payload())
            ->assertCreated();

        $service = Service::query()->sole();

        $this->assertSame('chung-nhan-iso', $service->translations()->where('locale', 'vi')->sole()->slug);
        $this->assertSame('iso-certification', $service->translations()->where('locale', 'en')->sole()->slug);
        $response->assertJsonPath('data.translations.vi.name', 'Chứng nhận ISO');
        $response->assertJsonPath('data.translations.en.name', 'ISO Certification');
    }

    public function test_an_explicit_slug_is_respected(): void
    {
        $this->actingAs($this->editor)
            ->postJson('/api/v1/admin/services', $this->payload([
                'translations' => [
                    'vi' => ['name' => 'Chứng nhận ISO', 'slug' => 'iso-vn'],
                    'en' => ['name' => 'ISO Certification', 'slug' => 'iso-en'],
                ],
            ]))
            ->assertCreated();

        $this->assertSame('iso-vn', Service::query()->sole()->translations()->where('locale', 'vi')->sole()->slug);
    }

    /**
     * `Str::slug()` keeps ASCII only, so a Chinese title reduces to an empty
     * string and used to land on `Str::random(8)` — an unreadable address that
     * changed on every save. The record's English name stands in instead.
     */
    public function test_a_chinese_title_borrows_its_slug_from_the_english_name(): void
    {
        $this->actingAs($this->editor)
            ->postJson('/api/v1/admin/services', $this->payload([
                'translations' => [
                    'vi' => ['name' => 'Chứng nhận ISO'],
                    'en' => ['name' => 'ISO Certification'],
                    'zh' => ['name' => '质量保证认证'],
                ],
            ]))
            ->assertCreated();

        $service = Service::query()->sole();

        $this->assertSame('iso-certification', $service->translations()->where('locale', 'zh')->sole()->slug);
    }

    /**
     * Only a title with NO ASCII in it borrows from another language. A title
     * that mixes the two keeps its own: "ISO 9001质量管理" is still recognisable
     * as `iso-9001`, and it is what the editor actually typed.
     */
    public function test_a_mixed_title_keeps_the_ascii_it_already_has(): void
    {
        $this->actingAs($this->editor)
            ->postJson('/api/v1/admin/services', $this->payload([
                'translations' => [
                    'vi' => ['name' => 'Chứng nhận ISO'],
                    'en' => ['name' => 'ISO Certification'],
                    'zh' => ['name' => 'ISO 9001质量管理'],
                ],
            ]))
            ->assertCreated();

        $this->assertSame(
            'iso-9001',
            Service::query()->sole()->translations()->where('locale', 'zh')->sole()->slug,
        );
    }

    /** Without an English name to borrow, the Vietnamese one is next. */
    public function test_a_chinese_title_falls_back_to_the_vietnamese_name_when_english_is_blank(): void
    {
        $this->actingAs($this->editor)
            ->postJson('/api/v1/admin/services', $this->payload([
                'translations' => [
                    'vi' => ['name' => 'Chứng nhận ISO'],
                    'zh' => ['name' => '质量保证认证'],
                ],
            ]))
            ->assertCreated();

        $service = Service::query()->sole();

        $this->assertSame('chung-nhan-iso', $service->translations()->where('locale', 'zh')->sole()->slug);
    }

    public function test_a_colliding_slug_gets_a_counter_appended(): void
    {
        $this->actingAs($this->editor)->postJson('/api/v1/admin/services', $this->payload())->assertCreated();
        $this->actingAs($this->editor)->postJson('/api/v1/admin/services', $this->payload())->assertCreated();

        $slugs = Service::query()->with('translations')->get()
            ->flatMap(fn (Service $s) => $s->translations->where('locale', 'vi')->pluck('slug'))
            ->all();

        $this->assertEqualsCanonicalizing(['chung-nhan-iso', 'chung-nhan-iso-2'], $slugs);
    }

    public function test_the_primary_locale_is_required(): void
    {
        $this->actingAs($this->editor)
            ->postJson('/api/v1/admin/services', $this->payload([
                'translations' => ['en' => ['name' => 'ISO Certification']],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('translations.vi');
    }

    public function test_the_secondary_locale_may_be_left_blank(): void
    {
        $this->actingAs($this->editor)
            ->postJson('/api/v1/admin/services', $this->payload([
                'translations' => ['vi' => ['name' => 'Chứng nhận ISO'], 'en' => []],
            ]))
            ->assertCreated();

        $this->assertSame(1, Service::query()->sole()->translations()->count());
    }

    public function test_clearing_a_translation_removes_it_so_the_fallback_applies(): void
    {
        $service = Service::factory()->create();
        $this->assertSame(count(config('app.supported_locales')), $service->translations()->count());

        $this->actingAs($this->editor)
            ->putJson("/api/v1/admin/services/{$service->id}", $this->payload([
                'translations' => [
                    'vi' => ['name' => 'Chỉ tiếng Việt'],
                    'en' => ['name' => null],
                    'zh' => ['name' => null],
                ],
            ]))
            ->assertOk();

        $this->assertSame(['vi'], $service->translations()->pluck('locale')->all());
    }

    public function test_updating_keeps_its_own_slug_without_appending_a_counter(): void
    {
        $this->actingAs($this->editor)->postJson('/api/v1/admin/services', $this->payload())->assertCreated();
        $service = Service::query()->sole();

        $this->actingAs($this->editor)
            ->putJson("/api/v1/admin/services/{$service->id}", $this->payload())
            ->assertOk();

        $this->assertSame('chung-nhan-iso', $service->translations()->where('locale', 'vi')->sole()->slug);
    }

    public function test_linked_industries_are_synced(): void
    {
        $industries = Industry::factory()->count(2)->create();

        $this->actingAs($this->editor)
            ->postJson('/api/v1/admin/services', $this->payload([
                'industry_ids' => $industries->pluck('id')->all(),
            ]))
            ->assertCreated();

        $this->assertSame(2, Service::query()->sole()->industries()->count());
    }

    public function test_a_service_cannot_become_its_own_parent(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->editor)
            ->putJson("/api/v1/admin/services/{$service->id}", $this->payload(['parent_id' => $service->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_reordering_persists_positions_and_parents(): void
    {
        $first = Service::factory()->create(['sort_order' => 0]);
        $second = Service::factory()->create(['sort_order' => 1]);

        $this->actingAs($this->editor)
            ->putJson('/api/v1/admin/services/reorder', [
                'positions' => [
                    ['id' => $second->id, 'sort_order' => 0, 'parent_id' => null],
                    ['id' => $first->id, 'sort_order' => 1, 'parent_id' => $second->id],
                ],
            ])
            ->assertOk();

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame($second->id, $first->fresh()->parent_id);
    }

    public function test_reordering_refreshes_the_public_response(): void
    {
        $first = Service::factory()->create(['sort_order' => 0]);
        $second = Service::factory()->create(['sort_order' => 1]);

        $this->assertSame(
            $first->id,
            $this->getJson('/api/v1/services?locale=vi')->json('data.0.id'),
        );

        // Reordering is a mass update, which fires no model events — the
        // controller has to invalidate the cache itself.
        $this->actingAs($this->editor)
            ->putJson('/api/v1/admin/services/reorder', [
                'positions' => [
                    ['id' => $second->id, 'sort_order' => 0],
                    ['id' => $first->id, 'sort_order' => 1],
                ],
            ])
            ->assertOk();

        $this->assertSame(
            $second->id,
            $this->getJson('/api/v1/services?locale=vi')->json('data.0.id'),
        );
    }

    public function test_only_an_admin_may_delete(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->editor)
            ->deleteJson("/api/v1/admin/services/{$service->id}")
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/services/{$service->id}")
            ->assertNoContent();

        $this->assertDatabaseCount('services', 0);
    }

    public function test_the_admin_index_includes_drafts(): void
    {
        Service::factory()->create();
        Service::factory()->draft()->create();

        $this->actingAs($this->editor)
            ->getJson('/api/v1/admin/services')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_saving_through_the_admin_api_refreshes_the_public_response(): void
    {
        $this->getJson('/api/v1/services?locale=vi')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($this->editor)->postJson('/api/v1/admin/services', $this->payload())->assertCreated();

        $this->getJson('/api/v1/services?locale=vi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Chứng nhận ISO');
    }
}
