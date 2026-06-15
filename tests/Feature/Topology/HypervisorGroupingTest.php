<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Services\TopologyService;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

function makeHypervisorScene(): array
{
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create(['name' => 'Sede Roma']);
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);

    $hv = Equipment::factory()->hypervisor()->mountedAt(1, 2)->create([
        'rack_id' => $rack->getKey(),
        'room_id' => $room->getKey(),
        'name' => 'HV-Roma',
    ]);

    $vm1 = Equipment::factory()->virtualMachine($hv)->create(['name' => 'VM-A']);
    $vm2 = Equipment::factory()->virtualMachine($hv)->create(['name' => 'VM-B']);

    return [$tenant, $site, $hv, $vm1, $vm2];
}

it('exposes hostEquipmentId on VM nodes', function (): void {
    [, , $hv, $vm1] = makeHypervisorScene();

    $graph = app(TopologyService::class)->buildGraph();

    $vmNode = collect($graph['nodes'])->firstWhere('data.id', 'eq-'.$vm1->getKey());
    expect($vmNode['data']['type'])->toBe(EquipmentType::VirtualMachine->value)
        ->and($vmNode['data']['hostEquipmentId'])->toBe($hv->getKey());
});

it('wraps VMs and their hypervisor in a host compound when groupByHypervisor is on', function (): void {
    [, , $hv, $vm1, $vm2] = makeHypervisorScene();

    $graph = app(TopologyService::class)->buildGraph(groupByHypervisor: true);

    $hostKey = 'host-'.$hv->getKey();

    $compound = collect($graph['nodes'])->firstWhere('data.id', $hostKey);
    expect($compound)->not->toBeNull()
        ->and($compound['data']['kind'])->toBe('host')
        ->and($compound['data']['label'])->toBe('HV-Roma');

    foreach ([$hv, $vm1, $vm2] as $eq) {
        $node = collect($graph['nodes'])->firstWhere('data.id', 'eq-'.$eq->getKey());
        expect($node['data']['parent'])->toBe($hostKey);
    }
});

it('emits a dashed vnic edge between VM and hypervisor for each backed vNIC', function (): void {
    [, , $hv, $vm1] = makeHypervisorScene();

    $pnic = \App\Models\NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $hv->getKey(),
        'name' => 'eno1',
    ]);
    \App\Models\NetworkInterface::factory()->create([
        'equipment_id' => $vm1->getKey(),
        'name' => 'net0',
        'type' => \App\Enums\InterfaceType::Virtual,
        'backed_by_interface_id' => $pnic->getKey(),
    ]);

    $graph = app(\App\Services\TopologyService::class)->buildGraph();

    $vnicEdges = collect($graph['edges'])->filter(fn ($e) => ($e['data']['kind'] ?? null) === 'vnic')->values();
    expect($vnicEdges)->toHaveCount(1)
        ->and($vnicEdges[0]['data']['source'])->toBe('eq-'.$vm1->getKey())
        ->and($vnicEdges[0]['data']['target'])->toBe('eq-'.$hv->getKey())
        ->and($vnicEdges[0]['data']['fromIface'])->toBe('net0')
        ->and($vnicEdges[0]['data']['toIface'])->toBe('eno1')
        ->and($vnicEdges[0]['data']['fromIfaceId'])->toBeInt()
        ->and($vnicEdges[0]['data']['toIfaceId'])->toBeInt();
});

it('nests the host compound inside the site compound when both groupings are active', function (): void {
    [, $site, $hv] = makeHypervisorScene();

    $graph = app(TopologyService::class)->buildGraph(
        groupBySite: true,
        groupByHypervisor: true,
    );

    $hostKey = 'host-'.$hv->getKey();
    $siteKey = 'site-'.$site->getKey();

    $compound = collect($graph['nodes'])->firstWhere('data.id', $hostKey);
    expect($compound['data']['parent'] ?? null)->toBe($siteKey);
});
