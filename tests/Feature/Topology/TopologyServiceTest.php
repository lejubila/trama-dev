<?php

declare(strict_types=1);

use App\Actions\Interfaces\CreateKeystonePair;
use App\Enums\EquipmentType;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tag;
use App\Models\Tenant;
use App\Services\ConnectionService;
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

it('emits the interface IDs on each edge so the client can attach port-label overlays', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    $graph = app(TopologyService::class)->buildGraph();

    expect($graph['edges'])->toHaveCount(1);
    $edge = $graph['edges'][0]['data'];
    expect($edge)->toHaveKeys(['fromIfaceId', 'toIfaceId'])
        ->and($edge['fromIfaceId'])->toBeInt()
        ->and($edge['toIfaceId'])->toBeInt()
        ->and($edge['fromIfaceId'])->not->toBe($edge['toIfaceId']);
});

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

it('filters by tags in OR', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    // SW-X → tag A, RTR-X → tag B, a third device → no tag.
    $tagA = Tag::create(['name' => 'core', 'color' => '#00ff00']);
    $tagB = Tag::create(['name' => 'backbone', 'color' => '#0000ff']);
    $sw->tags()->sync([$tagA->getKey()]);
    $rt->tags()->sync([$tagB->getKey()]);
    Equipment::factory()->mountedAt(3, 1)->create([
        'rack_id' => $sw->rack_id,
        'name' => 'SW-UNTAGGED',
    ]);

    $svc = app(TopologyService::class);

    // Single tag → only its device.
    $onlyA = collect($svc->buildGraph(tagIds: [$tagA->getKey()])['nodes'])->pluck('data.label')->all();
    expect($onlyA)->toContain('SW-X')
        ->and($onlyA)->not->toContain('RTR-X')
        ->and($onlyA)->not->toContain('SW-UNTAGGED');

    // Both tags → OR union, never the untagged device.
    $both = collect($svc->buildGraph(tagIds: [$tagA->getKey(), $tagB->getKey()])['nodes'])->pluck('data.label')->all();
    expect($both)->toContain('SW-X', 'RTR-X')
        ->and($both)->not->toContain('SW-UNTAGGED');
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

    // Update VLAN on BOTH endpoints of the cable so it survives the
    // strict per-cable filter; otherwise the orphan-pruning would
    // remove the surviving node as well.
    $sw->interfaces()->update(['vlan_default' => 42]);
    $rt->interfaces()->update(['vlan_default' => 42]);

    $graph = app(TopologyService::class)->buildGraph(vlan: 42);

    $labels = collect($graph['nodes'])->pluck('data.label')->all();
    expect($labels)->toContain('SW-X')
        ->and($labels)->toContain('RTR-X')
        ->and($graph['edges'])->toHaveCount(1);
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

// --------------------------------------------------------------------------
// hidePatchPanels — passthrough collapse
// --------------------------------------------------------------------------

it('collapses a single patch panel hop into one synthetic edge', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $sw = Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'SW1']);
    $pp = Equipment::factory()->ofType(EquipmentType::PatchPanel)->create(['name' => 'PP1']);
    $ap = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create(['name' => 'AP1']);

    $swPort = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $sw->getKey(), 'name' => 'Gi0/1']);
    [$ppFront, $ppRear] = app(CreateKeystonePair::class)
        ->execute($pp, ['name' => 'P-3']);
    $apPort = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $ap->getKey(), 'name' => 'eth0']);

    app(ConnectionService::class)->connect($swPort, $ppFront, ['cable_type' => 'utp_cat6']);
    app(ConnectionService::class)->connect($ppRear, $apPort, ['cable_type' => 'utp_cat6']);

    $graph = app(TopologyService::class)->buildGraph(hidePatchPanels: true);
    $nodeIds = collect($graph['nodes'])->pluck('data.id')->all();

    expect($nodeIds)->toContain('eq-'.$sw->getKey());
    expect($nodeIds)->toContain('eq-'.$ap->getKey());
    expect($nodeIds)->not->toContain('eq-'.$pp->getKey());

    $edges = collect($graph['edges']);
    expect($edges)->toHaveCount(1);
    $e = $edges->first()['data'];
    $endpoints = [$e['source'], $e['target']];
    sort($endpoints);
    $expected = ['eq-'.min($sw->getKey(), $ap->getKey()), 'eq-'.max($sw->getKey(), $ap->getKey())];
    expect($endpoints)->toEqual($expected);
    expect($e['passthrough'])->toBeTrue();
    expect($e['transit'])->toEqual(['PP1.P-3']);
    expect($e['label'])->toEqual('via PP1.P-3');
});

it('hides wall outlets alongside patch panels when hidePatchPanels is set', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $sw = Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'SW1']);
    $pp = Equipment::factory()->ofType(EquipmentType::PatchPanel)->create(['name' => 'PP1']);
    $wo = Equipment::factory()->ofType(EquipmentType::WallOutlet)->create(['name' => 'WO1']);
    $ap = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create(['name' => 'AP1']);

    $swPort = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $sw->getKey(), 'name' => 'Gi0/1']);
    [$ppFront, $ppRear] = app(CreateKeystonePair::class)->execute($pp, ['name' => 'P3']);
    [$woFront, $woRear] = app(CreateKeystonePair::class)->execute($wo, ['name' => 'A']);
    $apPort = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $ap->getKey(), 'name' => 'eth0']);

    $svc = app(ConnectionService::class);
    $svc->connect($swPort, $ppFront, ['cable_type' => 'utp_cat6']);
    $svc->connect($ppRear, $woRear, ['cable_type' => 'utp_cat6']);
    $svc->connect($woFront, $apPort, ['cable_type' => 'utp_cat6']);

    $graph = app(TopologyService::class)->buildGraph(hidePatchPanels: true);
    $nodeIds = collect($graph['nodes'])->pluck('data.id')->all();

    expect($nodeIds)->toContain('eq-'.$sw->getKey());
    expect($nodeIds)->toContain('eq-'.$ap->getKey());
    expect($nodeIds)->not->toContain('eq-'.$pp->getKey());
    expect($nodeIds)->not->toContain('eq-'.$wo->getKey());

    $edges = collect($graph['edges']);
    expect($edges)->toHaveCount(1);
    $e = $edges->first()['data'];
    expect($e['passthrough'])->toBeTrue();
    expect($e['transit'])->toEqualCanonicalizing(['PP1.P3', 'WO1.A']);
});

it('collapses a multi-hop chain through two patch panels', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $sw = Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'SW1']);
    $pp1 = Equipment::factory()->ofType(EquipmentType::PatchPanel)->create(['name' => 'PP1']);
    $pp2 = Equipment::factory()->ofType(EquipmentType::PatchPanel)->create(['name' => 'PP2']);
    $ap = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create(['name' => 'AP1']);

    $swPort = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $sw->getKey(), 'name' => 'Gi0/1']);
    [$pp1Front, $pp1Rear] = app(CreateKeystonePair::class)->execute($pp1, ['name' => 'P3']);
    [$pp2Front, $pp2Rear] = app(CreateKeystonePair::class)->execute($pp2, ['name' => 'Q7']);
    $apPort = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $ap->getKey(), 'name' => 'eth0']);

    $svc = app(ConnectionService::class);
    $svc->connect($swPort, $pp1Front, ['cable_type' => 'utp_cat6']);
    $svc->connect($pp1Rear, $pp2Rear, ['cable_type' => 'utp_cat6']);       // dorsale rear↔rear
    $svc->connect($pp2Front, $apPort, ['cable_type' => 'utp_cat6']);

    $graph = app(TopologyService::class)->buildGraph(hidePatchPanels: true);

    $edges = collect($graph['edges']);
    expect($edges)->toHaveCount(1);
    $e = $edges->first()['data'];
    expect($e['passthrough'])->toBeTrue();
    expect($e['transit'])->toEqualCanonicalizing(['PP1.P3', 'PP2.Q7']);
});

it('drops a connection whose chain is broken (rear not cabled)', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $sw = Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'SW1']);
    $pp = Equipment::factory()->ofType(EquipmentType::PatchPanel)->create(['name' => 'PP1']);

    $swPort = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $sw->getKey(), 'name' => 'Gi0/1']);
    [$ppFront] = app(CreateKeystonePair::class)->execute($pp, ['name' => 'P3']);

    app(ConnectionService::class)->connect($swPort, $ppFront, ['cable_type' => 'utp_cat6']);

    $graph = app(TopologyService::class)->buildGraph(hidePatchPanels: true);

    expect($graph['edges'])->toEqual([]);
});

it('leaves non-passthrough connections untouched and excludes only patch panels', function (): void {
    [$tenant, $site, $sw, $rt] = makeWiredScene();

    // Add a patch panel completely unconnected — should disappear from the
    // node list but the existing SW-RT direct cable should stay.
    $pp = Equipment::factory()->ofType(EquipmentType::PatchPanel)->create(['name' => 'LONELY-PP']);

    $graph = app(TopologyService::class)->buildGraph(hidePatchPanels: true);
    $nodeIds = collect($graph['nodes'])->pluck('data.id')->all();

    expect($nodeIds)->toContain('eq-'.$sw->getKey());
    expect($nodeIds)->toContain('eq-'.$rt->getKey());
    expect($nodeIds)->not->toContain('eq-'.$pp->getKey());

    $edges = collect($graph['edges']);
    expect($edges)->toHaveCount(1);
    $e = $edges->first()['data'];
    expect($e['passthrough'] ?? false)->toBeFalse();
});

// --------------------------------------------------------------------------
// Strict per-cable VLAN filter + transparent passthrough
// --------------------------------------------------------------------------

it('emits a VLAN-filtered edge only when both endpoints declare the VLAN', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $sw = Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'SW-T']);
    $rt = Equipment::factory()->ofType(EquipmentType::Router)->create(['name' => 'RT-T']);

    $a = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $sw->getKey(), 'name' => 'Gi0/1',
        'vlan_mode' => 'trunk', 'vlan_default' => 1, 'vlans_allowed' => [1, 60],
    ]);
    $b = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $rt->getKey(), 'name' => 'Gi0/0',
        'vlan_mode' => 'trunk', 'vlan_default' => 1, 'vlans_allowed' => [1, 60],
    ]);
    Connection::create([
        'tenant_id' => $tenant->getKey(), 'from_interface_id' => $a->getKey(),
        'to_interface_id' => $b->getKey(), 'cable_type' => 'utp_cat6', 'status' => 'active',
    ]);

    expect(app(TopologyService::class)->buildGraph(vlan: 60)['edges'])->toHaveCount(1);
});

it('drops a cable for VLAN N when one endpoint does not handle that VLAN', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    // Equipment passes the node filter via a non-uplink VLAN-60 interface.
    $sw = Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'SW-CB']);
    $rt = Equipment::factory()->ofType(EquipmentType::Router)->create(['name' => 'RT-CB']);

    // Sub-interfaces in VLAN 60 (not the cabled ones), so equipment is "visible".
    NetworkInterface::factory()->create([
        'equipment_id' => $sw->getKey(), 'name' => 'sw.60',
        'type' => 'virtual', 'media' => 'copper',
        'vlan_mode' => 'access', 'vlan_default' => 60,
    ]);
    NetworkInterface::factory()->create([
        'equipment_id' => $rt->getKey(), 'name' => 'rt.60',
        'type' => 'virtual', 'media' => 'copper',
        'vlan_mode' => 'access', 'vlan_default' => 60,
    ]);

    // Physical uplink in VLAN 1 (no allowed list) → strict filter rejects it for VLAN 60.
    $a = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $sw->getKey(), 'name' => 'Gi0/1',
        'vlan_mode' => 'access', 'vlan_default' => 1, 'vlans_allowed' => null,
    ]);
    $b = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $rt->getKey(), 'name' => 'Gi0/0',
        'vlan_mode' => 'access', 'vlan_default' => 1, 'vlans_allowed' => null,
    ]);
    Connection::create([
        'tenant_id' => $tenant->getKey(), 'from_interface_id' => $a->getKey(),
        'to_interface_id' => $b->getKey(), 'cable_type' => 'utp_cat6', 'status' => 'active',
    ]);

    $graph = app(TopologyService::class)->buildGraph(vlan: 60);

    // The cable is dropped because endpoints don't handle VLAN 60.
    // Pruning orphans then removes both equipment nodes — they have a
    // VLAN-60 sub-interface but no VLAN-60-carrying cable reaches them.
    expect($graph['edges'])->toEqual([]);
    expect($graph['nodes'])->toEqual([]);
});

it('keeps the cable when an endpoint is transparent (any VLAN passes)', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $sw = Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'SW-T2']);
    $hub = Equipment::factory()->ofType(EquipmentType::Other)->create(['name' => 'HUB']);

    $a = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $sw->getKey(), 'name' => 'Gi0/1',
        'vlan_mode' => 'trunk', 'vlan_default' => 1, 'vlans_allowed' => [1, 60],
    ]);
    $b = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $hub->getKey(), 'name' => 'uplink',
        'vlan_mode' => 'transparent', 'vlan_default' => null, 'vlans_allowed' => null,
    ]);
    Connection::create([
        'tenant_id' => $tenant->getKey(), 'from_interface_id' => $a->getKey(),
        'to_interface_id' => $b->getKey(), 'cable_type' => 'utp_cat6', 'status' => 'active',
    ]);

    expect(app(TopologyService::class)->buildGraph(vlan: 60)['edges'])->toHaveCount(1);
});

it('treats keystone interfaces on patch panels as VLAN-transparent', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $sw = Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'SW-PP']);
    $pp = Equipment::factory()->ofType(EquipmentType::PatchPanel)->create(['name' => 'PP-K']);

    $swPort = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $sw->getKey(), 'name' => 'Gi0/1',
        'vlan_mode' => 'trunk', 'vlan_default' => 1, 'vlans_allowed' => [1, 60],
    ]);
    [$ppFront, $ppRear] = app(CreateKeystonePair::class)
        ->execute($pp, ['name' => 'P3']);

    app(ConnectionService::class)->connect($swPort, $ppFront, ['cable_type' => 'utp_cat6']);

    // Both endpoints handle VLAN 60: switch via vlans_allowed, keystone via
    // patch-panel passthrough.
    expect(app(TopologyService::class)->buildGraph(vlan: 60)['edges'])->toHaveCount(1);
});

it('prunes equipment nodes that have no VLAN-compatible incident edge', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    // Scenario "Casa Bigagli" semplificato: AP con sotto-interfaccia
    // VLAN 60 (vlan_default=60) ma cavo fisico in VLAN 1 senza vlans_allowed.
    $ap = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create(['name' => 'AP-ORPHAN']);
    $sw = Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'SW-ORPHAN']);

    // Sub-interfaces VLAN 60 (rendono "candidate" entrambe le equipment al filtro VLAN).
    NetworkInterface::factory()->create([
        'equipment_id' => $ap->getKey(), 'name' => 'wl0.60',
        'type' => 'virtual', 'media' => 'wireless',
        'vlan_mode' => 'access', 'vlan_default' => 60,
    ]);
    NetworkInterface::factory()->create([
        'equipment_id' => $sw->getKey(), 'name' => 'p1.60',
        'type' => 'virtual', 'media' => 'copper',
        'vlan_mode' => 'access', 'vlan_default' => 60,
    ]);

    // Cavo fisico in VLAN 1, vlans_allowed=null (caso reale).
    $a = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $ap->getKey(), 'name' => 'eth0',
        'vlan_mode' => 'access', 'vlan_default' => 1, 'vlans_allowed' => null,
    ]);
    $b = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $sw->getKey(), 'name' => 'p19',
        'vlan_mode' => 'access', 'vlan_default' => 1, 'vlans_allowed' => null,
    ]);
    Connection::create([
        'tenant_id' => $tenant->getKey(), 'from_interface_id' => $a->getKey(),
        'to_interface_id' => $b->getKey(), 'cable_type' => 'utp_cat6', 'status' => 'active',
    ]);

    $graph = app(TopologyService::class)->buildGraph(vlan: 60);

    // Edge dropped (strict VLAN filter) → both equipment orphaned →
    // pruning removes them from nodes too.
    expect($graph['edges'])->toEqual([]);
    expect($graph['nodes'])->toEqual([]);
});

it('keeps both endpoints when the cable explicitly carries the VLAN', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $ap = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create(['name' => 'AP-OK']);
    $sw = Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'SW-OK']);

    $a = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $ap->getKey(), 'name' => 'eth0',
        'vlan_mode' => 'trunk', 'vlan_default' => 1, 'vlans_allowed' => [1, 60],
    ]);
    $b = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $sw->getKey(), 'name' => 'p19',
        'vlan_mode' => 'trunk', 'vlan_default' => 1, 'vlans_allowed' => [1, 60],
    ]);
    Connection::create([
        'tenant_id' => $tenant->getKey(), 'from_interface_id' => $a->getKey(),
        'to_interface_id' => $b->getKey(), 'cable_type' => 'utp_cat6', 'status' => 'active',
    ]);

    $graph = app(TopologyService::class)->buildGraph(vlan: 60);
    $nodeIds = collect($graph['nodes'])->pluck('data.id')->all();

    expect($graph['edges'])->toHaveCount(1);
    expect($nodeIds)->toContain('eq-'.$ap->getKey());
    expect($nodeIds)->toContain('eq-'.$sw->getKey());
});

it('prunes empty compound parents after the VLAN filter removes their children', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);

    // Orphaned VLAN 60 device in rack: passes equipment filter, no edge.
    $ap = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create([
        'name' => 'AP-LONE',
        'rack_id' => $rack->getKey(),
    ]);
    NetworkInterface::factory()->create([
        'equipment_id' => $ap->getKey(), 'name' => 'wl0.60',
        'type' => 'virtual', 'media' => 'wireless',
        'vlan_mode' => 'access', 'vlan_default' => 60,
    ]);

    $graph = app(TopologyService::class)->buildGraph(vlan: 60, groupByRack: true);

    // Both the equipment node and the rack compound disappear because
    // the rack has no remaining children.
    $ids = collect($graph['nodes'])->pluck('data.id')->all();
    expect($ids)->not->toContain('eq-'.$ap->getKey());
    expect($ids)->not->toContain('rack-'.$rack->getKey());
});
