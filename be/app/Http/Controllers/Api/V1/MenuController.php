<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MenuLocation;
use App\Http\Controllers\Controller;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use App\Support\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function show(Request $request, MenuLocation $location): JsonResponse
    {
        $payload = ContentCache::remember('menus.show', ['location' => $location->value], function () use ($location, $request): array {
            $items = MenuItem::query()
                ->where('location', $location)
                ->whereNull('parent_id')
                ->with(['children' => fn ($children) => $children->withTranslation()])
                ->withTranslation()
                ->orderBy('sort_order')
                ->get();

            MenuItem::loadTargets($items);

            $payload = MenuItemResource::collection($items)->response($request)->getData(true);

            $payload['data'] = static::withoutDeadLinks($payload['data']);

            return $payload;
        });

        return response()->json($payload);
    }

    /**
     * Drops items that have nowhere to go.
     *
     * A menu item is left without a destination whenever the record it pointed
     * at is deleted or moved back to draft, and by the migration that could not
     * work out what an old hand-typed URL meant. Rendering it as a link would
     * put a 404 in the navigation of every page — and the static build crawls
     * that navigation, so it would fail the publish outright. The admin shows
     * these items so an editor can finish them; the site simply leaves them out
     * until then.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function withoutDeadLinks(array $items): array
    {
        $alive = [];

        foreach ($items as $item) {
            if ($item['target'] === null) {
                continue;
            }

            $item['children'] = static::withoutDeadLinks($item['children'] ?? []);
            $alive[] = $item;
        }

        return $alive;
    }
}
