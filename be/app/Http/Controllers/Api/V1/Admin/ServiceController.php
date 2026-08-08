<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\SyncsTranslations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderRequest;
use App\Http\Requests\Admin\ServiceRequest;
use App\Http\Resources\Admin\AdminServiceResource;
use App\Models\Service;
use App\Support\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    use SyncsTranslations;

    public function index(Request $request): AnonymousResourceCollection
    {
        $services = Service::query()
            ->when($request->boolean('tree', true), fn ($query) => $query
                ->whereNull('parent_id')
                ->with(['children.translations', 'children.cover']))
            ->with(['translations', 'cover'])
            ->orderBy('sort_order')
            ->get();

        return AdminServiceResource::collection($services);
    }

    public function show(Service $service): AdminServiceResource
    {
        return AdminServiceResource::make(
            $service->load(['translations', 'cover', 'industries', 'children.translations'])
        );
    }

    public function store(ServiceRequest $request): JsonResponse
    {
        $service = DB::transaction(function () use ($request): Service {
            $service = Service::create($request->safe()->except(['translations', 'industry_ids']));

            $this->syncTranslations($service, $request->translations(), 'name');
            $service->industries()->sync($request->input('industry_ids', []));

            return $service;
        });

        return AdminServiceResource::make($service->load(['translations', 'cover', 'industries']))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(ServiceRequest $request, Service $service): AdminServiceResource
    {
        DB::transaction(function () use ($request, $service): void {
            $service->update($request->safe()->except(['translations', 'industry_ids']));

            $this->syncTranslations($service, $request->translations(), 'name');

            if ($request->has('industry_ids')) {
                $service->industries()->sync($request->input('industry_ids', []));
            }
        });

        return AdminServiceResource::make(
            $service->fresh(['translations', 'cover', 'industries'])
        );
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json(status: JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Persists the order produced by dragging rows in the admin table.
     */
    public function reorder(ReorderRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            foreach ($request->positions() as $position) {
                Service::whereKey($position['id'])->update([
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
