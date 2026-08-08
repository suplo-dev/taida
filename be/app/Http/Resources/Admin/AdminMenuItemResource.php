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
            'children' => static::collection($this->whenLoaded('children')),
        ];
    }
}
