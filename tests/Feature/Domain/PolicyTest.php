<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

afterEach(function (): void {
    TenantContext::clear();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function makeMember(Tenant $tenant, string $role): User
{
    /** @var User $user */
    $user = User::factory()->create();
    $user->tenants()->attach($tenant, ['role' => $role]);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();

    return $user;
}

it('lets admin and tecnico create equipment, but blocks cliente', function (): void {
    $tenant = Tenant::factory()->create();
    test()->seed(RolePermissionSeeder::class);

    $admin = makeMember($tenant, 'admin');
    $tecnico = makeMember($tenant, 'tecnico');
    $cliente = makeMember($tenant, 'cliente');

    expect($admin->can('create', Equipment::class))->toBeTrue()
        ->and($tecnico->can('create', Equipment::class))->toBeTrue()
        ->and($cliente->can('create', Equipment::class))->toBeFalse();
});

it('only admin can delete equipment', function (): void {
    $tenant = Tenant::factory()->create();
    test()->seed(RolePermissionSeeder::class);

    TenantContext::setId($tenant->getKey());
    $eq = Equipment::factory()->create();

    $admin = makeMember($tenant, 'admin');
    $tecnico = makeMember($tenant, 'tecnico');

    expect($admin->can('delete', $eq))->toBeTrue()
        ->and($tecnico->can('delete', $eq))->toBeFalse();
});

it('blocks view across tenants', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    test()->seed(RolePermissionSeeder::class);

    TenantContext::setId($tenantA->getKey());
    $siteA = Site::factory()->create();

    // user belongs only to tenant B with admin role; they should NOT see tenant A's site
    $userB = makeMember($tenantB, 'admin');

    expect($userB->can('view', $siteA))->toBeFalse();
});

it('lets a member view sites of their current tenant regardless of role', function (): void {
    $tenant = Tenant::factory()->create();
    test()->seed(RolePermissionSeeder::class);

    TenantContext::setId($tenant->getKey());
    $site = Site::factory()->create();

    $cliente = makeMember($tenant, 'cliente');

    expect($cliente->can('view', $site))->toBeTrue()
        ->and($cliente->can('viewAny', Site::class))->toBeTrue();
});
