<?php

namespace Tests\Feature;

use App\Models\Concerns\Publishable;
use App\Models\Concerns\Schedulable;
use App\Models\Industry;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Support\SitePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Đăng/nháp được tách làm hai trait, và ranh giới giữa chúng là thứ đã từng
 * hỏng: `Publishable` lọc theo cột `published_at`, nhưng bảng `pages` không có
 * cột đó — nên `Page::published()` ném SQL error. Không ai thấy, vì `Page` chỉ
 * override `isPublished()` chứ không override scope, và truy vấn duy nhất cần
 * tới nó thì viết tay `where('status', ...)`.
 */
class PublishableTest extends TestCase
{
    use RefreshDatabase;

    /** Chính lời gọi từng nổ. */
    public function test_the_published_scope_runs_on_a_model_without_a_schedule(): void
    {
        Page::factory()->create();
        $draft = Page::factory()->draft()->create();

        $published = Page::query()->published()->get();

        $this->assertCount(1, $published);
        $this->assertFalse($published->contains($draft));
    }

    /** Và không được nhắc tới cột mà bảng không có. */
    public function test_a_model_without_a_schedule_has_no_publish_date_column(): void
    {
        $this->assertFalse(Schema::hasColumn('pages', 'published_at'));

        $sql = Page::query()->published()->toSql();

        $this->assertStringNotContainsString('published_at', $sql);
    }

    /**
     * @return list<array{class-string}>
     */
    public static function schedulableModels(): array
    {
        return [[Service::class], [Industry::class], [Post::class]];
    }

    #[DataProvider('schedulableModels')]
    public function test_a_schedulable_model_hides_records_dated_in_the_future(string $model): void
    {
        $future = $model::factory()->create(['published_at' => now()->addDay()]);
        $live = $model::factory()->create(['published_at' => now()->subDay()]);

        $visible = $model::query()->published()->get();

        $this->assertTrue($visible->contains($live));
        $this->assertFalse($visible->contains($future));
        $this->assertFalse($future->isPublished());
        $this->assertTrue($live->isPublished());
    }

    /**
     * Một bản ghi hẹn giờ lên sóng mà không có lần ghi CSDL nào, nên không có
     * model event để bám vào — `SitePublisher` phải canh chúng bằng đồng hồ.
     * Danh sách đó và trait phải nói cùng một điều: thêm `Schedulable` cho một
     * model mà quên thêm vào danh sách thì bản ghi hẹn giờ lên sóng trong CSDL
     * mà site tĩnh không bao giờ dựng lại.
     */
    public function test_the_scheduled_model_list_matches_the_models_using_the_trait(): void
    {
        $watched = (new ReflectionClass(SitePublisher::class))->getConstant('SCHEDULED_MODELS');

        $schedulable = array_values(array_filter(
            [Page::class, Service::class, Industry::class, Post::class],
            fn (string $model): bool => in_array(Schedulable::class, class_uses_recursive($model), true),
        ));

        $this->assertEqualsCanonicalizing($schedulable, $watched);
    }

    /** `Schedulable` chỉ dùng được trên bảng thật sự có cột ngày đăng. */
    public function test_every_schedulable_model_has_the_column_it_filters_on(): void
    {
        foreach ([Service::class, Industry::class, Post::class, Page::class] as $model) {
            $uses = class_uses_recursive($model);

            $this->assertContains(Publishable::class, $uses, "{$model} phải có Publishable");

            if (in_array(Schedulable::class, $uses, true)) {
                $table = (new $model)->getTable();

                $this->assertTrue(
                    Schema::hasColumn($table, 'published_at'),
                    "{$model} dùng Schedulable nhưng bảng {$table} không có cột published_at",
                );
            }
        }
    }
}
