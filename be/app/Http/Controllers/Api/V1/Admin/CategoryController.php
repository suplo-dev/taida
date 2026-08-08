<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\SyncsTranslations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\Admin\AdminCategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    use SyncsTranslations;

    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->withCount('posts')
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        return AdminCategoryResource::collection($categories);
    }

    public function show(Category $category): AdminCategoryResource
    {
        return AdminCategoryResource::make($category->load('translations'));
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = DB::transaction(function () use ($request): Category {
            $category = Category::create($request->safe()->except('translations'));
            $this->syncTranslations($category, $request->translations(), 'name');

            return $category;
        });

        return AdminCategoryResource::make($category->load('translations'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(CategoryRequest $request, Category $category): AdminCategoryResource
    {
        DB::transaction(function () use ($request, $category): void {
            $category->update($request->safe()->except('translations'));
            $this->syncTranslations($category, $request->translations(), 'name');
        });

        return AdminCategoryResource::make($category->fresh('translations'));
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
