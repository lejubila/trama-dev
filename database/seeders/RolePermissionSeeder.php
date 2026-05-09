<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Coarse-grained capabilities for FASE 1. Per-resource verbs land with FASE 3.
     *
     * @var list<string>
     */
    private const PERMISSIONS = [
        'manage_users',
        'manage_equipment',
        'manage_connections',
        'view_only',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ROLE_MAP = [
        'admin' => ['manage_users', 'manage_equipment', 'manage_connections', 'view_only'],
        'tecnico' => ['manage_equipment', 'manage_connections', 'view_only'],
        'cliente' => ['view_only'],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $registrar = app(PermissionRegistrar::class);

        // teams=true scopes roles per tenant_id, so we instantiate the same role set
        // inside each tenant.
        Tenant::query()->each(function (Tenant $tenant) use ($registrar): void {
            $registrar->setPermissionsTeamId($tenant->getKey());

            foreach (self::ROLE_MAP as $roleName => $perms) {
                $role = Role::findOrCreate($roleName, 'web');
                $role->syncPermissions($perms);
            }
        });

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }
}
