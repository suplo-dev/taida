<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentAdminTest extends TestCase
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

    public function test_a_post_records_its_author_and_syncs_tags(): void
    {
        $category = Category::factory()->create();
        $tags = Tag::factory()->count(2)->create();

        $response = $this->actingAs($this->editor)
            ->postJson('/api/v1/admin/posts', [
                'status' => 'published',
                'published_at' => now()->toIso8601String(),
                'category_id' => $category->id,
                'tag_ids' => $tags->pluck('id')->all(),
                'translations' => [
                    'vi' => ['title' => 'Cập nhật quy định mới', 'excerpt' => 'Tóm tắt'],
                    'en' => ['title' => 'New regulatory update', 'excerpt' => 'Summary'],
                ],
            ])
            ->assertCreated();

        $post = Post::query()->sole();

        $response->assertJsonPath('data.author', $this->editor->name);
        $this->assertSame($this->editor->id, $post->author_id);
        $this->assertSame(2, $post->tags()->count());
        $this->assertSame('cap-nhat-quy-dinh-moi', $post->translations()->where('locale', 'vi')->sole()->slug);
    }

    public function test_the_admin_post_list_can_be_filtered_by_status(): void
    {
        Post::factory()->count(2)->create();
        Post::factory()->draft()->create();

        $this->actingAs($this->editor)
            ->getJson('/api/v1/admin/posts?status=draft')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_page_key_must_be_unique(): void
    {
        $payload = [
            'key' => 'about-us',
            'status' => 'published',
            'translations' => ['vi' => ['title' => 'Về chúng tôi']],
        ];

        $this->actingAs($this->editor)->postJson('/api/v1/admin/pages', $payload)->assertCreated();

        $this->actingAs($this->editor)
            ->postJson('/api/v1/admin/pages', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');
    }

    public function test_uploading_an_image_stores_it_with_a_thumbnail(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->editor)
            ->postJson('/api/v1/admin/media', [
                'file' => UploadedFile::fake()->image('hero.jpg', 1600, 900),
                'alt' => ['vi' => 'Ảnh bìa', 'en' => 'Cover image'],
            ])
            ->assertCreated();

        $media = Media::query()->sole();

        Storage::disk('public')->assertExists($media->path);
        Storage::disk('public')->assertExists($media->thumb_path);
        $this->assertSame(1600, $media->width);
        $this->assertSame('Ảnh bìa', $media->altFor('vi'));
        $this->assertSame('Cover image', $media->altFor('en'));

        // The resource resolves alt text for whatever locale is in play.
        $response->assertJsonPath('data.alt', $media->altFor());
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->editor)
            ->postJson('/api/v1/admin/media', ['file' => UploadedFile::fake()->create('report.pdf', 100)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_deleting_media_removes_the_files(): void
    {
        Storage::fake('public');

        $this->actingAs($this->editor)
            ->postJson('/api/v1/admin/media', ['file' => UploadedFile::fake()->image('hero.jpg')])
            ->assertCreated();

        $media = Media::query()->sole();

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/media/{$media->id}")
            ->assertNoContent();

        Storage::disk('public')->assertMissing($media->path);
        Storage::disk('public')->assertMissing($media->thumb_path);
    }

    public function test_saving_a_menu_replaces_the_tree_for_that_location(): void
    {
        MenuItem::factory()->create();

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/menus/header', [
                'items' => [
                    [
                        'translations' => [
                            'vi' => ['label' => 'Dịch vụ', 'url' => '/dich-vu'],
                            'en' => ['label' => 'Services', 'url' => '/en/services'],
                        ],
                        'children' => [
                            [
                                'translations' => [
                                    'vi' => ['label' => 'Chứng nhận', 'url' => '/dich-vu/chung-nhan'],
                                    'en' => ['label' => 'Certification', 'url' => '/en/services/certification'],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.children')
            ->assertJsonPath('data.0.translations.vi.label', 'Dịch vụ');

        $this->assertSame(2, MenuItem::query()->count());

        $this->getJson('/api/v1/menus/header?locale=en')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Services')
            ->assertJsonPath('data.0.children.0.url', '/en/services/certification');
    }

    public function test_menus_and_settings_are_admin_only(): void
    {
        $this->actingAs($this->editor)->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->actingAs($this->editor)->putJson('/api/v1/admin/menus/header', ['items' => []])->assertForbidden();

        $this->actingAs($this->admin)->getJson('/api/v1/admin/settings')->assertOk();
    }

    public function test_settings_round_trip_through_the_admin_and_public_endpoints(): void
    {
        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', [
                'settings' => [
                    'hotline' => '1900 1234',
                    'address' => ['vi' => 'Hà Nội', 'en' => 'Hanoi'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.address.vi', 'Hà Nội');

        $this->assertSame('1900 1234', Setting::query()->find('hotline')->value);

        // The public endpoint collapses the localised value for the visitor.
        $this->getJson('/api/v1/settings?locale=en')
            ->assertOk()
            ->assertJsonPath('data.address', 'Hanoi');
    }

    /**
     * Ảnh hero đi đúng đường của logo — nó nằm trong MEDIA_KEYS — nhưng trang chủ
     * công khai mới là nơi dùng nó, nên phải chắc rằng endpoint công khai cũng bung
     * ra thành bản ghi media chứ không trả về một con số id.
     */
    public function test_the_hero_image_reaches_the_public_endpoint_as_a_media_record(): void
    {
        $media = Media::factory()->create();

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', ['settings' => ['heroImage' => $media->id]])
            ->assertOk();

        $this->getJson('/api/v1/settings?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.heroImage.id', $media->id)
            ->assertJsonPath('data.heroImage.url', $media->url);
    }

    public function test_the_hero_video_must_be_an_https_address(): void
    {
        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', ['settings' => ['heroVideo' => 'không-phải-địa-chỉ']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.heroVideo');

        // http bị trình duyệt chặn dưới dạng mixed content trên site https.
        // Thông báo phải đọc được: trang cấu hình chỉ hiện nó dưới dạng một dòng
        // toast, không gắn vào ô nhập nào.
        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', ['settings' => ['heroVideo' => 'http://cdn.example.com/hero.mp4']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'settings.heroVideo' => 'Địa chỉ video nền phải bắt đầu bằng https:// và trỏ tới một file video (ví dụ https://cdn.example.com/hero.mp4).',
            ]);

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', ['settings' => ['heroVideo' => 'https://cdn.example.com/hero.mp4']])
            ->assertOk()
            ->assertJsonPath('data.heroVideo', 'https://cdn.example.com/hero.mp4');
    }

    /** Ô trống trong form là chuỗi rỗng, và nó phải xoá được địa chỉ đã lưu. */
    public function test_the_hero_video_can_be_cleared_with_an_empty_field(): void
    {
        Setting::create(['key' => 'heroVideo', 'value' => 'https://cdn.example.com/hero.mp4']);

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', ['settings' => ['heroVideo' => '']])
            ->assertOk()
            ->assertJsonPath('data.heroVideo', null);
    }

    public function test_the_logo_is_stored_as_an_id_but_read_back_as_a_media_record(): void
    {
        $media = Media::factory()->create();

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', ['settings' => ['logo' => $media->id]])
            ->assertOk()
            // The picker needs a thumbnail, so the response expands the id …
            ->assertJsonPath('data.logo.id', $media->id)
            ->assertJsonPath('data.logo.thumbUrl', $media->thumb_url);

        // … while the row itself keeps only the id, so the URL is always
        // rebuilt from the disk config rather than frozen at the time of saving.
        $this->assertSame($media->id, Setting::query()->find('logo')->value);
    }

    public function test_the_expanded_logo_can_be_sent_straight_back_unchanged(): void
    {
        $media = Media::factory()->create();
        Setting::create(['key' => 'logo', 'value' => $media->id]);

        $logo = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/settings')
            ->json('data.logo');

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', ['settings' => ['logo' => $logo]])
            ->assertOk();

        $this->assertSame($media->id, Setting::query()->find('logo')->value);
    }

    public function test_the_logo_can_be_cleared(): void
    {
        Setting::create(['key' => 'logo', 'value' => Media::factory()->create()->id]);

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', ['settings' => ['logo' => null]])
            ->assertOk()
            ->assertJsonPath('data.logo', null);

        $this->assertNull(Setting::query()->find('logo')->value);
    }

    public function test_a_logo_that_is_not_a_real_media_record_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', ['settings' => ['logo' => 9999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.logo');
    }
}
