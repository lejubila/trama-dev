<?php

declare(strict_types=1);

use App\Actions\Interfaces\CreateKeystonePair;
use App\Enums\EquipmentType;
use App\Livewire\Connections\Wizard;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
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

function setupConnectionsScene(string $role): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->forceFill(['role' => $role])->save();
    $user->tenants()->attach($tenant);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
    actingAsInTenant($user, $tenant);

    // Default scene: a single rack shared by the two equipments, so the
    // wizard's proximity filter at step 2 keeps them visible to each other.
    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);

    $eq1 = Equipment::factory()->create(['name' => 'A', 'rack_id' => $rack->getKey()]);
    $eq2 = Equipment::factory()->create(['name' => 'B', 'rack_id' => $rack->getKey()]);
    $a = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eq1->getKey(), 'name' => 'p1']);
    $b = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eq2->getKey(), 'name' => 'p1']);

    return [$tenant, $user, $a, $b, $rack, $room, $site];
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

it('filters step-2 destinations to same media and excludes rear when origin is not rear', function (): void {
    [$tenant, $user, $copperA, , $rack] = setupConnectionsScene('admin');

    // Both PP and the fiber switch live in the same rack as the origin,
    // so the proximity filter doesn't hide them and we exercise the
    // side/media rules in isolation.
    $pp = Equipment::factory()->create([
        'type' => EquipmentType::PatchPanel,
        'name' => 'PP1',
        'rack_id' => $rack->getKey(),
    ]);
    [$ppFront, $ppRear] = app(CreateKeystonePair::class)->execute($pp, ['name' => 'P1']);

    $sw2 = Equipment::factory()->create(['name' => 'SW2', 'rack_id' => $rack->getKey()]);
    $fiberPort = NetworkInterface::factory()->fiber()->create([
        'equipment_id' => $sw2->getKey(),
        'name' => 'Te1',
    ]);

    $visible = Livewire::test(Wizard::class)
        ->set('fromInterfaceId', $copperA->getKey())
        ->viewData('equipmentStep2')
        ->flatMap(fn ($eq) => $eq->interfaces->pluck('id'))
        ->all();

    expect($visible)->toContain($ppFront->getKey());
    expect($visible)->not->toContain($ppRear->getKey());
    expect($visible)->not->toContain($fiberPort->getKey());
});

it('filters step-2 to only rear interfaces when origin is rear (same rack)', function (): void {
    [$tenant, $user, $copperA, , $rack] = setupConnectionsScene('admin');

    $pp1 = Equipment::factory()->create([
        'type' => EquipmentType::PatchPanel,
        'name' => 'PP1',
        'rack_id' => $rack->getKey(),
    ]);
    $pp2 = Equipment::factory()->create([
        'type' => EquipmentType::PatchPanel,
        'name' => 'PP2',
        'rack_id' => $rack->getKey(),
    ]);
    [$pp1Front, $pp1Rear] = app(CreateKeystonePair::class)->execute($pp1, ['name' => 'P1']);
    [$pp2Front, $pp2Rear] = app(CreateKeystonePair::class)->execute($pp2, ['name' => 'Q1']);

    $visible = Livewire::test(Wizard::class)
        ->set('fromInterfaceId', $pp1Rear->getKey())
        ->viewData('equipmentStep2')
        ->flatMap(fn ($eq) => $eq->interfaces->pluck('id'))
        ->all();

    expect($visible)->toContain($pp2Rear->getKey());
    expect($visible)->not->toContain($pp2Front->getKey());
    expect($visible)->not->toContain($copperA->getKey());
});

it('applies proximity to rear origins on a patch panel', function (): void {
    [$tenant, $user, , , $rack, $room] = setupConnectionsScene('admin');

    $pp1 = Equipment::factory()->create([
        'type' => EquipmentType::PatchPanel,
        'name' => 'PP1',
        'rack_id' => $rack->getKey(),
    ]);
    [, $pp1Rear] = app(CreateKeystonePair::class)->execute($pp1, ['name' => 'P1']);

    // Patch panel in another rack → rear hidden by proximity.
    $otherRack = Rack::factory()->create(['room_id' => $room->getKey()]);
    $ppFar = Equipment::factory()->create([
        'type' => EquipmentType::PatchPanel,
        'name' => 'PP-FAR',
        'rack_id' => $otherRack->getKey(),
    ]);
    [, $ppFarRear] = app(CreateKeystonePair::class)->execute($ppFar, ['name' => 'F1']);

    // Wall outlet (unracked) in the same room → its rear should pass
    // (sameRoom check: eq.rack_id is null and matches origin's locale).
    $wo = Equipment::factory()->create([
        'type' => EquipmentType::WallOutlet,
        'name' => 'WO-1',
        'rack_id' => null,
        'room_id' => $room->getKey(),
    ]);
    [, $woRear] = app(CreateKeystonePair::class)->execute($wo, ['name' => 'A']);

    $visible = Livewire::test(Wizard::class)
        ->set('fromInterfaceId', $pp1Rear->getKey())
        ->viewData('equipmentStep2')
        ->flatMap(fn ($eq) => $eq->interfaces->pluck('id'))
        ->all();

    expect($visible)->not->toContain($ppFarRear->getKey());
    expect($visible)->toContain($woRear->getKey());
});

it('skips proximity for rear origins on wall outlets (passive endpoint)', function (): void {
    [$tenant, $user, , , $rack] = setupConnectionsScene('admin');

    // Origin: wall outlet rear in some random room.
    $wo = Equipment::factory()->create([
        'type' => EquipmentType::WallOutlet,
        'name' => 'WO-ORIG',
        'rack_id' => null,
        'room_id' => Room::factory()->create()->getKey(),
    ]);
    [, $woRear] = app(CreateKeystonePair::class)->execute($wo, ['name' => 'A']);

    // Patch panel in a completely unrelated rack: its rear should still
    // appear because wall outlet rears don't apply proximity.
    $pp = Equipment::factory()->create([
        'type' => EquipmentType::PatchPanel,
        'name' => 'PP-ANYWHERE',
        'rack_id' => $rack->getKey(),
    ]);
    [, $ppRear] = app(CreateKeystonePair::class)->execute($pp, ['name' => 'X']);

    $visible = Livewire::test(Wizard::class)
        ->set('fromInterfaceId', $woRear->getKey())
        ->viewData('equipmentStep2')
        ->flatMap(fn ($eq) => $eq->interfaces->pluck('id'))
        ->all();

    expect($visible)->toContain($ppRear->getKey());
});

it('toggling showAllTargets restores the full destination list', function (): void {
    [$tenant, $user, $copperA] = setupConnectionsScene('admin');

    // Different rack → hidden by the proximity filter, visible only when
    // showAllTargets bypasses every rule.
    $otherRack = Rack::factory()->create();
    $sw2 = Equipment::factory()->create(['name' => 'SW2', 'rack_id' => $otherRack->getKey()]);
    $copperPort = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $sw2->getKey(),
        'name' => 'Gi9',
    ]);

    $component = Livewire::test(Wizard::class)->set('fromInterfaceId', $copperA->getKey());

    $filtered = $component->viewData('equipmentStep2')
        ->flatMap(fn ($eq) => $eq->interfaces->pluck('id'))->all();
    expect($filtered)->not->toContain($copperPort->getKey());

    $unfiltered = $component->set('showAllTargets', true)
        ->viewData('equipmentStep2')
        ->flatMap(fn ($eq) => $eq->interfaces->pluck('id'))->all();
    expect($unfiltered)->toContain($copperPort->getKey());
});

it('hides equipment from a different rack in the same room', function (): void {
    [$tenant, $user, $copperA, , $rack, $room] = setupConnectionsScene('admin');

    $otherRack = Rack::factory()->create(['room_id' => $room->getKey()]);
    $sw2 = Equipment::factory()->create(['name' => 'OTHER-RACK', 'rack_id' => $otherRack->getKey()]);
    $foreign = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $sw2->getKey(),
        'name' => 'Gi1',
    ]);

    $visible = Livewire::test(Wizard::class)
        ->set('fromInterfaceId', $copperA->getKey())
        ->viewData('equipmentStep2')
        ->flatMap(fn ($eq) => $eq->interfaces->pluck('id'))
        ->all();

    expect($visible)->not->toContain($foreign->getKey());
});

it('includes unracked equipment from the same room of a racked origin', function (): void {
    [$tenant, $user, $copperA, , , $room] = setupConnectionsScene('admin');

    $wallOutlet = Equipment::factory()->create([
        'type' => EquipmentType::WallOutlet,
        'name' => 'WALL-01',
        'rack_id' => null,
        'room_id' => $room->getKey(),
    ]);
    [$woFront, $woRear] = app(CreateKeystonePair::class)->execute($wallOutlet, ['name' => 'A']);

    $visible = Livewire::test(Wizard::class)
        ->set('fromInterfaceId', $copperA->getKey())
        ->viewData('equipmentStep2')
        ->flatMap(fn ($eq) => $eq->interfaces->pluck('id'))
        ->all();

    expect($visible)->toContain($woFront->getKey());
    expect($visible)->not->toContain($woRear->getKey());
});

it('uses the origin room as filter when origin is unracked', function (): void {
    [$tenant, $user, , , , $room] = setupConnectionsScene('admin');

    // Origin: unracked switch directly in the room.
    $unrackedOrigin = Equipment::factory()->create([
        'rack_id' => null,
        'room_id' => $room->getKey(),
        'name' => 'UNRACKED-A',
    ]);
    $origin = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $unrackedOrigin->getKey(),
        'name' => 'eth0',
    ]);

    // Same-room unracked target: visible.
    $unrackedB = Equipment::factory()->create([
        'rack_id' => null,
        'room_id' => $room->getKey(),
        'name' => 'UNRACKED-B',
    ]);
    $sameRoomPort = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $unrackedB->getKey(),
        'name' => 'eth0',
    ]);

    // Different-room unracked target: hidden.
    $otherRoom = Room::factory()->create();
    $unrackedFar = Equipment::factory()->create([
        'rack_id' => null,
        'room_id' => $otherRoom->getKey(),
        'name' => 'UNRACKED-FAR',
    ]);
    $farPort = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $unrackedFar->getKey(),
        'name' => 'eth0',
    ]);

    $visible = Livewire::test(Wizard::class)
        ->set('fromInterfaceId', $origin->getKey())
        ->viewData('equipmentStep2')
        ->flatMap(fn ($eq) => $eq->interfaces->pluck('id'))
        ->all();

    expect($visible)->toContain($sameRoomPort->getKey());
    expect($visible)->not->toContain($farPort->getKey());
});
