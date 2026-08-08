<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\MediaResource;
use App\Models\Post;

/**
 * @mixin Post
 */
class AdminPostResource extends AdminResource
{
    /**
     * @return array<string, mixed>
     */
    protected function baseAttributes(): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'cover_media_id' => $this->cover_media_id,
            'cover' => $this->cover ? MediaResource::make($this->cover) : null,
            'author' => $this->author?->name,
            'is_featured' => $this->is_featured,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'tag_ids' => $this->whenLoaded('tags', fn () => $this->tags->pluck('id')),
        ];
    }
}
