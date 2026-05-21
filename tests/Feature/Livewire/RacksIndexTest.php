<?php

declare(strict_types=1);

use App\Livewire\Racks\Index;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;

afterEach(function (): void {
    TenantContext::clear();
});

function setupRackContext(string $role): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->forceFill(['role' => $role])->save();
    $user->tenants()->attach($tenant);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
    actingAsInTenant($user, $tenant);

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);

    return [$tenant, $user, $room];
}

it('renders racks index for tecnico', function (): void {
    [$tenant, $user, $room] = setupRackContext('tecnico');
    Rack::factory()->create(['room_id' => $room->getKey(), 'name' => 'Rack-X']);

    Livewire::test(Index::class)->assertOk()->assertSee('Rack-X');
});

it('creates a new rack', function (): void {
    [$tenant, $user, $room] = setupRackContext('admin');

    Livewire::test(Index::class)
        ->call('openCreate')
        ->set('roomId', $room->getKey())
        ->set('name', 'Rack-A')
        ->call('save')
        ->assertHasNoErrors();

    expect(Rack::query()->where('name', 'Rack-A')->exists())->toBeTrue();
});

it('updates an existing rack', function (): void {
    [$tenant, $user, $room] = setupRackContext('admin');
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'name' => 'Old']);

    Livewire::test(Index::class)
        ->call('openEdit', $rack->getKey())
        ->set('name', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($rack->fresh()->name)->toBe('New');
});

it('validates required fields on rack create', function (): void {
    [$tenant, $user, $room] = setupRackContext('admin');

    Livewire::test(Index::class)
        ->call('openCreate')
        ->set('name', '')
        ->set('roomId', null)
        ->call('save')
        ->assertHasErrors(['name', 'roomId']);
});

it('forbids cliente from creating a rack', function (): void {
    [$tenant, $user, $room] = setupRackContext('cliente');

    Livewire::test(Index::class)->call('openCreate')->assertForbidden();
});

it('isolates racks between tenants', function (): void {
    [$tenantA, $userA, $roomA] = setupRackContext('admin');
    Rack::factory()->create(['room_id' => $roomA->getKey(), 'name' => 'Rack-A']);

    // Build a second tenant separately so we don't pollute the first context
    TenantContext::clear();
    $tenantB = Tenant::factory()->create();
    TenantContext::setId($tenantB->getKey());
    $siteB = Site::factory()->create();
    $roomB = Room::factory()->create(['site_id' => $siteB->getKey()]);
    Rack::factory()->create(['room_id' => $roomB->getKey(), 'name' => 'Rack-B']);

    /** @var User $userB */
    $userB = User::factory()->create();
    $userB->forceFill(['role' => 'admin'])->save();
    $userB->tenants()->attach($tenantB);
    actingAsInTenant($userB, $tenantB);

    Livewire::test(Index::class)->assertSee('Rack-B')->assertDontSee('Rack-A');
});
