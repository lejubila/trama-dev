<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use InvalidArgumentException;

/**
 * Assigns a user to a tenant. Assignment carries no role: it only governs which
 * tenants a cliente can see (admins and tecnici access every tenant regardless).
 */
class AssignTenant
{
    public function execute(Tenant $tenant, User $user): User
    {
        if ($user->belongsToTenant($tenant)) {
            throw new InvalidArgumentException('L\'utente è già assegnato a questo cliente.');
        }

        $user->tenants()->attach($tenant->getKey());

        return $user;
    }
}
