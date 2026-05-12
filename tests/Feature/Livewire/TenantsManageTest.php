<?php

declare(strict_types=1);

use App\Actions\Tenancy\CreateTenant;
use App\Livewire\Tenants\Manage;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

afterEach(function (): void {
    TenantContext::clear();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function bootTenantWithAdmin(): array
{
    /** @var User $admin */
    $admin = User::factory()->create();
    $tenant = app(CreateTenant::class)->execute($admin, ['name' => 'Acme Manage Test']);
    TenantContext::setId($tenant->getKey());
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    test()->actingAs($admin);

    return [$tenant, $admin];
}

it('adds a member from the user picker', function (): void {
    [$tenant, $admin] = bootTenantWithAdmin();
    $other = User::factory()->create();

    Livewire::test(Manage::class, ['tenant' => $tenant])
        ->set('activeTab', 'members')
        ->set('newUserId', $other->getKey())
        ->set('newRole', 'tecnico')
        ->call('addMember')
        ->assertHasNoErrors();

    expect($other->fresh()->belongsToTenant($tenant))->toBeTrue()
        ->and($other->fresh()->roleInTenant($tenant))->toBe('tecnico');
});

it('refuses to add a user id that does not exist', function (): void {
    [$tenant] = bootTenantWithAdmin();

    Livewire::test(Manage::class, ['tenant' => $tenant])
        ->set('activeTab', 'members')
        ->set('newUserId', 999999) // not in DB
        ->set('newRole', 'tecnico')
        ->call('addMember')
        ->assertHasErrors(['newUserId']);
});

it('refuses to add a user who is already a member', function (): void {
    [$tenant, $admin] = bootTenantWithAdmin();
    $other = User::factory()->create();
    $other->tenants()->attach($tenant->getKey(), ['role' => 'cliente']);

    Livewire::test(Manage::class, ['tenant' => $tenant])
        ->set('activeTab', 'members')
        ->set('newUserId', $other->getKey())
        ->set('newRole', 'tecnico')
        ->call('addMember')
        ->assertHasErrors(['newUserId']);
});

it('changes a member role', function (): void {
    [$tenant, $admin] = bootTenantWithAdmin();
    /** @var User $member */
    $member = User::factory()->create();
    $member->tenants()->attach($tenant->getKey(), ['role' => 'cliente']);

    Livewire::test(Manage::class, ['tenant' => $tenant])
        ->call('changeRole', $member->getKey(), 'tecnico');

    expect($member->fresh()->roleInTenant($tenant))->toBe('tecnico');
});

it('refuses to remove the last admin', function (): void {
    [$tenant, $admin] = bootTenantWithAdmin();

    Livewire::test(Manage::class, ['tenant' => $tenant])
        ->call('removeMember', $admin->getKey())
        ->assertDispatched('toast', type: 'error');

    expect($admin->fresh()->belongsToTenant($tenant))->toBeTrue();
});

it('refuses to demote the last admin', function (): void {
    [$tenant, $admin] = bootTenantWithAdmin();

    Livewire::test(Manage::class, ['tenant' => $tenant])
        ->call('changeRole', $admin->getKey(), 'tecnico')
        ->assertDispatched('toast', type: 'error');

    expect($admin->fresh()->roleInTenant($tenant))->toBe('admin');
});

it('forbids non-admin from managing members', function (): void {
    [$tenant, $admin] = bootTenantWithAdmin();
    /** @var User $cliente */
    $cliente = User::factory()->create();
    $cliente->tenants()->attach($tenant->getKey(), ['role' => 'cliente']);
    $candidate = User::factory()->create();
    test()->actingAs($cliente);

    Livewire::test(Manage::class, ['tenant' => $tenant])
        ->set('newUserId', $candidate->getKey())
        ->call('addMember')
        ->assertForbidden();
});
