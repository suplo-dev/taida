<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    /**
     * Raw settings, unresolved — the editor sees every locale, unlike the
     * public endpoint which collapses them to the active one.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->payload()]);
    }

    public function update(SettingRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->settings() as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        return response()->json(['data' => $this->payload()]);
    }

    /**
     * The stored map, with media ids expanded so the picker can show a
     * thumbnail. Writes still send the bare id back — see SettingRequest.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $settings = Setting::map();

        return array_merge($settings, array_map(
            fn (?Media $media): ?array => $media ? MediaResource::make($media)->resolve() : null,
            Setting::mediaFor($settings),
        ));
    }
}
