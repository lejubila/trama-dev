<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * Workspace-style authorization:
 *  - any authenticated user can list / create tenants;
 *  - members can view a tenant they belong to;
 *  - only admins of a tenant can update or delete it.
 *
 * Membership management (add/remove/role-change) reuses `update` since
 * those operations are admin-scoped too.
 */
class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->belongsToTenant($tenant);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->isAdminOf($user, $tenant);
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $this->isAdminOf($user, $tenant);
    }

    private function isAdminOf(User $user, Tenant $tenant): bool
    {
        return $user->tenants()
            ->whereKey($tenant->getKey())
            ->wherePivot('role', 'admin')
            ->exists();
    }
}
