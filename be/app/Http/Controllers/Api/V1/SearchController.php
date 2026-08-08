<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\IndustryResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\ServiceResource;
use App\Models\Industry;
use App\Models\Post;
use App\Models\Service;
use App\Support\ContentCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const LIMIT_PER_TYPE = 8;

    /**
     * Searches services, industries and posts in the active locale and
     * returns them grouped by type.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < 2) {
            return response()->json([
                'data' => ['services' => [], 'industries' => [], 'posts' => []],
            ]);
        }

        $payload = ContentCache::remember('search', ['q' => mb_strtolower($term)], function () use ($term, $request): array {
            $services = Service::query()
                ->published()
                ->whereHas('translations', $this->matches($term, ['name', 'excerpt']))
                ->with('cover')
                ->withTranslation()
                ->limit(self::LIMIT_PER_TYPE)
                ->get();

            $industries = Industry::query()
                ->published()
                ->whereHas('translations', $this->matches($term, ['name', 'excerpt']))
                ->with('cover')
                ->withTranslation()
                ->limit(self::LIMIT_PER_TYPE)
                ->get();

            $posts = Post::query()
                ->published()
                ->whereHas('translations', $this->matches($term, ['title', 'excerpt']))
                ->with(['cover', 'category' => fn ($category) => $category->withTranslation()])
                ->withTranslation()
                ->latest('published_at')
                ->limit(self::LIMIT_PER_TYPE)
                ->get();

            return ['data' => [
                'services' => ServiceResource::collection($services)->resolve($request),
                'industries' => IndustryResource::collection($industries)->resolve($request),
                'posts' => PostResource::collection($posts)->resolve($request),
            ]];
        });

        return response()->json($payload);
    }

    /**
     * Constrains a translations relation to the active locale and matches the
     * term against any of the given columns.
     *
     * @param  list<string>  $columns
     * @return \Closure(Builder): void
     */
    private function matches(string $term, array $columns): \Closure
    {
        return function (Builder $translations) use ($term, $columns): void {
            $translations->where('locale', app()->getLocale())
                ->where(function (Builder $query) use ($term, $columns): void {
                    foreach ($columns as $column) {
                        $query->orWhere($column, 'like', '%'.$term.'%');
                    }
                });
        };
    }
}
