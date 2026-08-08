<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'isFeatured' => $this->is_featured,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'cover' => $this->cover ? MediaResource::make($this->cover) : null,
            'category' => $this->category ? CategoryResource::make($this->category) : null,
        ];
    }
}
