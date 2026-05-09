<?php

declare(strict_types=1);

use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Support\Tenancy\TenantContext;
use Spatie\Permission\PermissionRegistrar;

afterEach(function (): void {
    TenantContext::clear();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function bootRoomFor(array $u): Room
{
    TenantContext::setId($u['tenant']->getKey());
    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    TenantContext::clear();

    return $room;
}

it('lists racks of a room (nested route)', function (): void {
    $u = apiUser('admin');
    $room = bootRoomFor($u);
    TenantContext::setId($u['tenant']->getKey());
    Rack::factory()->count(3)->create(['room_id' => $room->getKey()]);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->getJson("/api/v1/rooms/{$room->id}/racks")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates a rack', function (): void {
    $u = apiUser('admin');
    $room = bootRoomFor($u);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson("/api/v1/rooms/{$room->id}/racks", [
            'name' => 'NEW-RACK',
            'height_units' => 24,
            'numbering' => 'bottom_up',
            'room_id' => $room->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.attributes.name', 'NEW-RACK');
});

it('shows a rack on the shallow route', function (): void {
    $u = apiUser('admin');
    $room = bootRoomFor($u);
    TenantContext::setId($u['tenant']->getKey());
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'name' => 'R-X']);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->getJson("/api/v1/racks/{$rack->id}")
        ->assertOk()
        ->assertJsonPath('data.attributes.name', 'R-X');
});

it('updates a rack', function (): void {
    $u = apiUser('admin');
    $room = bootRoomFor($u);
    TenantContext::setId($u['tenant']->getKey());
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'name' => 'OLD']);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->patchJson("/api/v1/racks/{$rack->id}", ['name' => 'NEW'])
        ->assertOk()
        ->assertJsonPath('data.attributes.name', 'NEW');
});

it('deletes a rack', function (): void {
    $u = apiUser('admin');
    $room = bootRoomFor($u);
    TenantContext::setId($u['tenant']->getKey());
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->deleteJson("/api/v1/racks/{$rack->id}")
        ->assertNoContent();
});
