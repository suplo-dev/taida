<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RendersPublicOutput;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model implements RendersPublicOutput
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    protected array $translatable = ['name', 'slug', 'description'];

    protected $fillable = ['sort_order'];

    /**
     * Categories have no draft state: the blog's filter list carries every one
     * of them, including those without a post yet.
     */
    public function isPubliclyVisible(): bool
    {
        return true;
    }

    /**
     * `sort_order` decides the order the filters appear in. The names live on
     * the translation rows.
     *
     * @return list<string>
     */
    public function publiclyRenderedAttributes(): array
    {
        return ['sort_order'];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
