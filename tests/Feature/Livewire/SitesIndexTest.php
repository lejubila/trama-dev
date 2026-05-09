<?php

declare(strict_types=1);

use App\Livewire\Sites\Index;
use App\Models\Site;
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

function makeUser(Tenant $tenant, string $role): User
{
    /** @var User $user */
    $user = User::factory()->create();
    $user->tenants()->attach($tenant, ['role' => $role]);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
    test()->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    $user->syncRoles([$role]);

    return $user;
}

it('renders the sites index for an admin', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = makeUser($tenant, 'admin');
    actingAsInTenant($admin, $tenant);

    Site::factory()->create(['name' => 'Sede Test']);

    Livewire::test(Index::class)
        ->assertOk()
        ->assertSee('Sede Test');
});

it('creates a new site as admin', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = makeUser($tenant, 'admin');
    actingAsInTenant($admin, $tenant);

    Livewire::test(Index::class)
        ->call('openCreate')
        ->set('name', 'Nuova Sede')
        ->set('address', 'Via Roma 1')
        ->call('save')
        ->assertHasNoErrors();

    expect(Site::query()->where('name', 'Nuova Sede')->exists())->toBeTrue();
});

it('updates an existing site', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = makeUser($tenant, 'admin');
    actingAsInTenant($admin, $tenant);

    $site = Site::factory()->create(['name' => 'Old Name']);

    Livewire::test(Index::class)
        ->call('openEdit', $site->getKey())
        ->set('name', 'New Name')
        ->call('save')
        ->assertHasNoErrors();

    expect($site->fresh()->name)->toBe('New Name');
});

it('validates required name on save', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = makeUser($tenant, 'admin');
    actingAsInTenant($admin, $tenant);

    Livewire::test(Index::class)
        ->call('openCreate')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('forbids cliente from creating a site', function (): void {
    $tenant = Tenant::factory()->create();
    $cliente = makeUser($tenant, 'cliente');
    actingAsInTenant($cliente, $tenant);

    Livewire::test(Index::class)
        ->call('openCreate')
        ->assertForbidden();
});

it('isolates sites between tenants', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    TenantContext::setId($a->getKey());
    Site::factory()->create(['name' => 'Sede A']);

    TenantContext::setId($b->getKey());
    Site::factory()->create(['name' => 'Sede B']);

    $userB = makeUser($b, 'admin');
    actingAsInTenant($userB, $b);

    Livewire::test(Index::class)
        ->assertSee('Sede B')
        ->assertDontSee('Sede A');
});
