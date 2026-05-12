<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Materialises the three project-wide roles (admin/tecnico/cliente) and the
 * permissions they need INSIDE a specific tenant's team scope. Used by:
 *
 *  - RolePermissionSeeder during fresh setup (loops over all seeded tenants);
 *  - Actions\Tenancy\CreateTenant whenever a user spins up a new workspace.
 *
 * Idempotent: re-running on an already-bootstrapped tenant is a no-op.
 */
class TenantRoleBootstrapper
{
    /**
     * Coarse-grained capabilities. Per-resource verbs land on top via Policies.
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        'manage_users',
        'manage_equipment',
        'manage_connections',
        'view_only',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const ROLE_MAP = [
        'admin' => ['manage_users', 'manage_equipment', 'manage_connections', 'view_only'],
        'tecnico' => ['manage_equipment', 'manage_connections', 'view_only'],
        'cliente' => ['view_only'],
    ];

    public function __construct(private readonly PermissionRegistrar $registrar) {}

    /**
     * Ensure the global permission catalogue exists, then bootstrap roles
     * inside the given tenant team scope.
     */
    public function bootstrapFor(Tenant $tenant): void
    {
        $this->ensurePermissions();

        $previousTeam = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($tenant->getKey());

        try {
            foreach (self::ROLE_MAP as $roleName => $perms) {
                $role = Role::findOrCreate($roleName, 'web');
                $role->syncPermissions($perms);
            }
        } finally {
            $this->registrar->setPermissionsTeamId($previousTeam);
            $this->registrar->forgetCachedPermissions();
        }
    }

    /**
     * Bootstrap roles for every tenant — used by the seeder.
     */
    public function bootstrapAllTenants(): void
    {
        $this->ensurePermissions();

        Tenant::query()->each(function (Tenant $tenant): void {
            $this->bootstrapFor($tenant);
        });
    }

    private function ensurePermissions(): void
    {
        foreach (self::PERMISSIONS as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
    }
}
