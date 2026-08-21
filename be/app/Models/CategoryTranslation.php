<?php

namespace App\Models;

use App\Models\Concerns\RendersPublicOutput;
use App\Models\Concerns\RendersWithParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryTranslation extends Model implements RendersPublicOutput
{
    use RendersWithParent;

    protected $fillable = ['locale', 'name', 'slug', 'description'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
