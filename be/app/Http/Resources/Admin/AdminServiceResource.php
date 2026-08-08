<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\MediaResource;
use App\Models\Service;

/**
 * @mixin Service
 */
class AdminServiceResource extends AdminResource
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
            'industry_ids' => $this->whenLoaded('industries', fn () => $this->industries->pluck('id')),
            'children' => static::collection($this->whenLoaded('children')),
        ];
    }
}
