<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\MediaResource;
use App\Models\Page;

/**
 * @mixin Page
 */
class AdminPageResource extends AdminResource
{
    /**
     * @return array<string, mixed>
     */
    protected function baseAttributes(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'cover_media_id' => $this->cover_media_id,
            'cover' => $this->cover ? MediaResource::make($this->cover) : null,
            'status' => $this->status->value,
        ];
    }
}
