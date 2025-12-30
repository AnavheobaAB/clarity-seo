<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Location;

use App\Http\Controllers\Controller;
use App\Http\Requests\Location\BulkDeleteLocationRequest;
use App\Http\Requests\Location\BulkImportLocationRequest;
use App\Http\Requests\Location\StoreLocationRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Http\Resources\Location\LocationResource;
use App\Models\Tenant;
use App\Services\Location\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class LocationController extends Controller
{
    public function __construct(
        private readonly LocationService $locationService,
    ) {}

    public function index(Request $request, Tenant $tenant): AnonymousResourceCollection
    {
        $this->authorize('view', $tenant);

        $locations = $this->locationService->list($tenant, $request->all());

        return LocationResource::collection($locations);
    }

    public function store(StoreLocationRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        $location = $this->locationService->create($tenant, $request->validated());

        return (new LocationResource($location))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Tenant $tenant, int $locationId): LocationResource|JsonResponse
    {
        $this->authorize('view', $tenant);

        $location = $this->locationService->findForTenant($tenant, $locationId);

        if (! $location) {
            return response()->json(['message' => 'Location not found.'], Response::HTTP_NOT_FOUND);
        }

        return new LocationResource($location);
    }

    public function update(UpdateLocationRequest $request, Tenant $tenant, int $locationId): LocationResource|JsonResponse
    {
        $this->authorize('update', $tenant);

        $location = $this->locationService->findForTenant($tenant, $locationId);

        if (! $location) {
            return response()->json(['message' => 'Location not found.'], Response::HTTP_NOT_FOUND);
        }

        $location = $this->locationService->update($location, $request->validated());

        return new LocationResource($location);
    }

    public function destroy(Tenant $tenant, int $locationId): Response|JsonResponse
    {
        $this->authorize('update', $tenant);

        $location = $this->locationService->findForTenant($tenant, $locationId);

        if (! $location) {
            return response()->json(['message' => 'Location not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->locationService->delete($location);

        return response()->noContent();
    }

    public function bulkImport(BulkImportLocationRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('update', $tenant);

        $count = $this->locationService->bulkImport($tenant, $request->validated('locations'));

        return response()->json([
            'message' => 'Locations imported successfully.',
            'data' => ['imported' => $count],
        ]);
    }

    public function bulkDelete(BulkDeleteLocationRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('update', $tenant);

        $count = $this->locationService->bulkDelete($tenant, $request->validated('ids'));

        return response()->json([
            'message' => 'Locations deleted successfully.',
            'data' => ['deleted' => $count],
        ]);
    }
}
