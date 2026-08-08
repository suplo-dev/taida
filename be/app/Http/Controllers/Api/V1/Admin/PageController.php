<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\SyncsTranslations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Http\Resources\Admin\AdminPageResource;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PageController extends Controller
{
    use SyncsTranslations;

    public function index(): AnonymousResourceCollection
    {
        return AdminPageResource::collection(
            Page::query()->with(['translations', 'cover'])->orderBy('key')->get()
        );
    }

    public function show(Page $page): AdminPageResource
    {
        return AdminPageResource::make($page->load(['translations', 'cover']));
    }

    public function store(PageRequest $request): JsonResponse
    {
        $page = DB::transaction(function () use ($request): Page {
            $page = Page::create($request->safe()->except('translations'));
            $this->syncTranslations($page, $request->translations(), 'title');

            return $page;
        });

        return AdminPageResource::make($page->load(['translations', 'cover']))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(PageRequest $request, Page $page): AdminPageResource
    {
        DB::transaction(function () use ($request, $page): void {
            $page->update($request->safe()->except('translations'));
            $this->syncTranslations($page, $request->translations(), 'title');
        });

        return AdminPageResource::make($page->fresh(['translations', 'cover']));
    }

    public function destroy(Page $page): JsonResponse
    {
        $page->delete();

        return response()->json(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
