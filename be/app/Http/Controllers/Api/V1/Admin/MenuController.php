<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\MenuLocation;
use App\Http\Controllers\Api\V1\Admin\Concerns\SyncsTranslations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuRequest;
use App\Http\Resources\Admin\AdminMenuItemResource;
use App\Models\MenuItem;
use App\Support\ContentCache;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    use SyncsTranslations;

    public function show(MenuLocation $location): AnonymousResourceCollection
    {
        $items = MenuItem::query()
            ->where('location', $location)
            ->whereNull('parent_id')
            ->with(['translations', 'children.translations'])
            ->orderBy('sort_order')
            ->get();

        return AdminMenuItemResource::collection($items);
    }

    /**
     * Replaces the menu for a location with the submitted tree. Rebuilding
     * wholesale keeps the drag-and-drop editor simple: the client sends the
     * final arrangement and never has to diff it.
     */
    public function update(MenuRequest $request, MenuLocation $location): AnonymousResourceCollection
    {
        DB::transaction(function () use ($request, $location): void {
            MenuItem::query()->where('location', $location)->delete();

            foreach ($request->input('items', []) as $order => $item) {
                $parent = $this->createItem($location, $item, $order, null);

                foreach ($item['children'] ?? [] as $childOrder => $child) {
                    $this->createItem($location, $child, $childOrder, $parent->id);
                }
            }
        });

        // The wholesale delete above bypasses model events, so an emptied menu
        // would otherwise keep serving its old items from cache.
        ContentCache::flush();

        return $this->show($location);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createItem(MenuLocation $location, array $data, int $order, ?int $parentId): MenuItem
    {
        $item = MenuItem::create([
            'location' => $location,
            'parent_id' => $parentId,
            'sort_order' => $order,
            'opens_in_new_tab' => (bool) ($data['opens_in_new_tab'] ?? false),
        ]);

        $this->syncTranslations($item, $data['translations'] ?? [], 'label', hasSlug: false);

        return $item;
    }
}
