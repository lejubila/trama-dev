<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\WifiAssociation;
use App\Models\WifiNetwork;
use App\Services\TopologyService;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

function makeWifiScene(): array
{
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());
    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);

    $ap1 = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create(['name' => 'AP-1', 'rack_id' => $rack->getKey()]);
    $ap2 = Equipment::factory()->ofType(EquipmentType::AccessPoint)->create(['name' => 'AP-2', 'rack_id' => $rack->getKey()]);
    $client = Equipment::factory()->ofType(EquipmentType::Notebook)->create(['name' => 'NB-1', 'rack_id' => null, 'room_id' => $room->getKey()]);

    $apIface1 = NetworkInterface::factory()->create([
        'equipment_id' => $ap1->getKey(),
        'name' => 'wlan0',
        'media' => InterfaceMedia::Wireless,
    ]);
    $apIface2 = NetworkInterface::factory()->create([
        'equipment_id' => $ap2->getKey(),
        'name' => 'wlan0',
        'media' => InterfaceMedia::Wireless,
    ]);
    $clientIface = NetworkInterface::factory()->create([
        'equipment_id' => $client->getKey(),
        'name' => 'wlan0',
        'media' => InterfaceMedia::Wireless,
    ]);

    return compact('tenant', 'ap1', 'ap2', 'client', 'apIface1', 'apIface2', 'clientIface');
}

it('emits a synthetic SSID node with broadcasters and clients edges', function (): void {
    $s = makeWifiScene();

    $net = WifiNetwork::create([
        'tenant_id' => $s['tenant']->getKey(),
        'ssid' => 'Office',
    ]);
    $net->broadcasters()->attach([$s['apIface1']->id, $s['apIface2']->id]);
    WifiAssociation::create([
        'tenant_id' => $s['tenant']->getKey(),
        'wifi_network_id' => $net->id,
        'client_interface_id' => $s['clientIface']->id,
    ]);

    $graph = app(TopologyService::class)->buildGraph();

    $nodeIds = collect($graph['nodes'])->pluck('data.id')->all();
    expect($nodeIds)->toContain('wifi-'.$net->id);

    $edgeIds = collect($graph['edges'])->pluck('data.id')->all();
    expect($edgeIds)
        ->toContain('wifi-bc-'.$net->id.'-'.$s['apIface1']->id)
        ->toContain('wifi-bc-'.$net->id.'-'.$s['apIface2']->id);

    $assocEdges = collect($graph['edges'])->filter(fn ($e) => str_starts_with($e['data']['id'], 'wifi-as-'));
    expect($assocEdges)->toHaveCount(1);
});

it('skips a Wi-Fi network whose broadcasters and clients are all filtered out', function (): void {
    $s = makeWifiScene();

    $net = WifiNetwork::create([
        'tenant_id' => $s['tenant']->getKey(),
        'ssid' => 'Office',
    ]);
    $net->broadcasters()->attach([$s['apIface1']->id]);

    // Filter to a type that excludes APs → broadcaster equipment vanishes.
    $graph = app(TopologyService::class)->buildGraph(types: ['notebook']);

    $nodeIds = collect($graph['nodes'])->pluck('data.id')->all();
    expect($nodeIds)->not->toContain('wifi-'.$net->id);
});

it('vlan filter drops Wi-Fi networks with mismatching vlan_id', function (): void {
    $s = makeWifiScene();
    $net = WifiNetwork::create([
        'tenant_id' => $s['tenant']->getKey(),
        'ssid' => 'Office',
        'vlan_id' => 100,
    ]);
    $net->broadcasters()->attach([$s['apIface1']->id]);
    $s['apIface1']->update(['vlan_default' => 100]);
    $s['apIface2']->update(['vlan_default' => 200]);

    $graph = app(TopologyService::class)->buildGraph(vlan: 200);

    $nodeIds = collect($graph['nodes'])->pluck('data.id')->all();
    expect($nodeIds)->not->toContain('wifi-'.$net->id);
});
