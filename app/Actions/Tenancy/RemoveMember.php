<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

class RemoveMember
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function execute(Tenant $tenant, User $member): void
    {
        if (! $member->belongsToTenant($tenant)) {
            throw new InvalidArgumentException('Utente non è membro di questo cliente.');
        }

        if ($member->roleInTenant($tenant) === 'admin' && $this->isLastAdmin($tenant)) {
            throw new InvalidArgumentException('Non puoi rimuovere l\'ultimo admin del cliente.');
        }

        DB::transaction(function () use ($tenant, $member): void {
            // Clear spatie roles in this tenant's team scope so we don't leave
            // dangling model_has_roles rows.
            $previousTeam = $this->registrar->getPermissionsTeamId();
            $this->registrar->setPermissionsTeamId($tenant->getKey());
            try {
                $member->syncRoles([]);
            } finally {
                $this->registrar->setPermissionsTeamId($previousTeam);
            }

            $member->tenants()->detach($tenant->getKey());

            // If the removed user had this tenant as current, reset so the
            // next request bootstraps to another one.
            if ((int) $member->current_tenant_id === $tenant->getKey()) {
                $member->forceFill(['current_tenant_id' => null])->save();
            }
        });
    }

    private function isLastAdmin(Tenant $tenant): bool
    {
        return $tenant->users()->wherePivot('role', 'admin')->count() <= 1;
    }
}
