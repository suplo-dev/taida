<?php

namespace App\Http\Requests\Admin;

class TagRequest extends TranslatableRequest
{
    protected function translationTable(): string
    {
        return 'tag_translations';
    }

    protected function translationForeignKey(): string
    {
        return 'tag_id';
    }

    protected function titleField(): string
    {
        return 'name';
    }

    protected function translationFields(): array
    {
        return [];
    }

    public function currentId(): ?int
    {
        return $this->route('tag')?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->translationRules();
    }
}
