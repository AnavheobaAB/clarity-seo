<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Listing\FacebookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacebookWebController extends Controller
{
    public function __construct(
        private readonly FacebookService $facebookService
    ) {}

    public function index(): View
    {
        // For testing purposes, we'll use the first tenant
        $tenant = Tenant::first();
        
        return view('facebook.index', [
            'tenant' => $tenant
        ]);
    }

    public function redirect(): RedirectResponse
    {
        // Simple state for security
        $state = base64_encode(json_encode(['nonce' => md5(uniqid())]));
        
        // This MUST match exactly what is in your Facebook App settings -> Facebook Login -> Settings -> Valid OAuth Redirect URIs
        // Using config('app.url') to ensure it matches the .env exactly
        $redirectUri = config('app.url') . '/facebook/callback';

        $url = $this->facebookService->getLoginUrl($redirectUri, $state);

        return redirect($url);
    }

    public function callback(Request $request): View|RedirectResponse
    {
        $code = $request->input('code');
        
        if (!$code) {
            return redirect()->route('facebook.index')->with('error', 'Authorization failed: No code returned.');
        }

        // Must match the redirect URI used in the redirect method
        $redirectUri = config('app.url') . '/facebook/callback';
        $accessToken = $this->facebookService->getAccessTokenFromCode($code, $redirectUri);

        if (!$accessToken) {
            return redirect()->route('facebook.index')->with('error', 'Failed to exchange code for access token.');
        }

        $pages = $this->facebookService->getPages($accessToken);

        if (!$pages) {
            return redirect()->route('facebook.index')->with('error', 'Failed to fetch Facebook pages.');
        }

        return view('facebook.pages', [
            'pages' => $pages,
            'userAccessToken' => $accessToken
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = Tenant::first(); // Using first tenant for this test UI
        
        $pageId = $request->input('page_id');
        $pageAccessToken = $request->input('page_access_token');
        $userAccessToken = $request->input('user_access_token');

        if (!$pageId || !$pageAccessToken) {
            return redirect()->route('facebook.index')->with('error', 'Invalid page selection.');
        }

        try {
            $credential = $this->facebookService->storeCredentials(
                $tenant,
                $userAccessToken,
                $pageId,
                $pageAccessToken
            );

            // Also update the location to link to this page
            $location = $tenant->locations()->first();
            if ($location) {
                $location->update(['facebook_page_id' => $pageId]);
            }

            return redirect()->route('facebook.index')->with('success', "Successfully connected page ID: {$pageId}");
        } catch (\Exception $e) {
            return redirect()->route('facebook.index')->with('error', 'Error saving credentials: ' . $e->getMessage());
        }
    }
}
