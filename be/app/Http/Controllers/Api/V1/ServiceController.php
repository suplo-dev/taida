<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceDetailResource;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Support\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ServiceController extends Controller
{
    /**
     * The service tree: the top-level pillars, each with its children.
     */
    public function index(Request $request): JsonResponse
    {
        $flat = $request->boolean('flat');

        $payload = ContentCache::remember('services.index', ['flat' => $flat], function () use ($flat, $request): array {
            $services = Service::query()
                ->published()
                ->when(! $flat, fn ($query) => $query->roots()->with([
                    'children' => fn ($children) => $children->published()->withTranslation()->with('cover'),
                ]))
                ->with('cover')
                ->withTranslation()
                ->orderBy('sort_order')
                ->get();

            return ServiceResource::collection($services)->response($request)->getData(true);
        });

        return response()->json($payload);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $payload = ContentCache::remember('services.show', ['slug' => $slug], function () use ($slug, $request): array {
            $service = Service::query()
                ->published()
                ->whereTranslatedSlug($slug)
                ->with([
                    'cover',
                    'parent' => fn ($parent) => $parent->withTranslation(),
                    'children' => fn ($children) => $children->published()->withTranslation()->with('cover'),
                    'industries' => fn ($industries) => $industries->published()->withTranslation()->with('cover'),
                ])
                ->withAllTranslations()
                ->first();

            if ($service === null) {
                throw new NotFoundHttpException('Service not found.');
            }

            return ServiceDetailResource::make($service)->response($request)->getData(true);
        });

        return response()->json($payload);
    }
}
