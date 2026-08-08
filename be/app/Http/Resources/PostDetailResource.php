<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;

/**
 * @mixin Post
 */
class PostDetailResource extends PostResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            // Lets the language switcher build this record's URL in the other
            // locale; the slugs differ, so reusing the current one 404s.
            'slugs' => $this->translatedSlugs(),
            'body' => $this->body,
            'author' => $this->author ? ['name' => $this->author->name] : null,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'meta' => [
                'title' => $this->meta_title ?: $this->title,
                'description' => $this->meta_description ?: $this->excerpt,
            ],
        ]);
    }
}
