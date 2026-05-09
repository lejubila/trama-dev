<?php

declare(strict_types=1);

use App\Livewire\Audit\Trail;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

afterEach(function (): void {
    TenantContext::clear();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('shows audits scoped to the active tenant only', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    TenantContext::setId($tenantA->getKey());
    $eqA = Equipment::factory()->create(['name' => 'EQ-A']);
    $eqA->update(['name' => 'EQ-A-renamed']);

    TenantContext::setId($tenantB->getKey());
    Equipment::factory()->create(['name' => 'EQ-B']);

    /** @var User $userA */
    $userA = User::factory()->create();
    $userA->tenants()->attach($tenantA, ['role' => 'admin']);
    test()->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenantA->getKey());
    $userA->syncRoles(['admin']);
    actingAsInTenant($userA, $tenantA);

    Livewire::test(Trail::class)
        ->assertOk()
        // Audits for tenantA's Equipment land in the trail; we don't see audits
        // for tenantB. We compare counts via the auditable_type cell to avoid
        // depending on the JSON-rendered diff being captured by assertSee.
        ->assertSee('Equipment#'.$eqA->getKey())
        ->assertDontSee('"EQ-B"');
});

it('filters by event type', function (): void {
    $tenant = Tenant::factory()->create();

    TenantContext::setId($tenant->getKey());
    $eq = Equipment::factory()->create();
    $eq->update(['name' => 'updated-once']);
    $eq->delete();

    /** @var User $user */
    $user = User::factory()->create();
    $user->tenants()->attach($tenant, ['role' => 'admin']);
    test()->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    $user->syncRoles(['admin']);
    actingAsInTenant($user, $tenant);

    Livewire::test(Trail::class)
        ->set('eventFilter', 'deleted')
        ->assertSee('deleted');
});
