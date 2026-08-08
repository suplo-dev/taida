<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MediaRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
            'alt' => ['sometimes', 'array'],
            'alt.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
