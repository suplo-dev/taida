<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class IndustryRequest extends TreeContentRequest
{
    protected function table(): string
    {
        return 'industries';
    }

    protected function routeParameter(): string
    {
        return 'industry';
    }

    protected function translationTable(): string
    {
        return 'industry_translations';
    }

    protected function translationForeignKey(): string
    {
        return 'industry_id';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => [Rule::exists('services', 'id')],
        ];
    }
}
