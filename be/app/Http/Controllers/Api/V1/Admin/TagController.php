<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\SyncsTranslations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TagRequest;
use App\Http\Resources\Admin\AdminTagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class TagController extends Controller
{
    use SyncsTranslations;

    public function index(): AnonymousResourceCollection
    {
        return AdminTagResource::collection(
            Tag::query()->withCount('posts')->with('translations')->get()
        );
    }

    public function store(TagRequest $request): JsonResponse
    {
        $tag = DB::transaction(function () use ($request): Tag {
            $tag = Tag::create();
            $this->syncTranslations($tag, $request->translations(), 'name');

            return $tag;
        });

        return AdminTagResource::make($tag->load('translations'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(TagRequest $request, Tag $tag): AdminTagResource
    {
        $this->syncTranslations($tag, $request->translations(), 'name');

        return AdminTagResource::make($tag->fresh('translations'));
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();

        return response()->json(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
