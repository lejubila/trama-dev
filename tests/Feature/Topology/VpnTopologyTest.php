<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Enums\VpnProtocol;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\VpnRemoteAccess;
use App\Models\VpnRemoteAccessClient;
use App\Models\VpnSiteToSite;
use App\Services\TopologyService;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

function makeVpnScene(): array
{
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());
    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);

    $fwA = Equipment::factory()->ofType(EquipmentType::Firewall)->create(['name' => 'FW-MI', 'rack_id' => $rack->getKey()]);
    $fwB = Equipment::factory()->ofType(EquipmentType::Firewall)->create(['name' => 'FW-RM', 'rack_id' => $rack->getKey()]);
    $notebook = Equipment::factory()->ofType(EquipmentType::Notebook)->create(['name' => 'NB', 'rack_id' => null, 'room_id' => $room->getKey()]);

    $fwAIface = NetworkInterface::factory()->create([
        'equipment_id' => $fwA->getKey(),
        'name' => 'wan0',
        'media' => InterfaceMedia::Copper,
    ]);
    $fwBIface = NetworkInterface::factory()->create([
        'equipment_id' => $fwB->getKey(),
        'name' => 'wan0',
        'media' => InterfaceMedia::Copper,
    ]);
    $nbIface = NetworkInterface::factory()->create([
        'equipment_id' => $notebook->getKey(),
        'name' => 'vpn0',
        'media' => InterfaceMedia::Virtual,
    ]);

    return compact('tenant', 'fwA', 'fwB', 'notebook', 'fwAIface', 'fwBIface', 'nbIface');
}

it('emits a remote-access VPN node with firewall and client edges', function (): void {
    $s = makeVpnScene();
    $vpn = VpnRemoteAccess::create([
        'tenant_id' => $s['tenant']->getKey(),
        'name' => 'Office RA',
        'protocol' => VpnProtocol::WireGuard->value,
        'firewall_interface_id' => $s['fwAIface']->id,
    ]);
    VpnRemoteAccessClient::create([
        'tenant_id' => $s['tenant']->getKey(),
        'vpn_remote_access_id' => $vpn->id,
        'client_interface_id' => $s['nbIface']->id,
    ]);

    $graph = app(TopologyService::class)->buildGraph();
    $nodeIds = collect($graph['nodes'])->pluck('data.id')->all();
    $edgeIds = collect($graph['edges'])->pluck('data.id')->all();

    expect($nodeIds)->toContain('vpn-ra-'.$vpn->id)
        ->and($edgeIds)->toContain('vpn-ra-fw-'.$vpn->id)
        ->and(collect($edgeIds)->filter(fn ($id) => str_starts_with($id, 'vpn-ra-cli-')))->toHaveCount(1);
});

it('emits a site-to-site VPN node with edges to both firewalls', function (): void {
    $s = makeVpnScene();
    $vpn = VpnSiteToSite::create([
        'tenant_id' => $s['tenant']->getKey(),
        'name' => 'MI-RM Tunnel',
        'protocol' => VpnProtocol::Ipsec->value,
        'endpoint_a_interface_id' => $s['fwAIface']->id,
        'endpoint_b_interface_id' => $s['fwBIface']->id,
    ]);

    $graph = app(TopologyService::class)->buildGraph();
    $nodeIds = collect($graph['nodes'])->pluck('data.id')->all();
    $edgeIds = collect($graph['edges'])->pluck('data.id')->all();

    expect($nodeIds)->toContain('vpn-stos-'.$vpn->id)
        ->and($edgeIds)
            ->toContain('vpn-stos-a-'.$vpn->id)
            ->toContain('vpn-stos-b-'.$vpn->id);
});

it('vlan filter drops a remote-access VPN with mismatched routed_vlans', function (): void {
    $s = makeVpnScene();
    $vpn = VpnRemoteAccess::create([
        'tenant_id' => $s['tenant']->getKey(),
        'name' => 'Office RA',
        'protocol' => VpnProtocol::WireGuard->value,
        'firewall_interface_id' => $s['fwAIface']->id,
        'routed_vlans' => [10, 20],
    ]);
    $s['fwAIface']->update(['vlan_default' => 30]);

    $graph = app(TopologyService::class)->buildGraph(vlan: 30);
    $nodeIds = collect($graph['nodes'])->pluck('data.id')->all();
    expect($nodeIds)->not->toContain('vpn-ra-'.$vpn->id);
});

it('vlan filter keeps a site-to-site VPN matching either side', function (): void {
    $s = makeVpnScene();
    $vpn = VpnSiteToSite::create([
        'tenant_id' => $s['tenant']->getKey(),
        'name' => 'MI-RM',
        'protocol' => VpnProtocol::Ipsec->value,
        'endpoint_a_interface_id' => $s['fwAIface']->id,
        'endpoint_b_interface_id' => $s['fwBIface']->id,
        'routed_vlans_a' => [10],
        'routed_vlans_b' => [100],
    ]);
    // Both firewalls also need an interface that carries the filtered VLAN to
    // survive the equipment-level VLAN filter (in real life the WAN interface
    // is unlikely to be the only one a firewall hosts).
    $s['fwAIface']->update(['vlan_default' => 100]);
    $s['fwBIface']->update(['vlan_default' => 100]);

    $graph = app(TopologyService::class)->buildGraph(vlan: 100);
    $nodeIds = collect($graph['nodes'])->pluck('data.id')->all();
    expect($nodeIds)->toContain('vpn-stos-'.$vpn->id);
});
