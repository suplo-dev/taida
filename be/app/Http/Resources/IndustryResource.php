<?php

namespace App\Http\Resources;

use App\Models\Industry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Industry
 */
class IndustryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'excerpt' => $this->excerpt,
            'icon' => $this->icon,
            'isFeatured' => $this->is_featured,
            'cover' => $this->cover ? MediaResource::make($this->cover) : null,
            'children' => static::collection($this->whenLoaded('children')),
        ];
    }
}
