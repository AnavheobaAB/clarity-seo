<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Tenant Data Isolation', function () {
    it('users can only see data from their tenant', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $otherTenant = Tenant::factory()->create();

        Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'My Location']);
        Location::factory()->create(['tenant_id' => $otherTenant->id, 'name' => 'Other Location']);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/locations");

        $response->assertSuccessful();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['name' => 'My Location']);
        $response->assertJsonMissing(['name' => 'Other Location']);
    });

    it('returns 404 when accessing other tenant resources', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $otherTenant = Tenant::factory()->create();
        $otherLocation = Location::factory()->create(['tenant_id' => $otherTenant->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/locations/{$otherLocation->id}");

        $response->assertNotFound();
    });

    it('prevents creating resources for other tenants', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $otherTenant = Tenant::factory()->create();

        Sanctum::actingAs($user);

        // Try to create location in other tenant - should be forbidden
        $response = $this->postJson("/api/v1/tenants/{$otherTenant->id}/locations", [
            'name' => 'Malicious Location',
        ]);

        $response->assertForbidden();
    });

    it('prevents updating resources in other tenants', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $otherTenant = Tenant::factory()->create();
        $otherLocation = Location::factory()->create(['tenant_id' => $otherTenant->id]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/tenants/{$tenant->id}/locations/{$otherLocation->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertNotFound();
    });
});

describe('Tenant Switching', function () {
    it('allows users to switch between their tenants', function () {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $tenant2 = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant2->id}/switch");

        $response->assertSuccessful();
        $response->assertJson([
            'data' => [
                'current_tenant_id' => $tenant2->id,
            ],
        ]);
    });

    it('denies switching to non-member tenants', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $otherTenant = Tenant::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$otherTenant->id}/switch");

        $response->assertForbidden();
    });

    it('uses current tenant for API requests', function () {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $tenant2 = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();

        Location::factory()->create(['tenant_id' => $tenant1->id, 'name' => 'Location 1']);
        Location::factory()->create(['tenant_id' => $tenant2->id, 'name' => 'Location 2']);

        Sanctum::actingAs($user);

        // Access tenant 2's locations
        $response = $this->getJson("/api/v1/tenants/{$tenant2->id}/locations");

        $response->assertSuccessful();
        $response->assertJsonFragment(['name' => 'Location 2']);
        $response->assertJsonMissing(['name' => 'Location 1']);
    });
});

describe('Default Tenant', function () {
    it('users can list their tenants', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/tenants');

        $response->assertSuccessful();
        $response->assertJsonFragment([
            'id' => $tenant->id,
        ]);
    });

    it('users with no tenants see empty list', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/tenants');

        $response->assertSuccessful();
        $response->assertJsonCount(0, 'data');
    });
});
