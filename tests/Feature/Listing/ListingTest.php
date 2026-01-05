<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\Location;
use App\Models\PlatformCredential;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Listing Retrieval', function () {
    it('lists all listings for a tenant', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);
        Listing::factory()->facebook()->create(['location_id' => $location->id]);
        Listing::factory()->google()->create(['location_id' => $location->id]);
        Listing::factory()->bing()->create(['location_id' => $location->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/listings");

        $response->assertSuccessful();
        $response->assertJsonCount(3, 'data');
    });

    it('does not list listings from other tenants', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $otherTenant = Tenant::factory()->create();

        $location = Location::factory()->create(['tenant_id' => $tenant->id]);
        $otherLocation = Location::factory()->create(['tenant_id' => $otherTenant->id]);

        Listing::factory()->facebook()->create(['location_id' => $location->id]);
        Listing::factory()->google()->create(['location_id' => $location->id]);
        Listing::factory()->facebook()->create(['location_id' => $otherLocation->id]);
        Listing::factory()->google()->create(['location_id' => $otherLocation->id]);
        Listing::factory()->bing()->create(['location_id' => $otherLocation->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/listings");

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'data');
    });

    it('returns listing details', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);
        $listing = Listing::factory()->facebook()->create([
            'location_id' => $location->id,
            'name' => 'Test Business Page',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/listings/{$listing->id}");

        $response->assertSuccessful();
        $response->assertJsonFragment(['name' => 'Test Business Page']);
        $response->assertJsonFragment(['platform' => 'facebook']);
    });

    it('requires authentication to list listings', function () {
        $tenant = Tenant::factory()->create();

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/listings");

        $response->assertUnauthorized();
    });

    it('requires tenant membership to list listings', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(); // User not a member

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/listings");

        $response->assertForbidden();
    });

    it('filters listings by platform', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location1 = Location::factory()->create(['tenant_id' => $tenant->id]);
        $location2 = Location::factory()->create(['tenant_id' => $tenant->id]);

        Listing::factory()->facebook()->create(['location_id' => $location1->id]);
        Listing::factory()->facebook()->create(['location_id' => $location2->id]);
        Listing::factory()->google()->create(['location_id' => $location1->id]);
        Listing::factory()->google()->create(['location_id' => $location2->id]);
        Listing::factory()->bing()->create(['location_id' => $location1->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/listings?platform=facebook");

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'data');
    });

    it('filters listings by status', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        Listing::factory()->facebook()->create(['location_id' => $location->id, 'status' => 'synced']);
        Listing::factory()->google()->create(['location_id' => $location->id, 'status' => 'synced']);
        Listing::factory()->bing()->withError()->create(['location_id' => $location->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/listings?status=error");

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
    });
});

describe('Listing Stats', function () {
    it('returns listing statistics for a tenant', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location1 = Location::factory()->create(['tenant_id' => $tenant->id]);
        $location2 = Location::factory()->create(['tenant_id' => $tenant->id]);

        Listing::factory()->facebook()->create(['location_id' => $location1->id]);
        Listing::factory()->google()->create(['location_id' => $location1->id]);
        Listing::factory()->facebook()->create(['location_id' => $location2->id]);
        Listing::factory()->bing()->withDiscrepancies()->create(['location_id' => $location2->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/listings/stats");

        $response->assertSuccessful();
        $response->assertJsonPath('data.total_listings', 4);
        $response->assertJsonPath('data.with_discrepancies', 1);
    });

    it('returns location-specific listing stats', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location1 = Location::factory()->create(['tenant_id' => $tenant->id]);
        $location2 = Location::factory()->create(['tenant_id' => $tenant->id]);

        Listing::factory()->facebook()->create(['location_id' => $location1->id]);
        Listing::factory()->google()->create(['location_id' => $location1->id]);
        Listing::factory()->bing()->create(['location_id' => $location1->id]);
        Listing::factory()->facebook()->create(['location_id' => $location2->id]);
        Listing::factory()->google()->create(['location_id' => $location2->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/locations/{$location1->id}/listings/stats");

        $response->assertSuccessful();
        $response->assertJsonPath('data.total_listings', 3);
    });
});

describe('Platform Credentials', function () {
    it('stores facebook credentials', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/listings/credentials", [
            'platform' => 'facebook',
            'access_token' => 'test_access_token_12345',
            'page_id' => '123456789',
        ]);

        $response->assertCreated();
        $response->assertJsonFragment(['platform' => 'facebook']);
        $response->assertJsonPath('data.page_id', '123456789');

        $this->assertDatabaseHas('platform_credentials', [
            'tenant_id' => $tenant->id,
            'platform' => 'facebook',
        ]);
    });

    it('requires admin role to store credentials', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/listings/credentials", [
            'platform' => 'facebook',
            'access_token' => 'test_token',
            'page_id' => '123456789',
        ]);

        $response->assertForbidden();
    });

    it('validates required fields for credentials', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/listings/credentials", [
            'platform' => 'facebook',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['access_token', 'page_id']);
    });

    it('validates platform value', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/listings/credentials", [
            'platform' => 'invalid_platform',
            'access_token' => 'test_token',
            'page_id' => '123456789',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['platform']);
    });

    it('returns available platforms', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        PlatformCredential::factory()->facebook()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/listings/platforms");

        $response->assertSuccessful();
        $response->assertJsonPath('data.facebook.connected', true);
        $response->assertJsonPath('data.google.connected', false);
    });

    it('disconnects platform credentials', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        PlatformCredential::factory()->facebook()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/tenants/{$tenant->id}/listings/credentials/facebook");

        $response->assertNoContent();

        $this->assertDatabaseHas('platform_credentials', [
            'tenant_id' => $tenant->id,
            'platform' => 'facebook',
            'is_active' => false,
        ]);
    });
});

describe('Listing Sync', function () {
    it('requires credentials to sync', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations/{$location->id}/listings/sync/facebook");

        $response->assertUnprocessable();
        $response->assertJsonFragment(['message' => 'Failed to sync listing. Please check platform credentials.']);
    });

    it('requires admin role to sync', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations/{$location->id}/listings/sync/facebook");

        $response->assertForbidden();
    });

    it('returns 404 for location in different tenant', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $otherTenant = Tenant::factory()->create();
        $otherLocation = Location::factory()->create(['tenant_id' => $otherTenant->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations/{$otherLocation->id}/listings/sync/facebook");

        $response->assertNotFound();
    });
});
