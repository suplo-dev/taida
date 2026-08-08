<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\IndustryDetailResource;
use App\Http\Resources\IndustryResource;
use App\Models\Industry;
use App\Support\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IndustryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $flat = $request->boolean('flat');

        $payload = ContentCache::remember('industries.index', ['flat' => $flat], function () use ($flat, $request): array {
            $industries = Industry::query()
                ->published()
                ->when(! $flat, fn ($query) => $query->roots()->with([
                    'children' => fn ($children) => $children->published()->withTranslation()->with('cover'),
                ]))
                ->with('cover')
                ->withTranslation()
                ->orderBy('sort_order')
                ->get();

            return IndustryResource::collection($industries)->response($request)->getData(true);
        });

        return response()->json($payload);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $payload = ContentCache::remember('industries.show', ['slug' => $slug], function () use ($slug, $request): array {
            $industry = Industry::query()
                ->published()
                ->whereTranslatedSlug($slug)
                ->with([
                    'cover',
                    'parent' => fn ($parent) => $parent->withTranslation(),
                    'children' => fn ($children) => $children->published()->withTranslation()->with('cover'),
                    'services' => fn ($services) => $services->published()->withTranslation()->with('cover'),
                ])
                ->withAllTranslations()
                ->first();

            if ($industry === null) {
                throw new NotFoundHttpException('Industry not found.');
            }

            return IndustryDetailResource::make($industry)->response($request)->getData(true);
        });

        return response()->json($payload);
    }
}
