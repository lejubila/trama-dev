<?php

declare(strict_types=1);

use App\Actions\Tenancy\CreateTenant;
use App\Livewire\Tenants\Manage;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;

afterEach(function (): void {
    TenantContext::clear();
});

function bootTenantWithAdmin(): array
{
    /** @var User $admin */
    $admin = User::factory()->admin()->create();
    $tenant = app(CreateTenant::class)->execute($admin, ['name' => 'Acme Manage Test']);
    TenantContext::setId($tenant->getKey());
    test()->actingAs($admin);

    return [$tenant, $admin];
}

it('assigns a cliente from the picker', function (): void {
    [$tenant] = bootTenantWithAdmin();
    $cliente = User::factory()->cliente()->create();

    Livewire::test(Manage::class, ['tenant' => $tenant])
        ->set('activeTab', 'members')
        ->set('newUserId', $cliente->getKey())
        ->call('addMember')
        ->assertHasNoErrors();

    expect($cliente->fresh()->belongsToTenant($tenant))->toBeTrue();
});

it('refuses to assign a user id that does not exist', function (): void {
    [$tenant] = bootTenantWithAdmin();

    Livewire::test(Manage::class, ['tenant' => $tenant])
        ->set('activeTab', 'members')
        ->set('newUserId', 999999) // not in DB
        ->call('addMember')
        ->assertHasErrors(['newUserId']);
});

it('refuses to assign a cliente who is already assigned', function (): void {
    [$tenant] = bootTenantWithAdmin();
    $cliente = User::factory()->cliente()->create();
    $cliente->tenants()->attach($tenant->getKey());

    Livewire::test(Manage::class, ['tenant' => $tenant])
        ->set('activeTab', 'members')
        ->set('newUserId', $cliente->getKey())
        ->call('addMember')
        ->assertHasErrors(['newUserId']);
});

it('removes a cliente assignment', function (): void {
    [$tenant] = bootTenantWithAdmin();
    $cliente = User::factory()->cliente()->create();
    $cliente->tenants()->attach($tenant->getKey());

    Livewire::test(Manage::class, ['tenant' => $tenant])
        ->call('removeMember', $cliente->getKey());

    expect($cliente->fresh()->belongsToTenant($tenant))->toBeFalse();
});

it('forbids a cliente from managing assignments', function (): void {
    [$tenant] = bootTenantWithAdmin();
    /** @var User $cliente */
    $cliente = User::factory()->cliente()->create();
    $cliente->tenants()->attach($tenant->getKey());
    $candidate = User::factory()->cliente()->create();
    test()->actingAs($cliente);

    Livewire::test(Manage::class, ['tenant' => $tenant])
        ->set('newUserId', $candidate->getKey())
        ->call('addMember')
        ->assertForbidden();
});
