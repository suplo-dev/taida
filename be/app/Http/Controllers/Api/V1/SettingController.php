<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Setting;
use App\Support\ContentCache;
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

            return array_merge($settings, $this->media($settings));
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
     * Picks the active locale out of a value keyed by locale, leaving values
     * that are not localised (a hotline, a URL) untouched.
     */
    private function localise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $locales = config('app.supported_locales');

        if (array_intersect(array_keys($value), $locales) === []) {
            return $value;
        }

        return $value[app()->getLocale()] ?? $value[$locales[0]] ?? null;
    }
}
