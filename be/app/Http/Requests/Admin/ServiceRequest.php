<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class ServiceRequest extends TreeContentRequest
{
    protected function table(): string
    {
        return 'services';
    }

    protected function routeParameter(): string
    {
        return 'service';
    }

    protected function translationTable(): string
    {
        return 'service_translations';
    }

    protected function translationForeignKey(): string
    {
        return 'service_id';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'industry_ids' => ['sometimes', 'array'],
            'industry_ids.*' => [Rule::exists('industries', 'id')],
        ];
    }
}
