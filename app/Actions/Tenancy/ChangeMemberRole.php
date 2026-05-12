<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

class ChangeMemberRole
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function execute(Tenant $tenant, User $member, string $newRole): void
    {
        if (! in_array($newRole, ['admin', 'tecnico', 'cliente'], true)) {
            throw new InvalidArgumentException('Ruolo non valido.');
        }

        if (! $member->belongsToTenant($tenant)) {
            throw new InvalidArgumentException('Utente non è membro di questo cliente.');
        }

        $currentRole = $member->roleInTenant($tenant);
        if ($currentRole === $newRole) {
            return;
        }

        if ($currentRole === 'admin' && $newRole !== 'admin' && $this->isLastAdmin($tenant)) {
            throw new InvalidArgumentException('Non puoi degradare l\'ultimo admin del cliente.');
        }

        DB::transaction(function () use ($tenant, $member, $newRole): void {
            $member->tenants()->updateExistingPivot($tenant->getKey(), ['role' => $newRole]);

            $previousTeam = $this->registrar->getPermissionsTeamId();
            $this->registrar->setPermissionsTeamId($tenant->getKey());
            try {
                $member->syncRoles([$newRole]);
            } finally {
                $this->registrar->setPermissionsTeamId($previousTeam);
            }
        });
    }

    private function isLastAdmin(Tenant $tenant): bool
    {
        return $tenant->users()->wherePivot('role', 'admin')->count() <= 1;
    }
}
