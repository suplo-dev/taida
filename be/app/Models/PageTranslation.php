<?php

namespace App\Models;

use App\Models\Concerns\RendersPublicOutput;
use App\Models\Concerns\RendersWithParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageTranslation extends Model implements RendersPublicOutput
{
    use RendersWithParent;

    protected $fillable = ['locale', 'title', 'slug', 'body', 'meta_title', 'meta_description'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
