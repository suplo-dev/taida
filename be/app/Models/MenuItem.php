<?php

namespace App\Models;

use App\Enums\MenuLocation;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RendersPublicOutput;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model implements RendersPublicOutput
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    protected array $translatable = ['label', 'url'];

    protected $fillable = ['location', 'parent_id', 'sort_order', 'opens_in_new_tab'];

    /**
     * Navigation is on every page of the site, always.
     */
    public function isPubliclyVisible(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public function publiclyRenderedAttributes(): array
    {
        return ['location', 'parent_id', 'sort_order', 'opens_in_new_tab'];
    }

    protected function casts(): array
    {
        return [
            'location' => MenuLocation::class,
            'opens_in_new_tab' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
