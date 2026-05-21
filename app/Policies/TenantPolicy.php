<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * Tenant (cliente) authorization with global roles (admin bypasses via
 * Gate::before):
 *  - admin/tecnico manage every tenant (list, create, update, delete);
 *  - clienti may only view the tenants they are explicitly assigned to.
 */
class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->canAccessTenant($tenant);
    }

    public function create(User $user): bool
    {
        return $user->canManageData();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->canManageData();
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->canManageData();
    }
}
