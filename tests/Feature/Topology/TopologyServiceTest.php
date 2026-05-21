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

it('excludes equipment flagged as hidden_in_topology and includes them when includeHidden=true', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    $rt->update(['hidden_in_topology' => true]);

    $graph = app(TopologyService::class)->buildGraph();
    $labels = collect($graph['nodes'])->pluck('data.label')->all();
    expect($labels)->toContain('SW-X')
        ->and($labels)->not->toContain('RTR-X')
        ->and($graph['edges'])->toHaveCount(0);

    $graphAll = app(TopologyService::class)->buildGraph(includeHidden: true);
    $labelsAll = collect($graphAll['nodes'])->pluck('data.label')->all();
    expect($labelsAll)->toContain('SW-X', 'RTR-X')
        ->and($graphAll['edges'])->toHaveCount(1);
});

it('filters by VLAN via interface vlan_default', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    $sw->interfaces()->update(['vlan_default' => 42]);

    $graph = app(TopologyService::class)->buildGraph(vlan: 42);

    $labels = collect($graph['nodes'])->pluck('data.label')->all();
    expect($labels)->toContain('SW-X')
        ->and($labels)->not->toContain('RTR-X');
});

it('emits a rack compound parent and sets parent on children when groupByRack is true', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    $graph = app(TopologyService::class)->buildGraph(groupByRack: true);

    $nodes = collect($graph['nodes']);

    // Children carry parent='rack-N'
    $swNode = $nodes->firstWhere('data.id', 'eq-'.$sw->id);
    $rtNode = $nodes->firstWhere('data.id', 'eq-'.$rt->id);
    expect($swNode['data']['parent'] ?? null)->toBe('rack-'.$sw->rack_id)
        ->and($rtNode['data']['parent'] ?? null)->toBe('rack-'.$rt->rack_id);

    // A single compound parent emitted with kind=rack
    $parents = $nodes->where('data.kind', 'rack')->values();
    expect($parents)->toHaveCount(1);
    expect($parents[0]['data']['id'])->toBe('rack-'.$sw->rack_id)
        ->and($parents[0]['data']['rackId'])->toBe($sw->rack_id);
});

it('does not emit any rack parent when groupByRack is false', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    $graph = app(TopologyService::class)->buildGraph(groupByRack: false);

    $parents = collect($graph['nodes'])->where('data.kind', 'rack');
    expect($parents)->toHaveCount(0);

    $swNode = collect($graph['nodes'])->firstWhere('data.id', 'eq-'.$sw->id);
    expect($swNode['data']['parent'] ?? null)->toBeNull();
});

it('skips emitting a rack compound when all children are filtered out', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    // No equipment matches type=firewall in makeWiredScene → no parents emitted.
    $graph = app(TopologyService::class)->buildGraph(types: ['firewall'], groupByRack: true);

    $parents = collect($graph['nodes'])->where('data.kind', 'rack');
    expect($parents)->toHaveCount(0);
});

it('groups unracked equipment into a room compound when groupByRack=true', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);

    $apA = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create([
        'name' => 'AP-A', 'rack_id' => null, 'room_id' => $room->getKey(),
    ]);
    $apB = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create([
        'name' => 'AP-B', 'rack_id' => null, 'room_id' => $room->getKey(),
    ]);

    $graph = app(TopologyService::class)->buildGraph(groupByRack: true);

    $rooms = collect($graph['nodes'])->where('data.kind', 'room')->values();
    expect($rooms)->toHaveCount(1)
        ->and($rooms[0]['data']['id'])->toBe('room-'.$room->getKey())
        ->and($rooms[0]['data']['roomId'])->toBe($room->getKey());

    $aNode = collect($graph['nodes'])->firstWhere('data.id', 'eq-'.$apA->id);
    $bNode = collect($graph['nodes'])->firstWhere('data.id', 'eq-'.$apB->id);
    expect($aNode['data']['parent'])->toBe('room-'.$room->getKey())
        ->and($bNode['data']['parent'])->toBe('room-'.$room->getKey());
});

it('does not emit a room compound for equipment without room_id either', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    Equipment::factory()->create(['rack_id' => null, 'room_id' => null]);

    $graph = app(TopologyService::class)->buildGraph(groupByRack: true);
    expect(collect($graph['nodes'])->where('data.kind', 'room'))->toHaveCount(0);
});

it('filters by room when roomId is provided', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    $otherRoom = Room::factory()->create(['site_id' => $site->getKey()]);
    $rackB = Rack::factory()->create(['room_id' => $otherRoom->getKey()]);
    $rt->update(['rack_id' => $rackB->getKey(), 'room_id' => $otherRoom->getKey()]);

    $originalRoomId = $sw->rack->room_id;
    $graph = app(TopologyService::class)->buildGraph(roomId: $originalRoomId);
    $labels = collect($graph['nodes'])
        ->reject(fn ($n) => in_array($n['data']['kind'] ?? null, ['rack', 'room'], true))
        ->pluck('data.label')->all();
    expect($labels)->toContain('SW-X')->not->toContain('RTR-X');
});

it('room filter also matches unracked equipment with that room_id', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);

    Equipment::factory()->create([
        'name' => 'AP-Solo', 'rack_id' => null, 'room_id' => $room->getKey(),
    ]);
    Equipment::factory()->create([
        'name' => 'OutsideAP', 'rack_id' => null, 'room_id' => null,
    ]);

    $graph = app(TopologyService::class)->buildGraph(roomId: $room->getKey());
    $labels = collect($graph['nodes'])->pluck('data.label')->all();
    expect($labels)->toContain('AP-Solo')->not->toContain('OutsideAP');
});

it('emits a site compound parent and sets parent on equipment when groupBySite is true', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $siteA = Site::factory()->create(['name' => 'Site-A']);
    $siteB = Site::factory()->create(['name' => 'Site-B']);
    $roomA = Room::factory()->create(['site_id' => $siteA->getKey()]);
    $roomB = Room::factory()->create(['site_id' => $siteB->getKey()]);
    $rackA = Rack::factory()->create(['room_id' => $roomA->getKey()]);
    $rackB = Rack::factory()->create(['room_id' => $roomB->getKey()]);

    $eqA = Equipment::factory()->ofType(EquipmentType::Switch)->create([
        'rack_id' => $rackA->getKey(), 'name' => 'SW-A',
    ]);
    $eqB = Equipment::factory()->ofType(EquipmentType::Switch)->create([
        'rack_id' => $rackB->getKey(), 'name' => 'SW-B',
    ]);

    $graph = app(TopologyService::class)->buildGraph(groupBySite: true);
    $nodes = collect($graph['nodes']);

    $aNode = $nodes->firstWhere('data.id', 'eq-'.$eqA->id);
    $bNode = $nodes->firstWhere('data.id', 'eq-'.$eqB->id);
    expect($aNode['data']['parent'] ?? null)->toBe('site-'.$siteA->getKey())
        ->and($bNode['data']['parent'] ?? null)->toBe('site-'.$siteB->getKey());

    $sites = $nodes->where('data.kind', 'site')->values();
    expect($sites)->toHaveCount(2)
        ->and($sites->pluck('data.id')->all())->toEqualCanonicalizing([
            'site-'.$siteA->getKey(), 'site-'.$siteB->getKey(),
        ]);
});

it('nests rack compounds inside site compounds when both groupByRack and groupBySite are true', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    $graph = app(TopologyService::class)->buildGraph(groupByRack: true, groupBySite: true);
    $nodes = collect($graph['nodes']);

    // Equipment still parented to rack compound.
    $swNode = $nodes->firstWhere('data.id', 'eq-'.$sw->id);
    expect($swNode['data']['parent'] ?? null)->toBe('rack-'.$sw->rack_id);

    // Rack compound now parented to site compound.
    $rackNode = $nodes->firstWhere('data.id', 'rack-'.$sw->rack_id);
    expect($rackNode['data']['parent'] ?? null)->toBe('site-'.$site->getKey());

    // Exactly one site compound, no parent of its own.
    $sites = $nodes->where('data.kind', 'site')->values();
    expect($sites)->toHaveCount(1)
        ->and($sites[0]['data']['id'])->toBe('site-'.$site->getKey())
        ->and($sites[0]['data']['parent'] ?? null)->toBeNull();
});

it('groups racked equipment into its room compound when groupByRoom is true', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    $graph = app(TopologyService::class)->buildGraph(groupByRoom: true);
    $nodes = collect($graph['nodes']);

    $roomId = $sw->rack->room_id;

    // Equipment parented directly to the room compound (no rack compound).
    $swNode = $nodes->firstWhere('data.id', 'eq-'.$sw->id);
    expect($swNode['data']['parent'] ?? null)->toBe('room-'.$roomId);

    // Exactly one room compound, top-level, and no rack compounds.
    $rooms = $nodes->where('data.kind', 'room')->values();
    expect($rooms)->toHaveCount(1)
        ->and($rooms[0]['data']['parent'] ?? null)->toBeNull()
        ->and($nodes->where('data.kind', 'rack'))->toHaveCount(0);
});

it('nests rack compounds inside room compounds when both groupByRack and groupByRoom are true', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    $graph = app(TopologyService::class)->buildGraph(groupByRack: true, groupByRoom: true);
    $nodes = collect($graph['nodes']);

    $roomId = $sw->rack->room_id;

    // Equipment → rack compound → room compound (no "non in rack" suffix).
    $swNode = $nodes->firstWhere('data.id', 'eq-'.$sw->id);
    expect($swNode['data']['parent'] ?? null)->toBe('rack-'.$sw->rack_id);

    $rackNode = $nodes->firstWhere('data.id', 'rack-'.$sw->rack_id);
    expect($rackNode['data']['parent'] ?? null)->toBe('room-'.$roomId);

    $roomNode = $nodes->firstWhere('data.id', 'room-'.$roomId);
    expect($roomNode['data']['parent'] ?? null)->toBeNull()
        ->and($roomNode['data']['label'])->not->toContain('non in rack');
});

it('leaves equipment without a resolvable site as top-level when groupBySite is true', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $eq = Equipment::factory()->create([
        'name' => 'Floating', 'rack_id' => null, 'room_id' => null,
    ]);

    $graph = app(TopologyService::class)->buildGraph(groupBySite: true);
    $node = collect($graph['nodes'])->firstWhere('data.id', 'eq-'.$eq->id);

    expect($node['data']['parent'] ?? null)->toBeNull()
        ->and(collect($graph['nodes'])->where('data.kind', 'site'))->toHaveCount(0);
});

it('site filter now also includes unracked equipment with that room.site_id', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $siteA = Site::factory()->create();
    $siteB = Site::factory()->create();
    $roomA = Room::factory()->create(['site_id' => $siteA->getKey()]);
    $roomB = Room::factory()->create(['site_id' => $siteB->getKey()]);

    Equipment::factory()->create([
        'name' => 'AP-A', 'rack_id' => null, 'room_id' => $roomA->getKey(),
    ]);
    Equipment::factory()->create([
        'name' => 'AP-B', 'rack_id' => null, 'room_id' => $roomB->getKey(),
    ]);

    $graph = app(TopologyService::class)->buildGraph(siteId: $siteA->getKey());
    $labels = collect($graph['nodes'])->pluck('data.label')->all();
    expect($labels)->toContain('AP-A')->not->toContain('AP-B');
});
