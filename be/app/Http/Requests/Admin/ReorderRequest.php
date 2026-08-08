<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'positions' => ['required', 'array'],
            'positions.*.id' => ['required', 'integer'],
            'positions.*.sort_order' => ['required', 'integer', 'min:0'],
            'positions.*.parent_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return list<array{id: int, sort_order: int, parent_id: int|null}>
     */
    public function positions(): array
    {
        return $this->validated('positions');
    }
}
