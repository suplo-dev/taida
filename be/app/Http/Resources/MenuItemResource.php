<?php

namespace App\Http\Resources;

use App\Enums\MenuTarget;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MenuItem
 */
class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'target' => $this->target(),
            'opensInNewTab' => $this->opens_in_new_tab,
            'children' => static::collection($this->whenLoaded('children')),
        ];
    }

    /**
     * Where this item goes, named rather than spelled out.
     *
     * The API resolves the slug — it is the side that knows the record and the
     * fallback chain — and the frontend turns that into a path, because it is
     * the side that knows /dich-vu from /en/services. Neither has to hold a
     * copy of the other's map, which is what used to drift.
     *
     * @return array<string, string>|null
     */
    private function target(): ?array
    {
        $type = $this->target_type;

        if ($type === null) {
            return null;
        }

        if ($type === MenuTarget::External) {
            return $this->external_url === null
                ? null
                : ['type' => $type->value, 'url' => $this->external_url];
        }

        if ($type === MenuTarget::Route) {
            return $this->target_route === null
                ? null
                : ['type' => $type->value, 'route' => $this->target_route->value];
        }

        $record = $this->resource->target();

        // Bản ghi đã bị xoá, hoặc chuyển về nháp: `MenuController` loại mục này
        // ra khỏi menu thay vì để nó thành link gãy.
        if ($record === null || $record->slug === null) {
            return null;
        }

        return ['type' => $type->value, 'slug' => $record->slug];
    }
}
