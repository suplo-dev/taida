<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\ContentCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $payload = ContentCache::remember('categories.index', [], function () use ($request): array {
            $categories = Category::query()
                ->withCount(['posts' => fn (Builder $posts) => $posts->published()])
                ->withTranslation()
                ->orderBy('sort_order')
                ->get();

            return CategoryResource::collection($categories)->response($request)->getData(true);
        });

        return response()->json($payload);
    }
}
