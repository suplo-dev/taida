<?php

namespace App\Models;

use App\Models\Concerns\RendersPublicOutput;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model implements RendersPublicOutput
{
    /**
     * Settings whose value is a media id rather than a literal. The id is
     * stored instead of a URL so the address is rebuilt from the current disk
     * configuration — a URL frozen into the row would break the day the site
     * moves domain.
     *
     * @var list<string>
     */
    public const MEDIA_KEYS = ['logo', 'heroImage'];

    /**
     * Địa chỉ video nền của hero. Là URL chứ không phải media id: file mp4 vài
     * chục MB không thuộc về thư viện ảnh — MediaRequest chặn ở 8 MB — và shared
     * hosting phát video thẳng thì vừa tốn băng thông vừa không có range request
     * tử tế. Trỏ sang CDN hoặc nơi đang lưu sẵn.
     *
     * `heroImage` vẫn được dùng làm ảnh poster của video, nên đặt cả hai là hợp lệ.
     */
    public const URL_KEYS = ['heroVideo'];

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    /**
     * Settings feed the header, footer and SEO defaults of every page.
     */
    public function isPubliclyVisible(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public function publiclyRenderedAttributes(): array
    {
        return ['key', 'value'];
    }

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /**
     * All settings as a flat key => value map.
     *
     * @return array<string, mixed>
     */
    public static function map(): array
    {
        return static::query()->pluck('value', 'key')->all();
    }

    /**
     * Resolves every MEDIA_KEYS entry to the record it points at, in one
     * query. A key that is unset — or that points at a since-deleted record —
     * comes back as null so callers render their own default rather than a
     * broken image.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, Media|null>
     */
    public static function mediaFor(array $settings): array
    {
        $ids = [];

        foreach (self::MEDIA_KEYS as $key) {
            if (is_int($settings[$key] ?? null)) {
                $ids[$key] = $settings[$key];
            }
        }

        $media = $ids === [] ? collect() : Media::findMany($ids)->keyBy('id');

        return collect(self::MEDIA_KEYS)
            ->mapWithKeys(fn (string $key): array => [$key => $media->get($ids[$key] ?? null)])
            ->all();
    }
}
