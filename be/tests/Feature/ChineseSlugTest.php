<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tiếng Trung không có địa chỉ riêng: nó dùng đúng slug của bản tiếng Anh.
 *
 * `Str::slug()` giữ ASCII và bỏ phần còn lại, nên một tiêu đề viết toàn ký tự
 * Hán rút lại thành chuỗi RỖNG — 质量保证 không còn gì để dựng địa chỉ. Sinh
 * chuỗi ngẫu nhiên thì mọi trang tiếng Trung mang một địa chỉ vô nghĩa và đổi
 * sau mỗi lần lưu; nên nó soi gương bản tiếng Anh.
 *
 * Đổi lại được hai thứ nữa: /zh/about-us là cùng một địa chỉ dù trang đã dịch
 * hay chưa, nên dịch một trang không bao giờ làm nó chuyển chỗ; và không hai bản
 * ghi nào tranh nhau được một địa chỉ /zh, vì slug tiếng Anh mà chúng soi vốn đã
 * duy nhất.
 */
class ChineseSlugTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    private function savePage(array $translations, string $key = 'about-us', ?int $id = null): Page
    {
        $payload = ['key' => $key, 'status' => 'published', 'translations' => $translations];

        $this->actingAs($this->admin)
            ->{$id ? 'putJson' : 'postJson'}('/api/v1/admin/pages'.($id ? "/{$id}" : ''), $payload)
            ->assertSuccessful();

        return Page::query()->where('key', $key)->sole();
    }

    private function slug(Page $page, string $locale): ?string
    {
        return $page->translations()->where('locale', $locale)->value('slug');
    }

    public function test_the_chinese_slug_is_the_english_one(): void
    {
        $page = $this->savePage([
            'vi' => ['title' => 'Chất lượng toàn diện'],
            'en' => ['title' => 'Total Quality Assurance'],
            'zh' => ['title' => '全面质量保证'],
        ]);

        $this->assertSame('total-quality-assurance', $this->slug($page, 'en'));
        $this->assertSame('total-quality-assurance', $this->slug($page, 'zh'));
    }

    /** Kể cả khi biên tập viên sửa tay slug tiếng Anh — bản sao phải theo. */
    public function test_it_mirrors_a_hand_edited_english_slug_rather_than_the_english_title(): void
    {
        $page = $this->savePage([
            'vi' => ['title' => 'Về chúng tôi'],
            'en' => ['title' => 'About Us', 'slug' => 'who-we-are'],
            'zh' => ['title' => '关于我们'],
        ]);

        $this->assertSame('who-we-are', $this->slug($page, 'zh'));
    }

    /** Slug tiếng Trung gửi lên bị bỏ qua: admin không còn ô đó nữa. */
    public function test_a_submitted_chinese_slug_is_ignored(): void
    {
        $page = $this->savePage([
            'vi' => ['title' => 'Về chúng tôi'],
            'en' => ['title' => 'About Us'],
            'zh' => ['title' => '关于我们', 'slug' => 'guan-yu-wo-men'],
        ]);

        $this->assertSame('about-us', $this->slug($page, 'zh'));
    }

    /** Hết tiếng Anh thì soi tiếng Việt — cùng thứ tự với `Locales::chain()`. */
    public function test_it_falls_back_to_vietnamese_when_there_is_no_english(): void
    {
        $page = $this->savePage([
            'vi' => ['title' => 'Chất lượng toàn diện'],
            'zh' => ['title' => '全面质量保证'],
        ]);

        $this->assertSame('chat-luong-toan-dien', $this->slug($page, 'zh'));
    }

    /**
     * Trước khi dịch, trang trả lời ở slug tiếng Anh nhờ luật rơi ngôn ngữ. Sau
     * khi dịch, nó phải trả lời ở ĐÚNG địa chỉ đó — dịch không được làm gãy link
     * đã phát ra ngoài.
     */
    public function test_translating_a_page_does_not_move_its_chinese_address(): void
    {
        $page = $this->savePage([
            'vi' => ['title' => 'Về chúng tôi'],
            'en' => ['title' => 'About Us'],
        ]);

        $this->getJson('/api/v1/pages/about-us?locale=zh')->assertOk();
        $this->assertNull($this->slug($page, 'zh'));

        $this->savePage([
            'vi' => ['title' => 'Về chúng tôi'],
            'en' => ['title' => 'About Us'],
            'zh' => ['title' => '关于我们'],
        ], id: $page->id);

        $this->assertSame('about-us', $this->slug($page->fresh(), 'zh'));

        $this->getJson('/api/v1/pages/about-us?locale=zh')
            ->assertOk()
            ->assertJsonPath('data.title', '关于我们');
    }

    /**
     * Đổi slug tiếng Anh thì bản tiếng Trung đi theo, chứ không mắc kẹt ở địa
     * chỉ cũ — hai trang cùng nội dung ở hai địa chỉ lệch nhau là thứ khó thấy
     * nhất khi nó xảy ra.
     */
    public function test_changing_the_english_slug_moves_the_chinese_one_too(): void
    {
        $page = $this->savePage([
            'vi' => ['title' => 'Về chúng tôi'],
            'en' => ['title' => 'About Us'],
            'zh' => ['title' => '关于我们'],
        ]);

        $this->savePage([
            'vi' => ['title' => 'Về chúng tôi'],
            'en' => ['title' => 'About Us', 'slug' => 'who-we-are'],
            'zh' => ['title' => '关于我们'],
        ], id: $page->id);

        $this->assertSame('who-we-are', $this->slug($page->fresh(), 'zh'));
    }

    /** Hai bản ghi không bao giờ tranh được cùng một địa chỉ /zh. */
    public function test_two_records_cannot_claim_the_same_chinese_address(): void
    {
        $first = $this->savePage([
            'vi' => ['title' => 'Đào tạo'],
            'en' => ['title' => 'Training'],
            'zh' => ['title' => '培训'],
        ], key: 'training');

        $second = $this->savePage([
            'vi' => ['title' => 'Đào tạo nội bộ'],
            'en' => ['title' => 'Training'],
            'zh' => ['title' => '内部培训'],
        ], key: 'internal-training');

        $this->assertSame('training', $this->slug($first, 'zh'));
        $this->assertSame('training-2', $this->slug($second, 'en'));
        $this->assertSame('training-2', $this->slug($second, 'zh'));
    }
}
