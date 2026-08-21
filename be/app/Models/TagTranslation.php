<?php

namespace App\Models;

use App\Models\Concerns\RendersPublicOutput;
use App\Models\Concerns\RendersWithParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagTranslation extends Model implements RendersPublicOutput
{
    use RendersWithParent;

    protected $fillable = ['locale', 'name', 'slug'];

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }
}
