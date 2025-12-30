<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\MemberResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MemberController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
    ) {}

    public function index(Tenant $tenant): AnonymousResourceCollection
    {
        $this->authorize('view', $tenant);

        return MemberResource::collection($tenant->users);
    }

    public function destroy(Request $request, Tenant $tenant, User $member): Response|JsonResponse
    {
        $this->authorize('removeMember', [$tenant, $member]);

        // Check if trying to remove the only owner
        if ($tenant->isOwner($member) && $tenant->owners()->count() === 1) {
            return response()->json([
                'message' => 'Cannot remove the only owner.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->tenantService->removeMember($tenant, $member);

        return response()->noContent();
    }

    public function update(Request $request, Tenant $tenant, User $member): JsonResponse
    {
        $newRole = $request->validate(['role' => 'required|in:member,admin,owner'])['role'];

        $this->authorize('updateMemberRole', [$tenant, $newRole]);

        $this->tenantService->updateMemberRole($tenant, $member, $newRole);

        return response()->json([
            'message' => 'Member role updated successfully.',
        ]);
    }

    public function leave(Request $request, Tenant $tenant): Response|JsonResponse
    {
        $user = $request->user();

        // Check if trying to leave as the only owner
        if ($tenant->isOwner($user) && $tenant->owners()->count() === 1) {
            return response()->json([
                'message' => 'Cannot remove the only owner.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->tenantService->leaveTenant($tenant, $user);

        return response()->json([
            'message' => 'Successfully left the organization.',
        ]);
    }
}
