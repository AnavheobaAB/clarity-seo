<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use Illuminate\Support\Facades\DB;

class TenantService
{
    public function create(array $data, User $owner): Tenant
    {
        return DB::transaction(function () use ($data, $owner) {
            $tenant = Tenant::create($data);
            $tenant->users()->attach($owner->id, ['role' => 'owner']);
            $owner->update(['current_tenant_id' => $tenant->id]);

            return $tenant;
        });
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        return $tenant->fresh();
    }

    public function delete(Tenant $tenant): void
    {
        $tenant->delete();
    }

    public function inviteMember(Tenant $tenant, string $email, string $role): TenantInvitation
    {
        $invitation = $tenant->invitations()->create([
            'email' => $email,
            'role' => $role,
        ]);

        // Send notification
        // Notification::route('mail', $email)->notify(new TenantInvitationNotification($invitation));

        return $invitation;
    }

    public function acceptInvitation(TenantInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user) {
            $invitation->tenant->users()->attach($user->id, ['role' => $invitation->role]);

            if (! $user->current_tenant_id) {
                $user->update(['current_tenant_id' => $invitation->tenant_id]);
            }

            $invitation->delete();
        });
    }

    public function removeMember(Tenant $tenant, User $user): void
    {
        $tenant->users()->detach($user->id);

        if ($user->current_tenant_id === $tenant->id) {
            $user->update(['current_tenant_id' => $user->tenants()->first()?->id]);
        }
    }

    public function updateMemberRole(Tenant $tenant, User $user, string $role): void
    {
        $tenant->users()->updateExistingPivot($user->id, ['role' => $role]);
    }

    public function leaveTenant(Tenant $tenant, User $user): void
    {
        $this->removeMember($tenant, $user);
    }

    public function switchTenant(User $user, Tenant $tenant): void
    {
        $user->switchTenant($tenant);
    }
}
