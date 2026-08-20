<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Support\ContentCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends Controller
{
    /**
     * Resolves a static page by its localised slug, or by its stable key so
     * the frontend can link to "about-us" without knowing either slug.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $payload = ContentCache::remember('pages.show', ['slug' => $slug], function () use ($slug, $request): array {
            $page = Page::query()
                ->where('status', ContentStatus::Published)
                // `whereTranslatedSlug` chứ không phải một `whereHas` riêng: nó mang theo
                // luật rơi về locale chính cho bản ghi chưa dịch (xem HasTranslations).
                // Thiếu nó thì /zh/chinh-sach-bao-mat — địa chỉ mà chính menu tiếng Trung
                // trỏ tới khi trang chưa được dịch — trả 404 và làm hỏng cả bản build.
                ->where(fn (Builder $query) => $query
                    ->where('key', $slug)
                    ->orWhere(fn (Builder $translated) => $translated->whereTranslatedSlug($slug)))
                ->with('cover')
                ->withAllTranslations()
                ->first();

            if ($page === null) {
                throw new NotFoundHttpException('Page not found.');
            }

            return PageResource::make($page)->response($request)->getData(true);
        });

        return response()->json($payload);
    }
}
