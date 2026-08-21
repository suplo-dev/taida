<?php

namespace App\Models;

use App\Models\Concerns\RendersPublicOutput;
use App\Support\Locales;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model implements RendersPublicOutput
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'disk', 'path', 'thumb_path', 'original_name', 'mime', 'size', 'width', 'height', 'alt', 'uploaded_by',
    ];

    /**
     * Models that can use a picture as their cover. A file nobody has attached
     * yet renders nowhere — which is the common case, because editors upload
     * images while a draft is still being written.
     *
     * @var list<class-string<Model>>
     */
    private const COVER_OWNERS = [Service::class, Industry::class, Post::class, Page::class];

    /**
     * Visible only through whatever is using it, so an upload on its own costs
     * nothing and the build happens when the picture is actually attached.
     *
     * Asked through `isPubliclyVisible()` on the owner rather than a `published`
     * scope, because Page publishes by status alone — it has no date column.
     */
    public function isPubliclyVisible(): bool
    {
        foreach (self::COVER_OWNERS as $owner) {
            $used = $owner::query()
                ->where('cover_media_id', $this->getKey())
                ->get()
                ->contains(fn (RendersPublicOutput $record): bool => $record->isPubliclyVisible());

            if ($used) {
                return true;
            }
        }

        return false;
    }

    /**
     * What a cached response embeds: where the file is served from, its alt
     * text, and the dimensions used to reserve layout space. `original_name`,
     * `mime`, `size` and `uploaded_by` are bookkeeping.
     *
     * @return list<string>
     */
    public function publiclyRenderedAttributes(): array
    {
        return ['disk', 'path', 'thumb_path', 'alt', 'width', 'height'];
    }

    protected function casts(): array
    {
        return ['alt' => 'array'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk($this->disk)->url($this->path));
    }

    protected function thumbUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->thumb_path
            ? Storage::disk($this->disk)->url($this->thumb_path)
            : null,
        );
    }

    /** Alternative text for a locale, falling back down its chain. */
    public function altFor(?string $locale = null): ?string
    {
        foreach (Locales::chain($locale) as $candidate) {
            if (isset($this->alt[$candidate])) {
                return $this->alt[$candidate];
            }
        }

        return null;
    }
}
