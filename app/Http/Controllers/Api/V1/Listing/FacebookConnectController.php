<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Listing;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Listing\FacebookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FacebookConnectController extends Controller
{
    public function __construct(
        private readonly FacebookService $facebookService,
    ) {}

    /**
     * Get the Facebook Login URL.
     */
    public function connect(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('update', $tenant);

        // State can be used to pass the tenant ID or other context safely
        $state = base64_encode(json_encode(['tenant_id' => $tenant->id]));
        
        // This should match the callback route in your API
        // NOTE: In production, this should be a frontend URL that calls the API
        $redirectUri = route('api.v1.facebook.callback'); 

        $url = $this->facebookService->getLoginUrl($redirectUri, $state);

        return response()->json([
            'url' => $url,
        ]);
    }

    /**
     * Handle the OAuth callback.
     */
    public function callback(Request $request): JsonResponse
    {
        $code = $request->input('code');
        $state = $request->input('state');
        
        if (!$code) {
             return response()->json(['message' => 'Authorization code missing.'], Response::HTTP_BAD_REQUEST);
        }

        // Decode state to get tenant context
        $stateData = json_decode(base64_decode($state), true);
        $tenantId = $stateData['tenant_id'] ?? null;

        if (!$tenantId) {
             return response()->json(['message' => 'Invalid state.'], Response::HTTP_BAD_REQUEST);
        }

        // In a real app, you might want to verify the user has access to this tenant here
        // For now, we proceed to get the token

        $redirectUri = route('api.v1.facebook.callback');
        $accessToken = $this->facebookService->getAccessTokenFromCode($code, $redirectUri);

        if (!$accessToken) {
             return response()->json(['message' => 'Failed to get access token.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Fetch pages to show to the user
        $pages = $this->facebookService->getPages($accessToken);

        if (!$pages) {
            return response()->json(['message' => 'Failed to fetch pages.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Connected successfully. Please select a page.',
            'access_token' => $accessToken,
            'pages' => $pages,
            'tenant_id' => $tenantId,
        ]);
    }
}
