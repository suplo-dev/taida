<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\MenuTarget;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Post;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Support\SitePublisher;
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

    public function test_changing_only_the_tags_of_a_live_post_queues_a_build(): void
    {
        $post = Post::factory()->create();
        $tag = Tag::factory()->create();

        cache()->forget('publish:stale-since');
        cache()->forget('publish:last-change');
        $this->assertFalse(SitePublisher::isStale());

        // Pivot rows fire no model event, so nothing about this request would
        // reach ContentObserver on its own — and the payload deliberately keeps
        // every column of the post itself unchanged.
        $this->actingAs($this->editor)
            ->putJson("/api/v1/admin/posts/{$post->id}", [
                'status' => $post->status->value,
                'published_at' => $post->published_at->toIso8601String(),
                'category_id' => $post->category_id,
                'tag_ids' => [$tag->id],
                'translations' => ['vi' => ['title' => $post->translate('vi')->title]],
            ])
            ->assertOk();

        $this->assertSame(1, $post->tags()->count());
        $this->assertTrue(SitePublisher::isStale());
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
        $service = Service::factory()->create();
        $enSlug = $service->translations()->where('locale', 'en')->sole()->slug;

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/menus/header', [
                'items' => [
                    [
                        'target_type' => 'route',
                        'target_route' => 'services',
                        'translations' => [
                            'vi' => ['label' => 'Dịch vụ'],
                            'en' => ['label' => 'Services'],
                        ],
                        'children' => [
                            [
                                'target_type' => 'service',
                                'target_id' => $service->id,
                                'translations' => [
                                    'vi' => ['label' => 'Chứng nhận'],
                                    'en' => ['label' => 'Certification'],
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

        /*
         * API đặt tên đích đến chứ không dựng URL: nó biết bản ghi và luật rơi
         * về ngôn ngữ khác, còn frontend biết /dich-vu khác /en/services. Không
         * bên nào phải giữ bản sao bản đồ của bên kia.
         */
        $this->getJson('/api/v1/menus/header?locale=en')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Services')
            ->assertJsonPath('data.0.target', ['type' => 'route', 'route' => 'services'])
            ->assertJsonPath('data.0.children.0.target', ['type' => 'service', 'slug' => $enSlug]);
    }

    /**
     * Đổi loại đích mà còn sót cột của loại cũ thì bản ghi tự mâu thuẫn, và chỗ
     * đọc sau này tin vào cột nào là chuyện may rủi.
     */
    public function test_switching_target_type_clears_the_other_columns(): void
    {
        $service = Service::factory()->create();

        $save = fn (array $target) => $this->actingAs($this->admin)->putJson('/api/v1/admin/menus/footer', [
            'items' => [[...$target, 'translations' => ['vi' => ['label' => 'Mục']]]],
        ])->assertOk();

        $save(['target_type' => 'service', 'target_id' => $service->id]);
        $save(['target_type' => 'external', 'external_url' => 'https://example.com']);

        $item = MenuItem::query()->where('location', 'footer')->sole();

        $this->assertNull($item->target_id);
        $this->assertNull($item->target_route);
        $this->assertSame('https://example.com', $item->external_url);
    }

    /** Mục trỏ tới bản ghi đã bị xoá hoặc chuyển về nháp thì biến mất khỏi site. */
    public function test_the_public_menu_leaves_out_items_with_nowhere_to_go(): void
    {
        $draft = Service::factory()->draft()->create();

        MenuItem::factory()->footer()->pointingAt(MenuTarget::Service, $draft->id)->create();
        MenuItem::factory()->footer()->withoutTarget()->create();
        MenuItem::factory()->footer()->create();

        $this->getJson('/api/v1/menus/footer?locale=vi')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_menu_item_cannot_point_at_a_record_that_does_not_exist(): void
    {
        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/menus/header', [
                'items' => [[
                    'target_type' => 'page',
                    'target_id' => 9999,
                    'translations' => ['vi' => ['label' => 'Mục']],
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.target_id');
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

    public function test_only_enabled_social_networks_reach_the_public_site(): void
    {
        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', [
                'settings' => [
                    'social' => [
                        'tiktok' => ['url' => 'https://www.tiktok.com/@taida', 'enabled' => true],
                        'lemon8' => ['url' => 'https://www.lemon8-app.com/taida', 'enabled' => false],
                        'facebook' => ['url' => '', 'enabled' => true],
                    ],
                ],
            ])
            ->assertOk()
            // Trang cấu hình cần thấy cả mạng đang tắt để còn bật lại được.
            ->assertJsonPath('data.social.lemon8.url', 'https://www.lemon8-app.com/taida')
            ->assertJsonPath('data.social.lemon8.enabled', false);

        $this->getJson('/api/v1/settings?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.social.tiktok', 'https://www.tiktok.com/@taida')
            // Tắt thì không ra tới chân trang, và bật mà bỏ trống địa chỉ cũng vậy.
            ->assertJsonMissingPath('data.social.lemon8')
            ->assertJsonMissingPath('data.social.facebook');
    }

    /**
     * Trước khi có công tắc, mỗi mạng chỉ là một chuỗi địa chỉ. Những hàng đó vẫn
     * nằm trong database của site đang chạy, nên chúng phải tự lên dạng mới —
     * đang bật — chứ không được biến mất khỏi chân trang sau khi deploy.
     */
    public function test_social_links_saved_before_the_switch_existed_are_still_shown(): void
    {
        Setting::create(['key' => 'social', 'value' => ['facebook' => 'https://facebook.com/taida']]);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.social.facebook.url', 'https://facebook.com/taida')
            ->assertJsonPath('data.social.facebook.enabled', true);

        $this->getJson('/api/v1/settings?locale=vi')
            ->assertOk()
            ->assertJsonPath('data.social.facebook', 'https://facebook.com/taida');
    }

    /** Endpoint này nhận cấu hình từng phần, nên gửi một mạng không được xoá các mạng còn lại. */
    public function test_saving_one_social_network_leaves_the_others_alone(): void
    {
        Setting::create(['key' => 'social', 'value' => [
            'facebook' => ['url' => 'https://facebook.com/taida', 'enabled' => true],
        ]]);

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', [
                'settings' => ['social' => ['tiktok' => ['url' => 'https://www.tiktok.com/@taida', 'enabled' => true]]],
            ])
            ->assertOk()
            ->assertJsonPath('data.social.facebook.url', 'https://facebook.com/taida')
            ->assertJsonPath('data.social.tiktok.url', 'https://www.tiktok.com/@taida');
    }

    public function test_a_social_address_without_a_scheme_is_rejected(): void
    {
        // "facebook.com/taida" trong href là đường dẫn tương đối của chính site.
        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', [
                'settings' => ['social' => ['facebook' => ['url' => 'facebook.com/taida', 'enabled' => true]]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.social.facebook.url');
    }

    public function test_contact_qr_codes_round_trip_and_only_the_enabled_ones_are_public(): void
    {
        $zalo = Media::factory()->create();
        $wechat = Media::factory()->create();

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', [
                'settings' => [
                    'contactQr' => [
                        ['label' => 'Zalo', 'media' => $zalo->id, 'enabled' => true],
                        ['label' => 'WeChat', 'media' => $wechat->id, 'enabled' => false],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.contactQr')
            ->assertJsonPath('data.contactQr.0.label', 'Zalo')
            ->assertJsonPath('data.contactQr.0.media.thumbUrl', $zalo->thumb_url);

        // Hàng lưu lại chỉ giữ id, giống logo, để URL luôn dựng từ cấu hình đĩa.
        $this->assertSame(
            [['label' => 'Zalo', 'media' => $zalo->id, 'enabled' => true], ['label' => 'WeChat', 'media' => $wechat->id, 'enabled' => false]],
            Setting::query()->find('contactQr')->value,
        );

        $this->getJson('/api/v1/settings?locale=vi')
            ->assertOk()
            ->assertJsonCount(1, 'data.contactQr')
            ->assertJsonPath('data.contactQr.0.label', 'Zalo')
            ->assertJsonPath('data.contactQr.0.media.url', $zalo->url);
    }

    /** Bộ chọn ảnh gửi lại nguyên bản ghi media mà nó vừa nhận từ endpoint đọc. */
    public function test_an_expanded_qr_code_can_be_sent_straight_back_unchanged(): void
    {
        $media = Media::factory()->create();
        Setting::create(['key' => 'contactQr', 'value' => [['label' => 'Zalo', 'media' => $media->id, 'enabled' => true]]]);

        $items = $this->actingAs($this->admin)->getJson('/api/v1/admin/settings')->json('data.contactQr');

        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', ['settings' => ['contactQr' => $items]])
            ->assertOk()
            ->assertJsonPath('data.contactQr.0.media.id', $media->id);

        $this->assertSame($media->id, Setting::query()->find('contactQr')->value[0]['media']);
    }

    public function test_a_qr_code_needs_both_a_label_and_a_real_image(): void
    {
        $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', [
                'settings' => ['contactQr' => [['label' => '', 'media' => 9999, 'enabled' => true]]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.contactQr.0.label', 'settings.contactQr.0.media']);
    }

    /** Ảnh bị xoá khỏi thư viện thì hàng QR không còn gì để vẽ — bỏ hẳn, như logo. */
    public function test_a_qr_code_pointing_at_a_deleted_image_disappears(): void
    {
        $media = Media::factory()->create();
        Setting::create(['key' => 'contactQr', 'value' => [['label' => 'Zalo', 'media' => $media->id, 'enabled' => true]]]);
        $media->delete();

        $this->getJson('/api/v1/settings?locale=vi')
            ->assertOk()
            ->assertJsonCount(0, 'data.contactQr');

        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonCount(0, 'data.contactQr');
    }
}
