<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RendersPublicOutput;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model implements RendersPublicOutput
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    protected array $translatable = ['name', 'slug'];

    protected $fillable = [];

    /**
     * Tags have no draft state.
     */
    public function isPubliclyVisible(): bool
    {
        return true;
    }

    /**
     * The row itself carries nothing but an id — name and slug are translated,
     * so a tag only ever changes through TagTranslation.
     *
     * @return list<string>
     */
    public function publiclyRenderedAttributes(): array
    {
        return [];
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }
}
