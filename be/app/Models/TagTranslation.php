<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagTranslation extends Model
{
    protected $fillable = ['locale', 'name', 'slug'];

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }
}
