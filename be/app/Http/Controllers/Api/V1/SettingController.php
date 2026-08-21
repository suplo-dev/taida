<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Setting;
use App\Support\ContentCache;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    /**
     * Site-wide settings, already resolved for the active locale so the
     * frontend never has to know which values were authored per language.
     */
    public function index(): JsonResponse
    {
        $settings = ContentCache::remember('settings.index', [], function (): array {
            $settings = collect(Setting::map())
                ->map(fn (mixed $value) => $this->localise($value))
                ->all();

            return array_merge($settings, $this->media($settings), [
                'social' => $this->social($settings),
                'contactQr' => $this->contactQr($settings),
            ]);
        });

        return response()->json(['data' => $settings]);
    }

    /**
     * Expands the settings that hold a media id into the full media payload,
     * so the frontend can render the logo without a second round trip.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, array<string, mixed>|null>
     */
    private function media(array $settings): array
    {
        return array_map(
            fn (?Media $media): ?array => $media ? MediaResource::make($media)->resolve() : null,
            Setting::mediaFor($settings),
        );
    }

    /**
     * Chỉ những mạng đang bật và có địa chỉ mới ra tới site, và ra dưới dạng
     * `mạng => địa chỉ`: chân trang không cần biết gì về công tắc bật/tắt, nó
     * chỉ vẽ những gì được đưa.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    private function social(array $settings): array
    {
        return collect(Setting::socialLinks($settings))
            ->filter(fn (array $link): bool => $link['enabled'] && $link['url'] !== '')
            ->map(fn (array $link): string => $link['url'])
            ->all();
    }

    /**
     * Mã QR liên hệ đang bật, ảnh đã bung sẵn thành bản ghi media.
     *
     * @param  array<string, mixed>  $settings
     * @return list<array{label: string, media: array<string, mixed>}>
     */
    private function contactQr(array $settings): array
    {
        return collect(Setting::mediaListsFor($settings)['contactQr'])
            ->filter(fn (array $item): bool => $item['enabled'])
            ->map(fn (array $item): array => [
                'label' => $item['label'],
                'media' => MediaResource::make($item['media'])->resolve(),
            ])
            ->values()
            ->all();
    }

    /**
     * Picks the active locale out of a value keyed by locale, leaving values
     * that are not localised (a hotline, a URL) untouched.
     */
    private function localise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_intersect(array_keys($value), Locales::supported()) === []) {
            return $value;
        }

        foreach (Locales::chain() as $candidate) {
            if (isset($value[$candidate])) {
                return $value[$candidate];
            }
        }

        return null;
    }
}
