<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentStatus;
use Illuminate\Validation\Rule;

/**
 * Shared rules for the two hierarchical, cross-linked catalogues: services
 * and industries. They carry identical fields and differ only in their tables.
 */
abstract class TreeContentRequest extends TranslatableRequest
{
    /** Base table, e.g. `services`. */
    abstract protected function table(): string;

    /** Route parameter holding the record being updated, e.g. `service`. */
    abstract protected function routeParameter(): string;

    protected function titleField(): string
    {
        return 'name';
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
        return $this->route($this->routeParameter())?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A record may not be its own parent, which would orphan the tree.
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists($this->table(), 'id')->when(
                    (bool) $this->currentId(),
                    fn ($rule) => $rule->whereNot('id', $this->currentId()),
                ),
            ],
            'cover_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_featured' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'published_at' => ['nullable', 'date'],
            ...$this->translationRules(),
        ];
    }
}
