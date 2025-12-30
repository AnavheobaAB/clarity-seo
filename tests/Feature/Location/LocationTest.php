<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Location Creation', function () {
    it('allows tenant members to create locations', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations", [
            'name' => 'Downtown Store',
            'address' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'US',
            'phone' => '+1-555-123-4567',
            'website' => 'https://example.com',
        ]);

        $response->assertCreated();
        $response->assertJsonFragment([
            'name' => 'Downtown Store',
            'city' => 'New York',
        ]);
    });

    it('requires authentication to create locations', function () {
        $tenant = Tenant::factory()->create();

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations", [
            'name' => 'Test Location',
        ]);

        $response->assertUnauthorized();
    });

    it('requires tenant membership to create locations', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(); // User not a member

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations", [
            'name' => 'Test Location',
        ]);

        $response->assertForbidden();
    });

    it('requires a location name', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations", [
            'address' => '123 Main St',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    });

    it('validates phone format', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations", [
            'name' => 'Test Location',
            'phone' => 'not-a-phone',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['phone']);
    });

    it('validates website format', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations", [
            'name' => 'Test Location',
            'website' => 'not-a-url',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['website']);
    });

    it('stores coordinates when provided', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations", [
            'name' => 'Geo Location',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.latitude', '40.7128000');
        $response->assertJsonPath('data.longitude', '-74.0060000');
    });
});

describe('Location Retrieval', function () {
    it('lists all locations for a tenant', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        Location::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/locations");

        $response->assertSuccessful();
        $response->assertJsonCount(3, 'data');
    });

    it('does not list locations from other tenants', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $otherTenant = Tenant::factory()->create();

        Location::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        Location::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/locations");

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'data');
    });

    it('returns location details', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location = Location::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Store',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/locations/{$location->id}");

        $response->assertSuccessful();
        $response->assertJsonFragment(['name' => 'Test Store']);
    });

    it('returns 404 for location in different tenant', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $otherTenant = Tenant::factory()->create();
        $otherLocation = Location::factory()->create(['tenant_id' => $otherTenant->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/locations/{$otherLocation->id}");

        $response->assertNotFound();
    });

    it('supports pagination', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        Location::factory()->count(25)->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/locations?per_page=10");

        $response->assertSuccessful();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 25);
    });

    it('supports search by name', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Downtown Store']);
        Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Uptown Store']);
        Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Mall Kiosk']);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/locations?search=store");

        $response->assertSuccessful();
        $response->assertJsonCount(2, 'data');
    });
});

describe('Location Update', function () {
    it('allows admins to update locations', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/tenants/{$tenant->id}/locations/{$location->id}", [
            'name' => 'Updated Store Name',
        ]);

        $response->assertSuccessful();
        $response->assertJsonFragment(['name' => 'Updated Store Name']);
    });

    it('allows owners to update locations', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'owner'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/tenants/{$tenant->id}/locations/{$location->id}", [
            'name' => 'Owner Updated',
        ]);

        $response->assertSuccessful();
    });

    it('denies members from updating locations', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/tenants/{$tenant->id}/locations/{$location->id}", [
            'name' => 'Should Not Work',
        ]);

        $response->assertForbidden();
    });

    it('prevents updating locations in other tenants', function () {
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

describe('Location Deletion', function () {
    it('allows admins to delete locations', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/tenants/{$tenant->id}/locations/{$location->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    });

    it('denies members from deleting locations', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/tenants/{$tenant->id}/locations/{$location->id}");

        $response->assertForbidden();
    });
});

describe('Business Hours', function () {
    it('stores business hours when creating location', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations", [
            'name' => 'Store with Hours',
            'business_hours' => [
                'monday' => ['open' => '09:00', 'close' => '17:00'],
                'tuesday' => ['open' => '09:00', 'close' => '17:00'],
                'wednesday' => ['open' => '09:00', 'close' => '17:00'],
                'thursday' => ['open' => '09:00', 'close' => '17:00'],
                'friday' => ['open' => '09:00', 'close' => '17:00'],
                'saturday' => ['open' => '10:00', 'close' => '14:00'],
                'sunday' => null,
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.business_hours.monday.open', '09:00');
    });

    it('allows updating business hours', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/tenants/{$tenant->id}/locations/{$location->id}", [
            'business_hours' => [
                'monday' => ['open' => '08:00', 'close' => '20:00'],
            ],
        ]);

        $response->assertSuccessful();
    });
});

describe('Location Categories', function () {
    it('stores categories with location', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations", [
            'name' => 'Categorized Store',
            'primary_category' => 'Restaurant',
            'categories' => ['Restaurant', 'Bar', 'Nightclub'],
        ]);

        $response->assertCreated();
        $response->assertJsonFragment(['primary_category' => 'Restaurant']);
    });
});

describe('Location Status', function () {
    it('creates locations as active by default', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations", [
            'name' => 'Active Store',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'active');
    });

    it('allows setting location as inactive', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/tenants/{$tenant->id}/locations/{$location->id}", [
            'status' => 'inactive',
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('data.status', 'inactive');
    });

    it('filters locations by status', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        Location::factory()->count(3)->create(['tenant_id' => $tenant->id, 'status' => 'active']);
        Location::factory()->count(2)->create(['tenant_id' => $tenant->id, 'status' => 'inactive']);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/locations?status=active");

        $response->assertSuccessful();
        $response->assertJsonCount(3, 'data');
    });
});

describe('Bulk Operations', function () {
    it('allows bulk import of locations', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations/bulk", [
            'locations' => [
                ['name' => 'Location 1', 'city' => 'New York'],
                ['name' => 'Location 2', 'city' => 'Los Angeles'],
                ['name' => 'Location 3', 'city' => 'Chicago'],
            ],
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('data.imported', 3);
    });

    it('returns validation errors for invalid bulk data', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations/bulk", [
            'locations' => [
                ['name' => 'Valid Location'],
                ['city' => 'Missing Name'], // Invalid - no name
            ],
        ]);

        $response->assertUnprocessable();
    });

    it('allows bulk delete of locations', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $locations = Location::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/tenants/{$tenant->id}/locations/bulk", [
            'ids' => $locations->pluck('id')->toArray(),
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('data.deleted', 3);
    });
});
