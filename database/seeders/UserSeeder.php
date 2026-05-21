<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Demo accounts, one per global role.
     *
     * @var array<int, array{name: string, email: string, role: UserRole}>
     */
    private const DEMO_USERS = [
        ['name' => 'Demo Admin', 'email' => 'admin@demo.test', 'role' => UserRole::Admin],
        ['name' => 'Demo Tecnico', 'email' => 'tecnico@demo.test', 'role' => UserRole::Tecnico],
        ['name' => 'Demo Cliente', 'email' => 'cliente@demo.test', 'role' => UserRole::Cliente],
    ];

    public function run(): void
    {
        $tenants = Tenant::query()->get();

        foreach (self::DEMO_USERS as $spec) {
            $user = User::query()->updateOrCreate(
                ['email' => $spec['email']],
                [
                    'name' => $spec['name'],
                    'role' => $spec['role'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            // Only clienti need explicit tenant assignment (no role on the pivot);
            // admins and tecnici see every tenant regardless.
            if ($spec['role'] === UserRole::Cliente) {
                $user->tenants()->sync($tenants->modelKeys());
            } else {
                $user->tenants()->detach();
            }

            $user->forceFill(['current_tenant_id' => $tenants->first()?->getKey()])->save();
        }
    }
}
