<?php

namespace App\Models;

use App\Models\Concerns\RendersPublicOutput;
use App\Models\Concerns\RendersWithParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostTranslation extends Model implements RendersPublicOutput
{
    use RendersWithParent;

    protected $fillable = [
        'locale', 'title', 'slug', 'excerpt', 'body', 'meta_title', 'meta_description',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
