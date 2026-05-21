<?php

declare(strict_types=1);

use App\Livewire\Equipment\Drawer;
use App\Livewire\Racks\Elevation;
use App\Livewire\Racks\Show as RackShow;
use App\Models\Equipment;
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

function bootRackScene(string $role): array
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
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'height_units' => 10]);

    return [$tenant, $user, $rack];
}

it('renders rack elevation with mounted equipment', function (): void {
    [$tenant, $user, $rack] = bootRackScene('admin');

    Equipment::factory()->mountedAt(3, 1)->create([
        'rack_id' => $rack->getKey(),
        'name' => 'SW-RACK',
    ]);

    Livewire::test(RackShow::class, ['rack' => $rack])
        ->assertOk()
        ->assertSee('SW-RACK');
});

it('moves equipment to a free U as admin', function (): void {
    [$tenant, $user, $rack] = bootRackScene('admin');

    $eq = Equipment::factory()->mountedAt(3, 1)->create(['rack_id' => $rack->getKey()]);

    Livewire::test(Elevation::class, ['rack' => $rack])
        ->call('moveEquipment', $eq->getKey(), 7);

    expect($eq->fresh()->position_u_start)->toBe(7);
});

it('allows move into the same U as another equipment (multi-device per U)', function (): void {
    [$tenant, $user, $rack] = bootRackScene('admin');

    $eq = Equipment::factory()->mountedAt(3, 1)->create(['rack_id' => $rack->getKey()]);
    Equipment::factory()->mountedAt(5, 2)->create(['rack_id' => $rack->getKey()]);

    Livewire::test(Elevation::class, ['rack' => $rack])
        ->call('moveEquipment', $eq->getKey(), 5)
        ->assertDispatched('toast', type: 'success');

    expect($eq->fresh()->position_u_start)->toBe(5);
});

it('refuses move on a locked equipment', function (): void {
    [$tenant, $user, $rack] = bootRackScene('admin');

    $eq = Equipment::factory()->mountedAt(3, 1)->create([
        'rack_id' => $rack->getKey(),
        'locked' => true,
    ]);

    Livewire::test(Elevation::class, ['rack' => $rack])
        ->call('moveEquipment', $eq->getKey(), 7)
        ->assertDispatched('toast', type: 'error');

    expect($eq->fresh()->position_u_start)->toBe(3);
});

it('refuses move that would overflow rack height', function (): void {
    [$tenant, $user, $rack] = bootRackScene('admin');
    $eq = Equipment::factory()->mountedAt(3, 2)->create(['rack_id' => $rack->getKey()]);

    Livewire::test(Elevation::class, ['rack' => $rack])
        ->call('moveEquipment', $eq->getKey(), 10) // height=2, rack=10, so start=10 → goes to U11 → overflow
        ->assertDispatched('toast', type: 'error');

    expect($eq->fresh()->position_u_start)->toBe(3);
});

it('toggles between front and rear orientation', function (): void {
    [$tenant, $user, $rack] = bootRackScene('admin');

    Livewire::test(Elevation::class, ['rack' => $rack])
        ->assertSet('orient', 'front')
        ->call('setOrient', 'rear')
        ->assertSet('orient', 'rear');
});

it('drawer loads equipment when equipment-clicked is dispatched', function (): void {
    [$tenant, $user, $rack] = bootRackScene('admin');

    $eq = Equipment::factory()->mountedAt(3, 1)->create([
        'rack_id' => $rack->getKey(),
        'name' => 'DRAWER-TARGET',
    ]);

    Livewire::test(Drawer::class)
        ->assertSet('open', false)
        ->dispatch('equipment-clicked', id: $eq->getKey())
        ->assertSet('open', true)
        ->assertSee('DRAWER-TARGET');
});

it('only admin can move equipment, tecnico ok, cliente forbidden', function (): void {
    [$tenant, $user, $rack] = bootRackScene('cliente');
    $eq = Equipment::factory()->mountedAt(3, 1)->create(['rack_id' => $rack->getKey()]);

    Livewire::test(Elevation::class, ['rack' => $rack])
        ->call('moveEquipment', $eq->getKey(), 7)
        ->assertForbidden();

    expect($eq->fresh()->position_u_start)->toBe(3);
});

it('opens the create form when slot-clicked fires', function (): void {
    [$tenant, $user, $rack] = bootRackScene('admin');

    Livewire::test(Elevation::class, ['rack' => $rack])
        ->assertSet('showForm', false)
        ->dispatch('slot-clicked', u: 5)
        ->assertSet('showForm', true)
        ->assertSet('selectedU', 5)
        ->assertSet('positionUHeight', 1);
});

it('creates a mounted equipment at the clicked U', function (): void {
    [$tenant, $user, $rack] = bootRackScene('admin');

    Livewire::test(Elevation::class, ['rack' => $rack])
        ->dispatch('slot-clicked', u: 7)
        ->set('name', 'NEW-FROM-SLOT')
        ->set('type', 'switch')
        ->call('saveEquipment')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    expect(Equipment::query()
        ->where('rack_id', $rack->getKey())
        ->where('name', 'NEW-FROM-SLOT')
        ->where('mounted', true)
        ->where('position_u_start', 7)
        ->where('position_u_height', 1)
        ->exists()
    )->toBeTrue();
});

it('allows creating a device that overlaps a neighbor (multi-device per U)', function (): void {
    [$tenant, $user, $rack] = bootRackScene('admin');
    Equipment::factory()->mountedAt(4, 2)->create(['rack_id' => $rack->getKey()]); // occupies U4-U5

    Livewire::test(Elevation::class, ['rack' => $rack])
        ->dispatch('slot-clicked', u: 3)
        ->set('name', 'STACKED')
        ->set('positionUHeight', 3) // U3-U4-U5; visually rendered side-by-side on overlap
        ->call('saveEquipment')
        ->assertHasNoErrors();

    expect(Equipment::query()->where('name', 'STACKED')->exists())->toBeTrue();
});

it('forbids cliente from opening the create form via slot-clicked', function (): void {
    [$tenant, $user, $rack] = bootRackScene('cliente');

    Livewire::test(Elevation::class, ['rack' => $rack])
        ->dispatch('slot-clicked', u: 5)
        ->assertForbidden();
});
