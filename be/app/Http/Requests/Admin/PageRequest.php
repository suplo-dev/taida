<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentStatus;
use Illuminate\Validation\Rule;

class PageRequest extends TranslatableRequest
{
    protected function translationTable(): string
    {
        return 'page_translations';
    }

    protected function translationForeignKey(): string
    {
        return 'page_id';
    }

    protected function titleField(): string
    {
        return 'title';
    }

    protected function translationFields(): array
    {
        return [
            'body' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function currentId(): ?int
    {
        return $this->route('page')?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => [
                'required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('pages', 'key')->ignore($this->currentId()),
            ],
            'cover_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            ...$this->translationRules(),
        ];
    }
}
