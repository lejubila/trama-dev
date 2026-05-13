<?php

declare(strict_types=1);

use App\Livewire\Connections\Wizard;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
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

function setupConnectionsScene(string $role): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->tenants()->attach($tenant, ['role' => $role]);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
    test()->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    $user->syncRoles([$role]);
    actingAsInTenant($user, $tenant);

    $eq1 = Equipment::factory()->create(['name' => 'A']);
    $eq2 = Equipment::factory()->create(['name' => 'B']);
    $a = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eq1->getKey(), 'name' => 'p1']);
    $b = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eq2->getKey(), 'name' => 'p1']);

    return [$tenant, $user, $a, $b];
}

it('creates a connection through the wizard happy path', function (): void {
    [$tenant, $user, $a, $b] = setupConnectionsScene('admin');

    Livewire::test(Wizard::class)
        ->set('fromInterfaceId', $a->getKey())
        ->set('toInterfaceId', $b->getKey())
        ->set('cableType', 'utp_cat6')
        ->call('save')
        ->assertHasNoErrors();

    expect(Connection::query()->where('from_interface_id', $a->getKey())->exists())->toBeTrue();
});

it('refuses self-connection in the wizard', function (): void {
    [$tenant, $user, $a] = setupConnectionsScene('admin');

    Livewire::test(Wizard::class)
        ->set('fromInterfaceId', $a->getKey())
        ->set('toInterfaceId', $a->getKey())
        ->set('cableType', 'utp_cat6')
        ->call('save')
        ->assertHasErrors(['toInterfaceId']);
});

it('forbids cliente from accessing the wizard', function (): void {
    [$tenant, $user] = setupConnectionsScene('cliente');

    Livewire::test(Wizard::class)->assertForbidden();
});

it('refuses to wire an already busy interface', function (): void {
    [$tenant, $user, $a, $b] = setupConnectionsScene('admin');

    // Pre-existing active connection on $a
    Connection::create([
        'tenant_id' => $tenant->getKey(),
        'from_interface_id' => $a->getKey(),
        'to_interface_id' => $b->getKey(),
        'cable_type' => 'utp_cat6',
        'status' => 'active',
    ]);

    $eq3 = Equipment::factory()->create();
    $c = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eq3->getKey(), 'name' => 'p1']);

    Livewire::test(Wizard::class)
        ->set('fromInterfaceId', $a->getKey())
        ->set('toInterfaceId', $c->getKey())
        ->set('cableType', 'utp_cat6')
        ->call('save')
        ->assertHasErrors(['toInterfaceId']);
});

it('walks through every step and creates the connection at submit', function (): void {
    [$tenant, $user, $a, $b] = setupConnectionsScene('admin');

    Livewire::test(Wizard::class)
        ->assertSet('step', 1)
        ->set('fromInterfaceId', $a->getKey())
        ->call('next')
        ->assertSet('step', 2)
        ->set('toInterfaceId', $b->getKey())
        ->call('next')
        ->assertSet('step', 3)
        ->set('cableType', 'utp_cat6')
        ->call('save')
        ->assertHasNoErrors();

    expect(Connection::query()
        ->where('from_interface_id', $a->getKey())
        ->where('to_interface_id', $b->getKey())
        ->exists()
    )->toBeTrue();
});

it('clears a stale toInterfaceId when advancing from step 1', function (): void {
    [$tenant, $user, $a, $b] = setupConnectionsScene('admin');

    // Simulate the morph-stuck DOM scenario: the step-2 <select> would
    // carry the step-1 value into toInterfaceId via deferred wire:model.
    // next() must reset toInterfaceId so server state stays coherent
    // regardless of what the DOM tries to push up.
    Livewire::test(Wizard::class)
        ->set('toInterfaceId', $a->getKey())
        ->set('fromInterfaceId', $a->getKey())
        ->call('next')
        ->assertSet('step', 2)
        ->assertSet('toInterfaceId', null);
});

it('surfaces validation errors at step 3 instead of failing silently', function (): void {
    [$tenant, $user, $a, $b] = setupConnectionsScene('admin');

    // Simulate the user reaching step 3 with the from-endpoint blanked.
    // Before the fix, the resulting validation error lived in an error bag
    // that step 3 never rendered — so the click looked like a no-op.
    Livewire::test(Wizard::class)
        ->set('step', 3)
        ->set('fromInterfaceId', null)
        ->set('toInterfaceId', $b->getKey())
        ->set('cableType', 'utp_cat6')
        ->call('save')
        ->assertHasErrors(['fromInterfaceId']);

    expect(Connection::query()->where('to_interface_id', $b->getKey())->exists())->toBeFalse();
});
