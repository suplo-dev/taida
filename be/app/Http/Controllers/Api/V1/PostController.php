<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostDetailResource;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Support\ContentCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PostController extends Controller
{
    private const MAX_PER_PAGE = 48;

    private const RELATED_LIMIT = 3;

    public function index(Request $request): JsonResponse
    {
        $params = [
            'page' => max($request->integer('page', 1), 1),
            'perPage' => min(max($request->integer('per_page', 12), 1), self::MAX_PER_PAGE),
            'category' => $request->string('category')->toString() ?: null,
            'tag' => $request->string('tag')->toString() ?: null,
            'featured' => $request->boolean('featured') ?: null,
        ];

        $payload = ContentCache::remember('posts.index', $params, function () use ($params, $request): array {
            $posts = Post::query()
                ->published()
                ->when($params['category'], fn (Builder $query, string $slug) => $query
                    ->whereHas('category.translations', fn (Builder $translations) => $translations
                        ->where('locale', app()->getLocale())
                        ->where('slug', $slug),
                    ))
                ->when($params['tag'], fn (Builder $query, string $slug) => $query
                    ->whereHas('tags.translations', fn (Builder $translations) => $translations
                        ->where('locale', app()->getLocale())
                        ->where('slug', $slug),
                    ))
                ->when($params['featured'], fn (Builder $query) => $query->where('is_featured', true))
                ->with(['cover', 'category' => fn ($category) => $category->withTranslation()])
                ->withTranslation()
                ->latest('published_at')
                ->paginate($params['perPage'], page: $params['page'])
                ->withQueryString();

            return PostResource::collection($posts)->response($request)->getData(true);
        });

        return response()->json($payload);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $payload = ContentCache::remember('posts.show', ['slug' => $slug], function () use ($slug, $request): array {
            $post = Post::query()
                ->published()
                ->whereTranslatedSlug($slug)
                ->with([
                    'cover',
                    'author',
                    'category' => fn ($category) => $category->withTranslation(),
                    'tags' => fn ($tags) => $tags->withTranslation(),
                ])
                ->withAllTranslations()
                ->first();

            if ($post === null) {
                throw new NotFoundHttpException('Post not found.');
            }

            return PostDetailResource::make($post)->response($request)->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * Posts sharing the same category, excluding the one being read.
     */
    public function related(Request $request, string $slug): JsonResponse
    {
        $payload = ContentCache::remember('posts.related', ['slug' => $slug], function () use ($slug, $request): array {
            $post = Post::query()->published()->whereTranslatedSlug($slug)->first();

            if ($post === null) {
                throw new NotFoundHttpException('Post not found.');
            }

            $related = Post::query()
                ->published()
                ->whereKeyNot($post->id)
                ->when($post->category_id, fn (Builder $query, int $id) => $query->where('category_id', $id))
                ->with(['cover', 'category' => fn ($category) => $category->withTranslation()])
                ->withTranslation()
                ->latest('published_at')
                ->limit(self::RELATED_LIMIT)
                ->get();

            return PostResource::collection($related)->response($request)->getData(true);
        });

        return response()->json($payload);
    }
}
