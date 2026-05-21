<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Removes a user's assignment to a tenant. With global roles there is no pivot
 * role to clean up — we just detach and, if needed, reset the user's current
 * tenant so the next request bootstraps to another accessible one.
 */
class RemoveMember
{
    public function execute(Tenant $tenant, User $member): void
    {
        if (! $member->belongsToTenant($tenant)) {
            throw new InvalidArgumentException('Utente non è assegnato a questo cliente.');
        }

        DB::transaction(function () use ($tenant, $member): void {
            $member->tenants()->detach($tenant->getKey());

            if ((int) $member->current_tenant_id === $tenant->getKey()) {
                $member->forceFill(['current_tenant_id' => null])->save();
            }
        });
    }
}
