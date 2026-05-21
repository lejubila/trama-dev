<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

function makeMember(Tenant $tenant, string $role): User
{
    /** @var User $user */
    $user = User::factory()->create();
    $user->forceFill(['role' => $role])->save();
    $user->tenants()->attach($tenant);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();

    return $user;
}

it('lets admin and tecnico create equipment, but blocks cliente', function (): void {
    $tenant = Tenant::factory()->create();

    $admin = makeMember($tenant, 'admin');
    $tecnico = makeMember($tenant, 'tecnico');
    $cliente = makeMember($tenant, 'cliente');

    expect($admin->can('create', Equipment::class))->toBeTrue()
        ->and($tecnico->can('create', Equipment::class))->toBeTrue()
        ->and($cliente->can('create', Equipment::class))->toBeFalse();
});

it('lets admin and tecnico delete equipment, but blocks cliente', function (): void {
    $tenant = Tenant::factory()->create();

    TenantContext::setId($tenant->getKey());
    $eq = Equipment::factory()->create();

    $admin = makeMember($tenant, 'admin');
    $tecnico = makeMember($tenant, 'tecnico');
    $cliente = makeMember($tenant, 'cliente');

    expect($admin->can('delete', $eq))->toBeTrue()
        ->and($tecnico->can('delete', $eq))->toBeTrue()
        ->and($cliente->can('delete', $eq))->toBeFalse();
});

it('blocks a cliente from viewing another tenant\'s data', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    TenantContext::setId($tenantA->getKey());
    $siteA = Site::factory()->create();

    // A cliente assigned only to tenant B must NOT see tenant A's site.
    $userB = makeMember($tenantB, 'cliente');

    expect($userB->can('view', $siteA))->toBeFalse();
});

it('lets a member view sites of their current tenant regardless of role', function (): void {
    $tenant = Tenant::factory()->create();

    TenantContext::setId($tenant->getKey());
    $site = Site::factory()->create();

    $cliente = makeMember($tenant, 'cliente');

    expect($cliente->can('view', $site))->toBeTrue()
        ->and($cliente->can('viewAny', Site::class))->toBeTrue();
});
