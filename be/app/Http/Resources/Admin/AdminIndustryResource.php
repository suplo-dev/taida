<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\MediaResource;
use App\Models\Industry;

/**
 * @mixin Industry
 */
class AdminIndustryResource extends AdminResource
{
    /**
     * @return array<string, mixed>
     */
    protected function baseAttributes(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'cover_media_id' => $this->cover_media_id,
            'cover' => $this->cover ? MediaResource::make($this->cover) : null,
            'icon' => $this->icon,
            'sort_order' => $this->sort_order,
            'is_featured' => $this->is_featured,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'service_ids' => $this->whenLoaded('services', fn () => $this->services->pluck('id')),
            'children' => static::collection($this->whenLoaded('children')),
        ];
    }
}
