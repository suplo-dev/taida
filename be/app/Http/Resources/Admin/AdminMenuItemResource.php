<?php

namespace App\Http\Resources\Admin;

use App\Models\MenuItem;

/**
 * @mixin MenuItem
 */
class AdminMenuItemResource extends AdminResource
{
    /**
     * @return array<string, mixed>
     */
    protected function baseAttributes(): array
    {
        return [
            'id' => $this->id,
            'sort_order' => $this->sort_order,
            'opens_in_new_tab' => $this->opens_in_new_tab,
            'target_type' => $this->target_type,
            'target_route' => $this->target_route,
            'target_id' => $this->target_id,
            'external_url' => $this->external_url,
            'children' => static::collection($this->whenLoaded('children')),
        ];
    }
}
