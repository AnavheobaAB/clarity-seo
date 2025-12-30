<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Http\Resources\Tenant\TenantResource;
use App\Models\Tenant;
use App\Services\Tenant\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenants = $request->user()->tenants;

        return TenantResource::collection($tenants);
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantService->create(
            $request->validated(),
            $request->user()
        );

        return (new TenantResource($tenant))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Tenant $tenant): TenantResource
    {
        $this->authorize('view', $tenant);

        return new TenantResource($tenant);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): TenantResource
    {
        $this->authorize('update', $tenant);

        $tenant = $this->tenantService->update($tenant, $request->validated());

        return new TenantResource($tenant);
    }

    public function destroy(Tenant $tenant): Response
    {
        $this->authorize('delete', $tenant);

        $this->tenantService->delete($tenant);

        return response()->noContent();
    }

    public function switch(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        $this->tenantService->switchTenant($request->user(), $tenant);

        return response()->json([
            'message' => 'Switched to tenant successfully.',
            'data' => [
                'current_tenant_id' => $tenant->id,
            ],
        ]);
    }
}
