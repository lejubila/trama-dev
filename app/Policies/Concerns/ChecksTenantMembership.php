<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Default authorization rules for tenant-scoped resources, based on the global
 * user role (admin bypasses everything via Gate::before):
 *  - viewAny  → any logged-in user with an active tenant
 *  - view     → model belongs to the user's current tenant
 *  - create   → tecnico (manages data of every tenant); clienti are read-only
 *  - update   → tecnico AND model belongs to the current tenant
 *  - delete   → tecnico AND model belongs to the current tenant
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
        return $user->canManageData();
    }

    public function update(User $user, Model $model): bool
    {
        return (int) $user->current_tenant_id === (int) $model->getAttribute('tenant_id')
            && $user->canManageData();
    }

    public function delete(User $user, Model $model): bool
    {
        return (int) $user->current_tenant_id === (int) $model->getAttribute('tenant_id')
            && $user->canManageData();
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
