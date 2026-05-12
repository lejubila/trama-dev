<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Tenancy\TenantRoleBootstrapper;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(TenantRoleBootstrapper $bootstrapper): void
    {
        $bootstrapper->bootstrapAllTenants();
    }
}
