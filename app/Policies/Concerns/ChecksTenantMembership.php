<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Default authorization rules for tenant-scoped resources:
 *  - viewAny  → any logged-in user
 *  - view     → user is member of the model's tenant
 *  - create   → user has admin or tecnico role in the *current* tenant
 *  - update   → admin/tecnico in current tenant AND member of model's tenant
 *  - delete   → admin only, AND member of model's tenant
 *
 * Concrete policies can override single methods (e.g. TagPolicy lets clienti
 * view-only) without rewriting the whole thing.
 */
trait ChecksTenantMembership
{
    public function viewAny(User $user): bool
    {
        return $user->current_tenant_id !== null;
    }

    public function view(User $user, Model $model): bool
    {
        return (int) $user->current_tenant_id === (int) $model->getAttribute('tenant_id');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRoleInCurrentTenant(['admin', 'tecnico']);
    }

    public function update(User $user, Model $model): bool
    {
        return (int) $user->current_tenant_id === (int) $model->getAttribute('tenant_id')
            && $user->hasAnyRoleInCurrentTenant(['admin', 'tecnico']);
    }

    public function delete(User $user, Model $model): bool
    {
        return (int) $user->current_tenant_id === (int) $model->getAttribute('tenant_id')
            && $user->hasRoleInCurrentTenant('admin');
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->delete($user, $model);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->delete($user, $model);
    }
}
