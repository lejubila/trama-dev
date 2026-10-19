<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\VpnRemoteAccess;
use App\Models\VpnRemoteAccessClient;
use App\Models\VpnSiteToSite;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

it('creates, reads, updates and deletes a site-to-site VPN', function (): void {
    $u = apiUser('admin');
    $a = deviceWithPort($u['tenant'], EquipmentType::Firewall, InterfaceMedia::Copper, 'FW-A');
    $b = deviceWithPort($u['tenant'], EquipmentType::Firewall, InterfaceMedia::Copper, 'FW-B');
    $h = apiHeaders($u['token'], $u['tenant']->getKey());

    $id = $this->withHeaders($h)->postJson('/api/v1/vpn/site-to-site', [
        'name' => 'Sede-Magazzino',
        'protocol' => 'ipsec',
        'endpoint_a_interface_id' => $a->id,
        'endpoint_b_interface_id' => $b->id,
        'routed_vlans_a' => [10, 20],
        'routed_networks_b' => ['192.168.50.0/24'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.attributes.routed_vlans_a', [10, 20])
        ->assertJsonPath('data.relationships.endpoint_a.id', $a->id)
        ->json('data.id');

    $this->withHeaders($h)->getJson('/api/v1/vpn/site-to-site')->assertOk()->assertJsonCount(1, 'data');
    $this->withHeaders($h)->patchJson("/api/v1/vpn/site-to-site/{$id}", ['protocol' => 'wireguard'])
        ->assertOk()->assertJsonPath('data.attributes.protocol', 'wireguard');
    // Partial update keeps the other endpoint, so pointing B at A is refused.
    $this->withHeaders($h)->patchJson("/api/v1/vpn/site-to-site/{$id}", ['endpoint_b_interface_id' => $a->id])
        ->assertUnprocessable()->assertJsonValidationErrors('endpoint_b_interface_id');

    $this->withHeaders($h)->deleteJson("/api/v1/vpn/site-to-site/{$id}")->assertNoContent();
    expect(VpnSiteToSite::query()->whereKey($id)->exists())->toBeFalse();
});

it('requires firewall interfaces and valid networks for site-to-site', function (): void {
    $u = apiUser('admin');
    $fw = deviceWithPort($u['tenant'], EquipmentType::Firewall, InterfaceMedia::Copper, 'FW');
    $sw = deviceWithPort($u['tenant'], EquipmentType::Switch, InterfaceMedia::Copper, 'SW');
    $h = apiHeaders($u['token'], $u['tenant']->getKey());
    $base = ['name' => 'T', 'protocol' => 'ipsec', 'endpoint_a_interface_id' => $fw->id];

    $this->withHeaders($h)->postJson('/api/v1/vpn/site-to-site', [...$base, 'endpoint_b_interface_id' => $sw->id])
        ->assertUnprocessable()->assertJsonValidationErrors('endpoint_b_interface_id');
    $this->withHeaders($h)->postJson('/api/v1/vpn/site-to-site', [...$base, 'endpoint_b_interface_id' => $fw->id])
        ->assertUnprocessable()->assertJsonValidationErrors('endpoint_b_interface_id');
    $this->withHeaders($h)->postJson('/api/v1/vpn/site-to-site', [...$base, 'endpoint_b_interface_id' => $fw->id, 'routed_networks_a' => ['10.0.0.0/33'], 'protocol' => 'nope'])
        ->assertUnprocessable()->assertJsonValidationErrors(['routed_networks_a.0', 'protocol']);
});

it('manages a remote-access VPN and its clients', function (): void {
    $u = apiUser('admin');
    $wan = deviceWithPort($u['tenant'], EquipmentType::Firewall, InterfaceMedia::Copper, 'FW');
    TenantContext::setId($u['tenant']->getKey());
    $laptop = Equipment::factory()->create(['type' => EquipmentType::Other, 'name' => 'Laptop']);
    TenantContext::clear();
    $h = apiHeaders($u['token'], $u['tenant']->getKey());

    $id = $this->withHeaders($h)->postJson('/api/v1/vpn/remote-access', [
        'name' => 'Smart working',
        'protocol' => 'wireguard',
        'firewall_interface_id' => $wan->id,
        'routing_mode' => 'routed',
        'client_network_cidr' => '10.8.0.0/24',
        'routed_vlans' => [10],
    ])
        ->assertCreated()
        ->assertJsonPath('data.attributes.client_network_cidr', '10.8.0.0/24')
        ->json('data.id');

    $client = $this->withHeaders($h)->postJson("/api/v1/vpn/remote-access/{$id}/clients", ['equipment_id' => $laptop->id, 'username' => 'mrossi'])
        ->assertCreated()
        ->assertJsonPath('data.attributes.client_interface_name', 'vpn0')
        ->assertJsonPath('data.attributes.username', 'mrossi')
        ->json('data.id');

    $this->withHeaders($h)->postJson("/api/v1/vpn/remote-access/{$id}/clients", ['equipment_id' => $laptop->id])
        ->assertUnprocessable()->assertJsonValidationErrors('equipment_id');

    $this->withHeaders($h)->getJson("/api/v1/vpn/remote-access/{$id}")
        ->assertOk()
        ->assertJsonPath('data.attributes.clients_count', 1)
        ->assertJsonPath('data.relationships.clients.0.attributes.username', 'mrossi');

    $this->withHeaders($h)->deleteJson("/api/v1/vpn/remote-access/{$id}/clients/{$client}")->assertNoContent();
    expect(VpnRemoteAccessClient::withoutGlobalScopes()->count())->toBe(0);

    $this->withHeaders($h)->deleteJson("/api/v1/vpn/remote-access/{$id}")->assertNoContent();
    expect(VpnRemoteAccess::query()->whereKey($id)->exists())->toBeFalse();
});

it('validates the remote-access firewall interface and CIDR', function (): void {
    $u = apiUser('admin');
    $sw = deviceWithPort($u['tenant'], EquipmentType::Switch, InterfaceMedia::Copper, 'SW');
    $h = apiHeaders($u['token'], $u['tenant']->getKey());

    $this->withHeaders($h)->postJson('/api/v1/vpn/remote-access', [
        'name' => 'X', 'protocol' => 'openvpn', 'firewall_interface_id' => $sw->id, 'routing_mode' => 'routed',
    ])->assertUnprocessable()->assertJsonValidationErrors('firewall_interface_id');

    $this->withHeaders($h)->postJson('/api/v1/vpn/remote-access', [
        'name' => 'X', 'protocol' => 'openvpn', 'firewall_interface_id' => $sw->id, 'routing_mode' => 'sideways', 'client_network_cidr' => '10.8.0.0',
    ])->assertUnprocessable()->assertJsonValidationErrors(['routing_mode', 'client_network_cidr']);
});

it('isolates VPNs per tenant and needs vpn abilities', function (): void {
    $other = Tenant::factory()->create();
    $fw = deviceWithPort($other, EquipmentType::Firewall, InterfaceMedia::Copper, 'FW');
    TenantContext::setId($other->getKey());
    $foreign = VpnRemoteAccess::factory()->create(['firewall_interface_id' => $fw->id, 'routing_mode' => 'routed']);
    TenantContext::clear();

    ['tenant' => $tenant, 'token' => $token] = tokenWith(['vpn:read']);
    $h = apiHeaders($token, $tenant->getKey());

    $this->withHeaders($h)->getJson('/api/v1/vpn/remote-access')->assertOk()->assertJsonCount(0, 'data');
    $this->withHeaders($h)->getJson("/api/v1/vpn/remote-access/{$foreign->id}")->assertNotFound();
    $this->withHeaders($h)->postJson('/api/v1/vpn/site-to-site', [])
        ->assertForbidden()->assertJson(['error' => 'Token lacks ability: vpn:create']);
    $this->withHeaders($h)->getJson('/api/v1/wifi-networks')
        ->assertForbidden()->assertJson(['error' => 'Token lacks ability: wifi:read']);
});
