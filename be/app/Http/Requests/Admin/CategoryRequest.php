<?php

namespace App\Http\Requests\Admin;

class CategoryRequest extends TranslatableRequest
{
    protected function translationTable(): string
    {
        return 'category_translations';
    }

    protected function translationForeignKey(): string
    {
        return 'category_id';
    }

    protected function titleField(): string
    {
        return 'name';
    }

    protected function translationFields(): array
    {
        return ['description' => ['nullable', 'string', 'max:1000']];
    }

    public function currentId(): ?int
    {
        return $this->route('category')?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            ...$this->translationRules(),
        ];
    }
}
