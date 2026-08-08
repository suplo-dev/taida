<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentStatus;
use Illuminate\Validation\Rule;

class PostRequest extends TranslatableRequest
{
    protected function translationTable(): string
    {
        return 'post_translations';
    }

    protected function translationForeignKey(): string
    {
        return 'post_id';
    }

    protected function titleField(): string
    {
        return 'title';
    }

    protected function translationFields(): array
    {
        return [
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function currentId(): ?int
    {
        return $this->route('post')?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'cover_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'is_featured' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'published_at' => ['nullable', 'date'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => [Rule::exists('tags', 'id')],
            ...$this->translationRules(),
        ];
    }
}
