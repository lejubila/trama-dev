<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

class AddMember
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function execute(Tenant $tenant, User $user, string $role): User
    {
        if (! in_array($role, ['admin', 'tecnico', 'cliente'], true)) {
            throw new InvalidArgumentException('Ruolo non valido.');
        }

        if ($user->belongsToTenant($tenant)) {
            throw new InvalidArgumentException("L'utente è già membro di questo cliente.");
        }

        DB::transaction(function () use ($tenant, $user, $role): void {
            $user->tenants()->attach($tenant->getKey(), ['role' => $role]);

            $previousTeam = $this->registrar->getPermissionsTeamId();
            $this->registrar->setPermissionsTeamId($tenant->getKey());
            try {
                $user->syncRoles([$role]);
            } finally {
                $this->registrar->setPermissionsTeamId($previousTeam);
            }
        });

        return $user;
    }
}
