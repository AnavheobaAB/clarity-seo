<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $tenant->hasUser($user);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $tenant->canManageSettings($user);
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $tenant->canDelete($user);
    }

    public function manageMembers(User $user, Tenant $tenant): bool
    {
        return $tenant->canManageMembers($user);
    }

    public function inviteOwner(User $user, Tenant $tenant): bool
    {
        return $tenant->isOwner($user);
    }

    public function removeMember(User $user, Tenant $tenant, User $member): bool
    {
        if (! $tenant->canManageMembers($user)) {
            return false;
        }

        $userRole = $tenant->getUserRole($user);
        $memberRole = $tenant->getUserRole($member);

        // Owners can remove anyone
        if ($userRole === 'owner') {
            return true;
        }

        // Admins can only remove members
        return $memberRole === 'member';
    }

    public function updateMemberRole(User $user, Tenant $tenant, string $newRole): bool
    {
        if (! $tenant->canManageMembers($user)) {
            return false;
        }

        $userRole = $tenant->getUserRole($user);

        // Only owners can assign owner role
        if ($newRole === 'owner') {
            return $userRole === 'owner';
        }

        return true;
    }
}
