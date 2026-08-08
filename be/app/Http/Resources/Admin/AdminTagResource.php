<?php

namespace App\Http\Resources\Admin;

use App\Models\Tag;

/**
 * @mixin Tag
 */
class AdminTagResource extends AdminResource
{
    /**
     * @return array<string, mixed>
     */
    protected function baseAttributes(): array
    {
        return [
            'id' => $this->id,
            'posts_count' => $this->whenCounted('posts'),
        ];
    }
}
