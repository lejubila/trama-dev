<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

/**
 * Parent ids are fixed by the URL on create and never movable through a
 * PATCH body: a foreign tenant's parent must not be reachable.
 *
 * @return array{site: Site, room: Room, rack: Rack, equipment: Equipment}
 */
function foreignTree(): array
{
    TenantContext::setId(Tenant::factory()->create()->getKey());
    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);
    $equipment = Equipment::factory()->create();
    TenantContext::clear();

    return compact('site', 'room', 'rack', 'equipment');
}

it('ignores parent ids in PATCH bodies', function (): void {
    $u = apiUser('admin');
    $other = foreignTree();

    TenantContext::setId($u['tenant']->getKey());
    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);
    $eq = Equipment::factory()->create();
    $if = NetworkInterface::factory()->create(['equipment_id' => $eq->getKey()]);
    TenantContext::clear();

    $api = $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()));
    $api->patchJson("/api/v1/sites/{$site->id}/rooms/{$room->id}", ['site_id' => $other['site']->id])->assertOk();
    $api->patchJson("/api/v1/racks/{$rack->id}", ['room_id' => $other['room']->id])->assertOk();
    $api->patchJson("/api/v1/interfaces/{$if->id}", ['equipment_id' => $other['equipment']->id])->assertOk();

    expect($room->fresh()->site_id)->toBe($site->id)
        ->and($rack->fresh()->room_id)->toBe($room->id)
        ->and($if->fresh()->equipment_id)->toBe($eq->id);
});

it('rejects a rack of another tenant on equipment', function (): void {
    $u = apiUser('admin');
    $other = foreignTree();

    TenantContext::setId($u['tenant']->getKey());
    $eq = Equipment::factory()->create(['mounted' => false, 'rack_id' => null]);
    TenantContext::clear();

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->patchJson("/api/v1/equipment/{$eq->id}", ['rack_id' => $other['rack']->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rack_id');

    expect($eq->fresh()->rack_id)->toBeNull();
});
