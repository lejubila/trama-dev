<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Services\TopologyService;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

function makeWiredScene(): array
{
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);

    $sw = Equipment::factory()->ofType(EquipmentType::Switch)->mountedAt(1, 1)->create([
        'rack_id' => $rack->getKey(),
        'name' => 'SW-X',
    ]);
    $rt = Equipment::factory()->ofType(EquipmentType::Router)->mountedAt(2, 1)->create([
        'rack_id' => $rack->getKey(),
        'name' => 'RTR-X',
    ]);

    $a = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $sw->getKey(), 'name' => 'Gi0/1']);
    $b = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $rt->getKey(), 'name' => 'Gi0/0']);

    Connection::create([
        'tenant_id' => $tenant->getKey(),
        'from_interface_id' => $a->getKey(),
        'to_interface_id' => $b->getKey(),
        'cable_type' => 'utp_cat6',
        'status' => 'active',
    ]);

    return [$tenant, $site, $sw, $rt];
}

it('returns nodes and edges for the active tenant only', function (): void {
    [$tenantA, $siteA] = makeWiredScene();

    // Build a separate tenant scene; its data must not leak
    $tenantB = Tenant::factory()->create();
    TenantContext::setId($tenantB->getKey());
    $siteB = Site::factory()->create();
    $roomB = Room::factory()->create(['site_id' => $siteB->getKey()]);
    $rackB = Rack::factory()->create(['room_id' => $roomB->getKey()]);
    Equipment::factory()->ofType(EquipmentType::Switch)->create([
        'rack_id' => $rackB->getKey(),
        'name' => 'SW-OTHER-TENANT',
    ]);

    TenantContext::setId($tenantA->getKey());

    $graph = app(TopologyService::class)->buildGraph();

    $labels = collect($graph['nodes'])->pluck('data.label')->all();
    expect($labels)->toContain('SW-X', 'RTR-X')
        ->and($labels)->not->toContain('SW-OTHER-TENANT')
        ->and($graph['edges'])->toHaveCount(1);
});

it('filters by site', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    // Equipment in another site of the same tenant
    $otherSite = Site::factory()->create();
    $otherRoom = Room::factory()->create(['site_id' => $otherSite->getKey()]);
    $otherRack = Rack::factory()->create(['room_id' => $otherRoom->getKey()]);
    Equipment::factory()->mountedAt(1, 1)->create([
        'rack_id' => $otherRack->getKey(),
        'name' => 'SW-OTHER-SITE',
    ]);

    $graph = app(TopologyService::class)->buildGraph(siteId: $site->getKey());

    $labels = collect($graph['nodes'])->pluck('data.label')->all();
    expect($labels)->toContain('SW-X')
        ->and($labels)->not->toContain('SW-OTHER-SITE');
});

it('filters by equipment type', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    $graph = app(TopologyService::class)->buildGraph(types: [EquipmentType::Router->value]);

    $labels = collect($graph['nodes'])->pluck('data.label')->all();
    expect($labels)->toContain('RTR-X')
        ->and($labels)->not->toContain('SW-X')
        // The connection's other end is filtered out → no edge survives
        ->and($graph['edges'])->toHaveCount(0);
});

it('filters by status', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    // Mark router decommissioned → status filter='active' should exclude it
    $rt->update(['status' => 'decommissioned']);

    $graph = app(TopologyService::class)->buildGraph(status: 'active');

    $labels = collect($graph['nodes'])->pluck('data.label')->all();
    expect($labels)->toContain('SW-X')
        ->and($labels)->not->toContain('RTR-X');
});

it('filters by VLAN via interface vlan_default', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    $sw->interfaces()->update(['vlan_default' => 42]);

    $graph = app(TopologyService::class)->buildGraph(vlan: 42);

    $labels = collect($graph['nodes'])->pluck('data.label')->all();
    expect($labels)->toContain('SW-X')
        ->and($labels)->not->toContain('RTR-X');
});
