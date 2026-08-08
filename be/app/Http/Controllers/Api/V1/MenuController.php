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

            return MenuItemResource::collection($items)->response($request)->getData(true);
        });

        return response()->json($payload);
    }
}
