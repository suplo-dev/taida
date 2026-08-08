<?php

namespace App\Http\Resources\Admin;

use App\Models\Category;

/**
 * @mixin Category
 */
class AdminCategoryResource extends AdminResource
{
    /**
     * @return array<string, mixed>
     */
    protected function baseAttributes(): array
    {
        return [
            'id' => $this->id,
            'sort_order' => $this->sort_order,
            'posts_count' => $this->whenCounted('posts'),
        ];
    }
}
