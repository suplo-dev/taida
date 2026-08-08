<?php

namespace App\Http\Resources;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Page
 */
class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'slug' => $this->slug,
            // Lets the language switcher build this page's URL in the other
            // locale; the slugs differ, so reusing the current one 404s.
            'slugs' => $this->translatedSlugs(),
            'title' => $this->title,
            'body' => $this->body,
            'cover' => $this->cover ? MediaResource::make($this->cover) : null,
            'meta' => [
                'title' => $this->meta_title ?: $this->title,
                'description' => $this->meta_description,
            ],
        ];
    }
}
