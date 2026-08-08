<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;

/**
 * @mixin Service
 */
class ServiceDetailResource extends ServiceResource
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
            'parent' => $this->parent ? ServiceResource::make($this->parent) : null,
            'industries' => IndustryResource::collection($this->whenLoaded('industries')),
            'meta' => [
                'title' => $this->meta_title ?: $this->name,
                'description' => $this->meta_description ?: $this->excerpt,
            ],
        ]);
    }
}
