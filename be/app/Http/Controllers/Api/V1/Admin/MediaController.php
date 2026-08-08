<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class MediaController extends Controller
{
    private const THUMB_WIDTH = 400;

    public function index(Request $request): AnonymousResourceCollection
    {
        $media = Media::query()
            ->latest('id')
            ->paginate($request->integer('per_page', 30));

        return MediaResource::collection($media);
    }

    /**
     * Stores an upload and derives a thumbnail used by the media picker and
     * by listing cards on the public site.
     */
    public function store(MediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $name = Str::random(20).'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs('media', $name, 'public');

        $image = (new ImageManager(new Driver))->decodePath($file->getRealPath());
        $width = $image->width();
        $height = $image->height();

        $thumbPath = 'media/thumbs/'.$name;
        Storage::disk('public')->put(
            $thumbPath,
            (string) $image->scaleDown(width: self::THUMB_WIDTH)->encodeUsingMediaType($file->getMimeType()),
        );

        $media = Media::create([
            'disk' => 'public',
            'path' => $path,
            'thumb_path' => $thumbPath,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt' => $request->input('alt'),
            'uploaded_by' => $request->user()->id,
        ]);

        return MediaResource::make($media)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function destroy(Media $medium): JsonResponse
    {
        Storage::disk($medium->disk)->delete(array_filter([$medium->path, $medium->thumb_path]));

        $medium->delete();

        return response()->json(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
