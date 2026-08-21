<?php

namespace App\Http\Requests\Admin;

use App\Enums\MenuTarget;
use App\Enums\SiteRoute;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        ];

        $primary = Locales::primary();

        foreach (['items.*', 'items.*.children.*'] as $level) {
            $rules["{$level}.opens_in_new_tab"] = ['sometimes', 'boolean'];
            $rules["{$level}.children"] = ['sometimes', 'array'];

            $rules += $this->targetRules($level);

            foreach (Locales::supported() as $locale) {
                $rules["{$level}.translations.{$locale}.label"] = [
                    $locale === $primary ? 'required' : 'nullable', 'string', 'max:255',
                ];
            }
        }

        return $rules;
    }

    /**
     * Rules for one item's destination.
     *
     * `target_type` is always required, but the field carrying the destination
     * is only required for its own type — and a menu item is allowed to have no
     * destination at all. That is not sloppiness: an editor adding a row types
     * the label first, and the migration off hand-typed URLs left the entries
     * it could not interpret without one. Such an item is kept, flagged in the
     * admin, and left out of the site (see the public MenuController) rather
     * than rendered as a link to nowhere.
     *
     * @return array<string, mixed>
     */
    private function targetRules(string $level): array
    {
        return [
            "{$level}.target_type" => ['required', Rule::enum(MenuTarget::class)],
            "{$level}.target_route" => ['nullable', Rule::enum(SiteRoute::class)],
            "{$level}.target_id" => ['nullable', 'integer'],
            "{$level}.external_url" => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    /**
     * Checks the destination points at a record that exists, once the shape is
     * known to be valid.
     *
     * Done here rather than with `exists:` in the rules above because which
     * table to look in depends on `target_type`, which is a sibling field.
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('items', []) as $index => $item) {
                $this->validateTarget($validator, "items.{$index}", $item);

                foreach ($item['children'] ?? [] as $childIndex => $child) {
                    $this->validateTarget($validator, "items.{$index}.children.{$childIndex}", $child);
                }
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function validateTarget(Validator $validator, string $key, array $item): void
    {
        $type = MenuTarget::tryFrom($item['target_type'] ?? '');
        $id = $item['target_id'] ?? null;

        if ($type === null || ! $type->isContent() || $id === null) {
            return;
        }

        /** @var class-string<Model> $model */
        $model = $type->model();

        if (! $model::query()->whereKey($id)->exists()) {
            $validator->errors()->add("{$key}.target_id", 'Nội dung được chọn không còn tồn tại.');
        }
    }
}
