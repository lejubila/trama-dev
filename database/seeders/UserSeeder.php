<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    /**
     * Demo accounts (one per role) — each is a member of every demo tenant.
     *
     * @var array<string, array{name: string, email: string, role: string}>
     */
    private const DEMO_USERS = [
        'admin' => ['name' => 'Demo Admin', 'email' => 'admin@demo.test', 'role' => 'admin'],
        'tecnico' => ['name' => 'Demo Tecnico', 'email' => 'tecnico@demo.test', 'role' => 'tecnico'],
        'cliente' => ['name' => 'Demo Cliente', 'email' => 'cliente@demo.test', 'role' => 'cliente'],
    ];

    public function run(): void
    {
        $tenants = Tenant::query()->get();
        $registrar = app(PermissionRegistrar::class);

        foreach (self::DEMO_USERS as $spec) {
            $user = User::query()->updateOrCreate(
                ['email' => $spec['email']],
                [
                    'name' => $spec['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            // Attach to every tenant with the same role
            $pivotData = $tenants->mapWithKeys(
                fn (Tenant $t): array => [$t->getKey() => ['role' => $spec['role']]],
            )->all();

            $user->tenants()->sync($pivotData);

            // Assign the spatie role inside each tenant scope
            foreach ($tenants as $tenant) {
                $registrar->setPermissionsTeamId($tenant->getKey());
                $user->syncRoles([$spec['role']]);
            }

            // Default landing tenant: first one
            $user->forceFill(['current_tenant_id' => $tenants->first()?->getKey()])->save();
        }

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }
}
