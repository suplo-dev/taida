<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\SyncsTranslations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndustryRequest;
use App\Http\Requests\Admin\ReorderRequest;
use App\Http\Resources\Admin\AdminIndustryResource;
use App\Models\Industry;
use App\Support\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class IndustryController extends Controller
{
    use SyncsTranslations;

    public function index(Request $request): AnonymousResourceCollection
    {
        $industries = Industry::query()
            ->when($request->boolean('tree', true), fn ($query) => $query
                ->whereNull('parent_id')
                ->with(['children.translations', 'children.cover']))
            ->with(['translations', 'cover'])
            ->orderBy('sort_order')
            ->get();

        return AdminIndustryResource::collection($industries);
    }

    public function show(Industry $industry): AdminIndustryResource
    {
        return AdminIndustryResource::make(
            $industry->load(['translations', 'cover', 'services', 'children.translations'])
        );
    }

    public function store(IndustryRequest $request): JsonResponse
    {
        $industry = DB::transaction(function () use ($request): Industry {
            $industry = Industry::create($request->safe()->except(['translations', 'service_ids']));

            $this->syncTranslations($industry, $request->translations(), 'name');
            $industry->syncPublicRelation('services', $request->input('service_ids', []));

            return $industry;
        });

        return AdminIndustryResource::make($industry->load(['translations', 'cover', 'services']))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(IndustryRequest $request, Industry $industry): AdminIndustryResource
    {
        DB::transaction(function () use ($request, $industry): void {
            $industry->update($request->safe()->except(['translations', 'service_ids']));

            $this->syncTranslations($industry, $request->translations(), 'name');

            if ($request->has('service_ids')) {
                $industry->syncPublicRelation('services', $request->input('service_ids', []));
            }
        });

        return AdminIndustryResource::make(
            $industry->fresh(['translations', 'cover', 'services'])
        );
    }

    public function destroy(Industry $industry): JsonResponse
    {
        $industry->delete();

        return response()->json(status: JsonResponse::HTTP_NO_CONTENT);
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->positions() as $position) {
                Industry::whereKey($position['id'])->update([
                    'sort_order' => $position['sort_order'],
                    'parent_id' => $position['parent_id'] ?? null,
                ]);
            }
        });

        // Mass updates bypass model events, so the observer never fires here.
        ContentCache::flush();

        return response()->json(['message' => 'Reordered.']);
    }
}
