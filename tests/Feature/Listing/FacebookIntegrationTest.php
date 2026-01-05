<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\PlatformCredential;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Listing\FacebookService;
use Laravel\Sanctum\Sanctum;

/**
 * Integration tests for Facebook Graph API.
 * These tests make real API calls to Facebook.
 *
 * To run: php artisan test tests/Feature/Listing/FacebookIntegrationTest.php
 */
describe('Facebook API Integration', function () {
    beforeEach(function () {
        $this->token = env('FACEBOOK_TEST_ACCESS_TOKEN');
        $this->pageId = env('FACEBOOK_TEST_PAGE_ID', '702344112952665');

        if (empty($this->token)) {
            $this->markTestSkipped('FACEBOOK_TEST_ACCESS_TOKEN not set in .env');
        }
    });

    it('can fetch user pages from Facebook', function () {
        $service = app(FacebookService::class);
        $pages = $service->getPages($this->token);

        expect($pages)->toBeArray();

        if (count($pages) > 0) {
            $page = $pages[0];
            expect($page)->toHaveKeys(['id', 'name']);
        }
    });

    it('can fetch page details from Facebook', function () {
        $service = app(FacebookService::class);
        $pageDetails = $service->getPageDetails($this->pageId, $this->token);

        expect($pageDetails)->toBeArray()
            ->and($pageDetails)->toHaveKey('id')
            ->and($pageDetails['id'])->toBe($this->pageId);
    });

    it('syncs listing from Facebook page', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        // Create credential with real token
        PlatformCredential::factory()->create([
            'tenant_id' => $tenant->id,
            'platform' => 'facebook',
            'access_token' => $this->token,
            'metadata' => ['page_id' => $this->pageId],
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations/{$location->id}/listings/sync/facebook");

        // The response should either succeed or fail with a known error
        if ($response->status() === 201 || $response->status() === 200) {
            $response->assertSuccessful();
            expect($response->json('data.platform'))->toBe('facebook');
        } else {
            // If failed, it should be due to API issues, not authentication
            $response->assertUnprocessable();
            expect($response->json('message'))->toContain('sync');
        }
    });

    it('stores credentials and validates connection', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/listings/credentials", [
            'platform' => 'facebook',
            'access_token' => $this->token,
            'page_id' => $this->pageId,
        ]);

        $response->assertCreated();
        expect($response->json('data.platform'))->toBe('facebook')
            ->and($response->json('data.page_id'))->toBe($this->pageId);

        // Verify it was stored
        $this->assertDatabaseHas('platform_credentials', [
            'tenant_id' => $tenant->id,
            'platform' => 'facebook',
            'is_active' => true,
        ]);
    });

    it('gets available platforms with connected status', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();

        // Create credential with real token
        PlatformCredential::factory()->create([
            'tenant_id' => $tenant->id,
            'platform' => 'facebook',
            'access_token' => $this->token,
            'metadata' => ['page_id' => $this->pageId],
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/listings/platforms");

        $response->assertSuccessful();
        expect($response->json('data.facebook.connected'))->toBeTrue()
            ->and($response->json('data.google.connected'))->toBeFalse();
    });
});

describe('Facebook Service Direct Tests', function () {
    beforeEach(function () {
        $this->token = env('FACEBOOK_TEST_ACCESS_TOKEN');
        $this->pageId = env('FACEBOOK_TEST_PAGE_ID', '702344112952665');

        if (empty($this->token)) {
            $this->markTestSkipped('FACEBOOK_TEST_ACCESS_TOKEN not set in .env');
        }
    });

    it('handles invalid token gracefully', function () {
        $service = app(FacebookService::class);
        $result = $service->getPages('invalid_token');

        // Service returns null for invalid tokens rather than throwing
        expect($result)->toBeNull();
    });

    it('returns page data structure with expected fields', function () {
        $service = app(FacebookService::class);
        $pageDetails = $service->getPageDetails($this->pageId, $this->token);

        // These are the fields we request from Facebook
        expect($pageDetails)->toHaveKey('id');

        // Optional fields that may or may not be present
        $possibleFields = ['name', 'phone', 'website', 'single_line_address', 'hours', 'about', 'category'];
        $hasAtLeastOne = false;
        foreach ($possibleFields as $field) {
            if (isset($pageDetails[$field])) {
                $hasAtLeastOne = true;
                break;
            }
        }

        expect($hasAtLeastOne)->toBeTrue('Page should have at least one optional field');
    });
});
