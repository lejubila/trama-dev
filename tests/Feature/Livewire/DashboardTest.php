<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Livewire\Dashboard\Index;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

afterEach(function (): void {
    TenantContext::clear();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    Cache::flush();
});

it('renders the dashboard for an authenticated user with KPI numbers', function (): void {
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->tenants()->attach($tenant, ['role' => 'admin']);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
    test()->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    $user->syncRoles(['admin']);
    actingAsInTenant($user, $tenant);

    Equipment::factory()->ofType(EquipmentType::Switch)->count(3)->create();

    $component = Livewire::test(Index::class)->assertOk();
    $component->assertSee('Riepilogo');
    $component->assertSee('Dispositivi');
});

it('caches KPI counts per tenant', function (): void {
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->tenants()->attach($tenant, ['role' => 'admin']);
    test()->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    $user->syncRoles(['admin']);
    actingAsInTenant($user, $tenant);

    Livewire::test(Index::class)->assertOk();

    expect(Cache::has("dashboard.kpi.tenant.{$tenant->getKey()}"))->toBeTrue();
});
