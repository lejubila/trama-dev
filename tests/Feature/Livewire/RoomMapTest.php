<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Livewire\Rooms\Map;
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

function setupRoomMapContext(string $role = 'tecnico'): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->forceFill(['role' => $role])->save();
    $user->tenants()->attach($tenant);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
    actingAsInTenant($user, $tenant);

    $site = Site::factory()->create();
    $room = Room::factory()->create([
        'site_id' => $site->getKey(),
        'width_m' => 10,
        'depth_m' => 6,
    ]);

    return [$tenant, $user, $room];
}

it('seeds default progressive positions for unracked equipment on mount', function (): void {
    [, , $room] = setupRoomMapContext();

    $a = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create([
        'rack_id' => null,
        'room_id' => $room->getKey(),
        'position_x' => null,
        'position_y' => null,
        'name' => 'AP-A',
    ]);
    $b = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create([
        'rack_id' => null,
        'room_id' => $room->getKey(),
        'position_x' => null,
        'position_y' => null,
        'name' => 'AP-B',
    ]);

    Livewire::test(Map::class, ['room' => $room])->assertOk();

    $a->refresh();
    $b->refresh();
    expect((float) $a->position_x)->toBe(0.4)
        ->and((float) $a->position_y)->toBe(0.4)
        ->and((float) $b->position_x)->toBe(0.9)
        ->and((float) $b->position_y)->toBe(0.4);
});

it('renders unracked equipment alongside racks on the floor plan', function (): void {
    [, , $room] = setupRoomMapContext();

    Rack::factory()->create(['room_id' => $room->getKey(), 'name' => 'RACK-1']);
    Equipment::factory()->ofType(EquipmentType::AccessPoint)->create([
        'rack_id' => null,
        'room_id' => $room->getKey(),
        'name' => 'AP-LOBBY',
    ]);

    Livewire::test(Map::class, ['room' => $room])
        ->assertOk()
        ->assertSee('RACK-1')
        ->assertSee('AP-LOBBY')
        ->assertSee('data-kind="equipment"', escape: false);
});

it('moveEquipment clamps inside the room and persists rounded values', function (): void {
    [, , $room] = setupRoomMapContext();

    $eq = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create([
        'rack_id' => null,
        'room_id' => $room->getKey(),
        'position_x' => 1,
        'position_y' => 1,
    ]);

    Livewire::test(Map::class, ['room' => $room])
        ->call('moveEquipment', $eq->getKey(), 999, -10)
        ->assertOk();

    $eq->refresh();
    expect((float) $eq->position_x)->toBe(9.6)
        ->and((float) $eq->position_y)->toBe(0.4);
});

it('resizeEquipmentIcon clamps within min/max', function (): void {
    [, , $room] = setupRoomMapContext();

    $eq = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create([
        'rack_id' => null,
        'room_id' => $room->getKey(),
    ]);

    Livewire::test(Map::class, ['room' => $room])
        ->call('resizeEquipmentIcon', $eq->getKey(), 9999);

    $eq->refresh();
    expect((int) $eq->icon_size_px)->toBe(Map::MAX_ICON_SIZE_PX);

    Livewire::test(Map::class, ['room' => $room])
        ->call('resizeEquipmentIcon', $eq->getKey(), -10);

    $eq->refresh();
    expect((int) $eq->icon_size_px)->toBe(Map::MIN_ICON_SIZE_PX);
});

it('resetEquipmentIcon nullifies icon_size_px', function (): void {
    [, , $room] = setupRoomMapContext();

    $eq = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create([
        'rack_id' => null,
        'room_id' => $room->getKey(),
        'icon_size_px' => 90,
    ]);

    Livewire::test(Map::class, ['room' => $room])
        ->call('resetEquipmentIcon', $eq->getKey());

    $eq->refresh();
    expect($eq->icon_size_px)->toBeNull();
});

it('does not seed default positions for cliente role (no manage data)', function (): void {
    [, , $room] = setupRoomMapContext('cliente');

    $eq = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create([
        'rack_id' => null,
        'room_id' => $room->getKey(),
        'position_x' => null,
        'position_y' => null,
    ]);

    Livewire::test(Map::class, ['room' => $room])->assertOk();

    $eq->refresh();
    expect($eq->position_x)->toBeNull();
});
