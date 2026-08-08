<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The whole menu for one location is saved in a single request: the editor
 * drags items around and submits the resulting tree, which replaces what was
 * stored before.
 */
class MenuRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'items' => ['present', 'array'],
            'items.*.opens_in_new_tab' => ['sometimes', 'boolean'],
            'items.*.children' => ['sometimes', 'array'],
            'items.*.children.*.opens_in_new_tab' => ['sometimes', 'boolean'],
        ];

        $primary = config('app.supported_locales')[0];

        foreach (config('app.supported_locales') as $locale) {
            $required = $locale === $primary ? 'required' : 'nullable';

            foreach (['items.*', 'items.*.children.*'] as $level) {
                $rules["{$level}.translations.{$locale}.label"] = [$required, 'string', 'max:255'];
                $rules["{$level}.translations.{$locale}.url"] = ['nullable', 'string', 'max:2048'];
            }
        }

        return $rules;
    }
}
