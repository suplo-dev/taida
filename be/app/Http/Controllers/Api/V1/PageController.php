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
                ->where(fn (Builder $query) => $query
                    ->where('key', $slug)
                    ->orWhereHas('translations', fn (Builder $translations) => $translations
                        ->where('locale', app()->getLocale())
                        ->where('slug', $slug),
                    ))
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
