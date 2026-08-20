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
                ->tap(fn (Builder $query) => $this->searchable($query, $term, ['name', 'excerpt']))
                ->with('cover')
                ->withTranslation()
                ->limit(self::LIMIT_PER_TYPE)
                ->get();

            $industries = Industry::query()
                ->published()
                ->tap(fn (Builder $query) => $this->searchable($query, $term, ['name', 'excerpt']))
                ->with('cover')
                ->withTranslation()
                ->limit(self::LIMIT_PER_TYPE)
                ->get();

            $posts = Post::query()
                ->published()
                ->tap(fn (Builder $query) => $this->searchable($query, $term, ['title', 'excerpt']))
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
     * Restricts a model query to records matching the term in the active locale.
     *
     * A record with NOTHING in that locale is matched on its primary-locale text
     * instead — the same rule the rest of the site runs on: such a record is
     * listed and readable under /zh in Vietnamese, so a search that cannot find
     * it makes the search box look broken rather than the translation look
     * missing. Records that DO have the locale are matched only on it, so
     * searching in English never surfaces a hit on Vietnamese text the reader
     * will not see.
     *
     * @param  list<string>  $columns
     */
    private function searchable(Builder $query, string $term, array $columns): void
    {
        $locale = app()->getLocale();
        $primary = config('app.supported_locales')[0];

        $query->where(function (Builder $query) use ($term, $columns, $locale, $primary): void {
            $query->whereHas('translations', $this->matches($term, $columns, $locale));

            if ($locale === $primary) {
                return;
            }

            $query->orWhere(fn (Builder $untranslated) => $untranslated
                ->whereHas('translations', $this->matches($term, $columns, $primary))
                ->whereDoesntHave('translations', fn (Builder $translations) => $translations
                    ->where('locale', $locale),
                ),
            );
        });
    }

    /**
     * Constrains a translations relation to one locale and matches the term
     * against any of the given columns.
     *
     * @param  list<string>  $columns
     * @return \Closure(Builder): void
     */
    private function matches(string $term, array $columns, string $locale): \Closure
    {
        return function (Builder $translations) use ($term, $columns, $locale): void {
            $translations->where('locale', $locale)
                ->where(function (Builder $query) use ($term, $columns): void {
                    foreach ($columns as $column) {
                        $query->orWhere($column, 'like', '%'.$term.'%');
                    }
                });
        };
    }
}
