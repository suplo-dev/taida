<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Publishable;
use App\Models\Concerns\RendersPublicOutput;
use App\Models\Concerns\RendersWhenPublished;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model implements RendersPublicOutput
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasTranslations, Publishable, RendersWhenPublished;

    /** @var list<string> */
    protected array $translatable = ['title', 'slug', 'body', 'meta_title', 'meta_description'];

    protected $fillable = ['key', 'cover_media_id', 'status'];

    protected function casts(): array
    {
        return ['status' => ContentStatus::class];
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }
}
