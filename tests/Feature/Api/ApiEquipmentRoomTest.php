<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

/**
 * A site with two rooms; the first one has a 42U rack.
 *
 * @return array{site: Site, room: Room, other: Room, rack: Rack}
 */
function roomsLab(Tenant $tenant): array
{
    TenantContext::setId($tenant->getKey());
    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey(), 'name' => 'CED']);
    $other = Room::factory()->create(['site_id' => $site->getKey(), 'name' => 'Ufficio']);
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'height_units' => 42]);
    TenantContext::clear();

    return ['site' => $site, 'room' => $room, 'other' => $other, 'rack' => $rack];
}

it('places a device in a room without a rack and finds it by room and site', function (): void {
    $u = apiUser('admin');
    ['site' => $site, 'other' => $other] = roomsLab($u['tenant']);
    $api = $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()));

    $api->postJson('/api/v1/equipment', ['name' => 'SW-WALL', 'type' => 'switch', 'room_id' => $other->id])
        ->assertCreated()
        ->assertJsonPath('data.attributes.room_id', $other->id)
        ->assertJsonPath('data.attributes.mounted', false)
        ->assertJsonPath('data.attributes.position', null);

    $api->getJson("/api/v1/equipment?room_id={$other->id}")->assertOk()->assertJsonCount(1, 'data');
    $api->getJson("/api/v1/equipment?site_id={$site->id}")->assertOk()->assertJsonCount(1, 'data');
});

it('takes the room from the rack and lists rack devices under the room', function (): void {
    $u = apiUser('admin');
    ['room' => $room, 'rack' => $rack] = roomsLab($u['tenant']);
    $api = $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()));

    $api->postJson('/api/v1/equipment', [
        'name' => 'SW-RACK', 'type' => 'switch', 'rack_id' => $rack->id, 'mounted' => true,
        'position_u_start' => 1, 'position_u_height' => 1,
    ])->assertCreated()->assertJsonPath('data.attributes.room_id', $room->id);

    $api->getJson("/api/v1/equipment?room_id={$room->id}")->assertOk()->assertJsonCount(1, 'data');
});

it('moves a rack-mounted device loose into another room', function (): void {
    $u = apiUser('admin');
    ['room' => $room, 'other' => $other, 'rack' => $rack] = roomsLab($u['tenant']);
    TenantContext::setId($u['tenant']->getKey());
    $eq = Equipment::factory()->create([
        'rack_id' => $rack->id, 'room_id' => $room->id, 'mounted' => true, 'position_u_start' => 10, 'position_u_height' => 2,
    ]);
    TenantContext::clear();
    $api = $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()));

    // Sending back the rack's own room changes nothing.
    $api->patchJson("/api/v1/equipment/{$eq->id}", ['room_id' => $room->id, 'name' => 'SW-X'])
        ->assertOk()->assertJsonPath('data.attributes.mounted', true)->assertJsonPath('data.attributes.position.u_start', 10);

    $api->patchJson("/api/v1/equipment/{$eq->id}", ['room_id' => $other->id])
        ->assertOk()
        ->assertJsonPath('data.attributes.room_id', $other->id)
        ->assertJsonPath('data.attributes.mounted', false);

    $fresh = Equipment::withoutGlobalScopes()->findOrFail($eq->id);
    expect($fresh->rack_id)->toBeNull()->and($fresh->position_u_start)->toBeNull();
});

it('rejects a room of another tenant or one that contradicts the rack', function (): void {
    $u = apiUser('admin');
    ['other' => $other, 'rack' => $rack] = roomsLab($u['tenant']);
    ['room' => $foreign] = roomsLab(Tenant::factory()->create());
    $api = $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()));

    $api->postJson('/api/v1/equipment', ['name' => 'X', 'type' => 'switch', 'room_id' => $foreign->id])
        ->assertUnprocessable()->assertJsonValidationErrors('room_id');

    $api->postJson('/api/v1/equipment', [
        'name' => 'X', 'type' => 'switch', 'room_id' => $other->id, 'rack_id' => $rack->id, 'mounted' => true,
        'position_u_start' => 1, 'position_u_height' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors('room_id');
});
