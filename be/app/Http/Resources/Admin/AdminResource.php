<?php

namespace App\Http\Resources\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin responses carry every locale at once, unlike the public API which
 * resolves a single one, because the editor edits them side by side.
 */
abstract class AdminResource extends JsonResource
{
    /**
     * Fields that live on the base record.
     *
     * @return array<string, mixed>
     */
    abstract protected function baseAttributes(): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->baseAttributes(),
            'translations' => $this->whenLoaded('translations', fn () => $this->resource->translations
                ->keyBy('locale')
                ->map(fn (Model $translation) => collect($translation->getAttributes())
                    ->except(['id', 'locale', 'created_at', 'updated_at', $this->foreignKey()])
                    ->all())
                ->all()),
        ];
    }

    private function foreignKey(): string
    {
        return $this->resource->translations()->getForeignKeyName();
    }
}
