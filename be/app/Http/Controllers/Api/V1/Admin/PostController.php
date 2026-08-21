<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\SyncsTranslations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostRequest;
use App\Http\Resources\Admin\AdminPostResource;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    use SyncsTranslations;

    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = Post::query()
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('category_id'), fn (Builder $query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('q'), fn (Builder $query) => $query
                ->whereHas('translations', fn (Builder $translations) => $translations
                    ->where('title', 'like', '%'.$request->string('q').'%')))
            ->with(['translations', 'cover', 'author'])
            ->latest('published_at')
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return AdminPostResource::collection($posts);
    }

    public function show(Post $post): AdminPostResource
    {
        return AdminPostResource::make($post->load(['translations', 'cover', 'author', 'tags']));
    }

    public function store(PostRequest $request): JsonResponse
    {
        $post = DB::transaction(function () use ($request): Post {
            $post = Post::create([
                ...$request->safe()->except(['translations', 'tag_ids']),
                'author_id' => $request->user()->id,
            ]);

            $this->syncTranslations($post, $request->translations(), 'title');
            $post->syncPublicRelation('tags', $request->input('tag_ids', []));

            return $post;
        });

        return AdminPostResource::make($post->load(['translations', 'cover', 'author', 'tags']))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(PostRequest $request, Post $post): AdminPostResource
    {
        DB::transaction(function () use ($request, $post): void {
            $post->update($request->safe()->except(['translations', 'tag_ids']));

            $this->syncTranslations($post, $request->translations(), 'title');

            if ($request->has('tag_ids')) {
                $post->syncPublicRelation('tags', $request->input('tag_ids', []));
            }
        });

        return AdminPostResource::make($post->fresh(['translations', 'cover', 'author', 'tags']));
    }

    public function destroy(Post $post): JsonResponse
    {
        $post->delete();

        return response()->json(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
