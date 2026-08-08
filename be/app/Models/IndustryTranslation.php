<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndustryTranslation extends Model
{
    protected $fillable = [
        'locale', 'name', 'slug', 'excerpt', 'body', 'meta_title', 'meta_description',
    ];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }
}
