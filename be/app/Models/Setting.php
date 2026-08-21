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

    /**
     * Mạng xã hội hiện ở chân trang, theo đúng thứ tự này. Danh sách nằm ở đây
     * chứ không ở form: endpoint admin trả về đủ mọi mạng — kể cả mạng chưa có
     * địa chỉ — nên thêm một mạng mới chỉ phải sửa một chỗ.
     *
     * @var list<string>
     */
    public const SOCIAL_NETWORKS = ['linkedin', 'facebook', 'youtube', 'tiktok', 'lemon8'];

    /**
     * Settings whose value is a *list* of items, each pointing at a media id —
     * mã QR liên hệ (Zalo, WeChat...) là một danh sách chứ không phải một ảnh
     * duy nhất, nên chúng không vừa với MEDIA_KEYS.
     *
     * @var list<string>
     */
    public const MEDIA_LIST_KEYS = ['contactQr'];

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

    /**
     * Every social network in a single canonical shape, whether or not the
     * editor has filled it in.
     *
     * Trước khi có công tắc bật/tắt, mỗi mạng chỉ là một chuỗi địa chỉ. Hàng cũ
     * trong database vẫn nằm ở dạng đó, nên chỗ này quy nó về dạng mới lúc đọc —
     * rẻ hơn một migration, và cũng đỡ cho bất kỳ hàng nào bị sửa tay về sau.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, array{url: string, enabled: bool}>
     */
    public static function socialLinks(array $settings): array
    {
        $stored = is_array($settings['social'] ?? null) ? $settings['social'] : [];

        return collect(self::SOCIAL_NETWORKS)
            ->mapWithKeys(function (string $network) use ($stored): array {
                $value = $stored[$network] ?? null;
                $value = is_array($value)
                    ? $value
                    : ['url' => (string) ($value ?? ''), 'enabled' => filled($value)];

                return [$network => [
                    'url' => trim((string) ($value['url'] ?? '')),
                    'enabled' => (bool) ($value['enabled'] ?? false),
                ]];
            })
            ->all();
    }

    /**
     * Resolves every MEDIA_LIST_KEYS entry into its items with the media record
     * attached, in one query for all keys at once. An item whose media has since
     * been deleted is dropped rather than handed over half-empty: the image is
     * the whole point of the row.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, list<array{label: string, enabled: bool, media: Media}>>
     */
    public static function mediaListsFor(array $settings): array
    {
        $lists = [];
        $ids = [];

        foreach (self::MEDIA_LIST_KEYS as $key) {
            $items = is_array($settings[$key] ?? null) ? $settings[$key] : [];
            $lists[$key] = array_values(array_filter($items, 'is_array'));

            foreach ($lists[$key] as $item) {
                if (is_int($item['media'] ?? null)) {
                    $ids[] = $item['media'];
                }
            }
        }

        $media = $ids === [] ? collect() : Media::findMany($ids)->keyBy('id');

        return array_map(fn (array $items): array => collect($items)
            ->map(fn (array $item): array => [
                'label' => trim((string) ($item['label'] ?? '')),
                'enabled' => (bool) ($item['enabled'] ?? false),
                'media' => $media->get($item['media'] ?? null),
            ])
            ->filter(fn (array $item): bool => $item['media'] instanceof Media)
            ->values()
            ->all(), $lists);
    }
}
