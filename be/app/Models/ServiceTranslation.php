<?php

namespace App\Models;

use App\Models\Concerns\RendersPublicOutput;
use App\Models\Concerns\RendersWithParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceTranslation extends Model implements RendersPublicOutput
{
    use RendersWithParent;

    protected $fillable = [
        'locale', 'name', 'slug', 'excerpt', 'body', 'meta_title', 'meta_description',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
