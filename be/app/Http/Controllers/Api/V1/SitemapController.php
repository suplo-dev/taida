<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Industry;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Support\ContentCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class SitemapController extends Controller
{
    /**
     * Feeds @nuxtjs/sitemap. Each entry carries the slug in every locale so
     * the frontend can emit one URL per language plus their hreflang links.
     */
    public function __invoke(): JsonResponse
    {
        $entries = ContentCache::remember('sitemap.urls', [], fn (): array => [
            ...$this->entries('service', Service::query()->published()->with('translations')->get()),
            ...$this->entries('industry', Industry::query()->published()->with('translations')->get()),
            ...$this->entries('post', Post::query()->published()->with('translations')->get()),
            ...$this->entries('page', Page::query()->where('status', ContentStatus::Published)->with('translations')->get()),
        ]);

        return response()->json(['data' => $entries]);
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return list<array{type: string, id: int, slugs: array<string, string>, updatedAt: string|null}>
     */
    private function entries(string $type, Collection $records): array
    {
        return $records->map(fn (Model $record): array => [
            'type' => $type,
            'id' => $record->id,
            'slugs' => $record->translations->pluck('slug', 'locale')->all(),
            'updatedAt' => $record->updated_at?->toIso8601String(),
        ])->all();
    }
}
