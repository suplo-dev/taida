<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\Publishable;
use App\Models\Concerns\RendersPublicOutput;
use App\Models\Concerns\RendersWhenPublished;
use App\Models\Concerns\SyncsPublicRelations;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model implements RendersPublicOutput
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasTranslations, Publishable, RendersWhenPublished, SyncsPublicRelations;

    /** @var list<string> */
    protected array $translatable = ['title', 'slug', 'excerpt', 'body', 'meta_title', 'meta_description'];

    protected $fillable = [
        'category_id', 'cover_media_id', 'author_id', 'is_featured', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
