<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'disk', 'path', 'thumb_path', 'original_name', 'mime', 'size', 'width', 'height', 'alt', 'uploaded_by',
    ];

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

    /** Alternative text for a locale, falling back to the primary locale. */
    public function altFor(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $primary = config('app.supported_locales')[0];

        return $this->alt[$locale] ?? $this->alt[$primary] ?? null;
    }
}
