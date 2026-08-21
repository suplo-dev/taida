<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\MenuTarget;
use App\Enums\SiteRoute;
use App\Http\Controllers\Controller;
use App\Models\Industry;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Everything a menu item is allowed to point at, for the picker in the admin.
 *
 * One response rather than a search endpoint: the whole site is a few dozen
 * records, so the list is a couple of kilobytes and the picker filters it in
 * the browser — instantly, with no debounce and no spinner. Worth revisiting if
 * posts ever run to the hundreds; `USelectMenu` takes over the filtering with
 * `ignore-filter` when that day comes.
 */
class MenuTargetController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            ...$this->routes(),
            ...$this->records(MenuTarget::Page, Page::query()->orderBy('key')->get()),
            ...$this->records(MenuTarget::Service, Service::query()->get()),
            ...$this->records(MenuTarget::Industry, Industry::query()->get()),
            ...$this->records(MenuTarget::Post, Post::query()->latest('published_at')->get()),
        ]]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function routes(): array
    {
        $labels = [
            SiteRoute::Home->value => 'Trang chủ',
            SiteRoute::Services->value => 'Danh sách dịch vụ',
            SiteRoute::Industries->value => 'Danh sách ngành nghề',
            SiteRoute::Insights->value => 'Danh sách tin tức',
            SiteRoute::Search->value => 'Trang tìm kiếm',
        ];

        return array_map(fn (SiteRoute $route): array => [
            'type' => MenuTarget::Route->value,
            'route' => $route->value,
            'id' => null,
            'label' => $labels[$route->value],
            'published' => true,
        ], SiteRoute::cases());
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return list<array<string, mixed>>
     */
    private function records(MenuTarget $type, $records): array
    {
        return $records->map(fn (Model $record): array => [
            'type' => $type->value,
            'route' => null,
            'id' => $record->id,
            'label' => $this->label($record),
            // Bản nháp vẫn nằm trong danh sách — biên tập viên hay dựng menu
            // trước rồi mới đăng — nhưng được đánh dấu, vì mục trỏ tới nháp sẽ
            // không hiện trên site.
            'published' => $record->isPublished(),
        ])->all();
    }

    /** Tên bản ghi ở ngôn ngữ chính; chưa có thì lấy ngôn ngữ nào đang có. */
    private function label(Model $record): string
    {
        foreach (Locales::chain(Locales::primary()) as $locale) {
            $translation = $record->translate($locale);
            $label = $translation?->title ?? $translation?->name;

            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        return "#{$record->id}";
    }
}
