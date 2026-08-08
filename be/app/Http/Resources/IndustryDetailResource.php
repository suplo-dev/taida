<?php

namespace App\Http\Resources;

use App\Models\Industry;
use Illuminate\Http\Request;

/**
 * @mixin Industry
 */
class IndustryDetailResource extends IndustryResource
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
            'parent' => $this->parent ? IndustryResource::make($this->parent) : null,
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'meta' => [
                'title' => $this->meta_title ?: $this->name,
                'description' => $this->meta_description ?: $this->excerpt,
            ],
        ]);
    }
}
