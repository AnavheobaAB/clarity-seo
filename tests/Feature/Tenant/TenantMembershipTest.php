<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

describe('Invite Members', function () {
    it('allows owners to invite members', function () {
        Notification::fake();

        $owner = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($owner, ['role' => 'owner'])->create();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/invitations", [
            'email' => 'newmember@example.com',
            'role' => 'member',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tenant_invitations', [
            'tenant_id' => $tenant->id,
            'email' => 'newmember@example.com',
            'role' => 'member',
        ]);
    });

    it('allows admins to invite members', function () {
        Notification::fake();

        $admin = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($admin, ['role' => 'admin'])->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/invitations", [
            'email' => 'newmember@example.com',
            'role' => 'member',
        ]);

        $response->assertCreated();
    });

    it('denies members from inviting others', function () {
        $member = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($member, ['role' => 'member'])->create();
        Sanctum::actingAs($member);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/invitations", [
            'email' => 'newmember@example.com',
            'role' => 'member',
        ]);

        $response->assertForbidden();
    });

    it('prevents inviting existing members', function () {
        $owner = User::factory()->create();
        $existingMember = User::factory()->create(['email' => 'existing@example.com']);
        $tenant = Tenant::factory()
            ->hasAttached($owner, ['role' => 'owner'])
            ->hasAttached($existingMember, ['role' => 'member'])
            ->create();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/invitations", [
            'email' => 'existing@example.com',
            'role' => 'member',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
    });

    it('prevents duplicate pending invitations', function () {
        Notification::fake();

        $owner = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($owner, ['role' => 'owner'])->create();
        TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'pending@example.com',
        ]);
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/invitations", [
            'email' => 'pending@example.com',
            'role' => 'member',
        ]);

        $response->assertUnprocessable();
    });

    it('admins cannot invite owners', function () {
        $admin = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($admin, ['role' => 'admin'])->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/invitations", [
            'email' => 'newowner@example.com',
            'role' => 'owner',
        ]);

        $response->assertForbidden();
    });
});

describe('Accept Invitation', function () {
    it('allows users to accept invitations', function () {
        $user = User::factory()->create(['email' => 'invited@example.com']);
        $tenant = Tenant::factory()->create();
        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'invited@example.com',
            'role' => 'member',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/invitations/{$invitation->token}/accept");

        $response->assertSuccessful();
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
        $this->assertDatabaseMissing('tenant_invitations', ['id' => $invitation->id]);
    });

    it('rejects expired invitations', function () {
        $user = User::factory()->create(['email' => 'invited@example.com']);
        $tenant = Tenant::factory()->create();
        $invitation = TenantInvitation::factory()->expired()->create([
            'tenant_id' => $tenant->id,
            'email' => 'invited@example.com',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/invitations/{$invitation->token}/accept");

        $response->assertUnprocessable();
        $response->assertJson(['message' => 'Invitation has expired.']);
    });

    it('rejects invitation for wrong email', function () {
        $user = User::factory()->create(['email' => 'different@example.com']);
        $tenant = Tenant::factory()->create();
        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'invited@example.com',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/invitations/{$invitation->token}/accept");

        $response->assertForbidden();
    });
});

describe('Remove Members', function () {
    it('allows owners to remove members', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $tenant = Tenant::factory()
            ->hasAttached($owner, ['role' => 'owner'])
            ->hasAttached($member, ['role' => 'member'])
            ->create();
        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/v1/tenants/{$tenant->id}/members/{$member->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
        ]);
    });

    it('allows admins to remove members', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $tenant = Tenant::factory()
            ->hasAttached($owner, ['role' => 'owner'])
            ->hasAttached($admin, ['role' => 'admin'])
            ->hasAttached($member, ['role' => 'member'])
            ->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/tenants/{$tenant->id}/members/{$member->id}");

        $response->assertNoContent();
    });

    it('denies admins from removing owners', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $tenant = Tenant::factory()
            ->hasAttached($owner, ['role' => 'owner'])
            ->hasAttached($admin, ['role' => 'admin'])
            ->create();
        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/tenants/{$tenant->id}/members/{$owner->id}");

        $response->assertForbidden();
    });

    it('denies admins from removing other admins', function () {
        $admin1 = User::factory()->create();
        $admin2 = User::factory()->create();
        $tenant = Tenant::factory()
            ->hasAttached($admin1, ['role' => 'admin'])
            ->hasAttached($admin2, ['role' => 'admin'])
            ->create();
        Sanctum::actingAs($admin1);

        $response = $this->deleteJson("/api/v1/tenants/{$tenant->id}/members/{$admin2->id}");

        $response->assertForbidden();
    });

    it('prevents owner from removing themselves if only owner', function () {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($owner, ['role' => 'owner'])->create();
        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/v1/tenants/{$tenant->id}/members/{$owner->id}");

        $response->assertUnprocessable();
        $response->assertJson(['message' => 'Cannot remove the only owner.']);
    });

    it('allows members to leave the tenant', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $tenant = Tenant::factory()
            ->hasAttached($owner, ['role' => 'owner'])
            ->hasAttached($member, ['role' => 'member'])
            ->create();
        Sanctum::actingAs($member);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/leave");

        $response->assertSuccessful();
        $this->assertDatabaseMissing('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
        ]);
    });
});

describe('List Members', function () {
    it('lists all members of a tenant', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $tenant = Tenant::factory()
            ->hasAttached($owner, ['role' => 'owner'])
            ->hasAttached($admin, ['role' => 'admin'])
            ->hasAttached($member, ['role' => 'member'])
            ->create();
        Sanctum::actingAs($member);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/members");

        $response->assertSuccessful();
        $response->assertJsonCount(3, 'data');
    });

    it('includes role information', function () {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($owner, ['role' => 'owner'])->create();
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/members");

        $response->assertJsonFragment([
            'role' => 'owner',
        ]);
    });
});

describe('Update Member Role', function () {
    it('allows owners to update member roles', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $tenant = Tenant::factory()
            ->hasAttached($owner, ['role' => 'owner'])
            ->hasAttached($member, ['role' => 'member'])
            ->create();
        Sanctum::actingAs($owner);

        $response = $this->patchJson("/api/v1/tenants/{$tenant->id}/members/{$member->id}", [
            'role' => 'admin',
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'role' => 'admin',
        ]);
    });

    it('denies admins from promoting to owner', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $tenant = Tenant::factory()
            ->hasAttached($owner, ['role' => 'owner'])
            ->hasAttached($admin, ['role' => 'admin'])
            ->hasAttached($member, ['role' => 'member'])
            ->create();
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/v1/tenants/{$tenant->id}/members/{$member->id}", [
            'role' => 'owner',
        ]);

        $response->assertForbidden();
    });
});
