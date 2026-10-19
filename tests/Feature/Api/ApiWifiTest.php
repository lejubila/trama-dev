<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\WifiAssociation;
use App\Models\WifiNetwork;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

it('creates a Wi-Fi network with broadcasters and reads it back', function (): void {
    $u = apiUser('admin');
    $radio = deviceWithPort($u['tenant'], EquipmentType::AccessPoint, InterfaceMedia::Wireless, 'AP-1');
    $h = apiHeaders($u['token'], $u['tenant']->getKey());

    $id = $this->withHeaders($h)->postJson('/api/v1/wifi-networks', [
        'ssid' => 'Ufficio',
        'security_type' => 'wpa3',
        'vlan_id' => 20,
        'broadcaster_interface_ids' => [$radio->id],
    ])
        ->assertCreated()
        ->assertJsonPath('data.attributes.ssid', 'Ufficio')
        ->assertJsonPath('data.attributes.broadcaster_interface_ids', [$radio->id])
        ->json('data.id');

    $this->withHeaders($h)->getJson('/api/v1/wifi-networks')->assertOk()->assertJsonCount(1, 'data');
    $this->withHeaders($h)->getJson("/api/v1/wifi-networks/{$id}")
        ->assertOk()
        ->assertJsonPath('data.attributes.vlan_id', 20)
        ->assertJsonPath('data.relationships.broadcasters.0.id', $radio->id);
});

it('rejects broadcasters that are not AP/controller radios', function (): void {
    $u = apiUser('admin');
    $copper = deviceWithPort($u['tenant'], EquipmentType::AccessPoint, InterfaceMedia::Copper);
    $switchRadio = deviceWithPort($u['tenant'], EquipmentType::Switch, InterfaceMedia::Wireless, 'SW');

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson('/api/v1/wifi-networks', ['ssid' => 'X', 'broadcaster_interface_ids' => [$copper->id, $switchRadio->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('broadcaster_interface_ids');

    expect(WifiNetwork::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects broadcasters and sites of another tenant', function (): void {
    $u = apiUser('admin');
    $other = Tenant::factory()->create();
    $foreign = deviceWithPort($other, EquipmentType::AccessPoint, InterfaceMedia::Wireless);
    $h = apiHeaders($u['token'], $u['tenant']->getKey());

    $this->withHeaders($h)->postJson('/api/v1/wifi-networks', ['ssid' => 'X', 'broadcaster_interface_ids' => [$foreign->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('broadcaster_interface_ids');
});

it('updates a network and syncs broadcasters only when sent', function (): void {
    $u = apiUser('admin');
    $r1 = deviceWithPort($u['tenant'], EquipmentType::AccessPoint, InterfaceMedia::Wireless, 'AP-1');
    $r2 = deviceWithPort($u['tenant'], EquipmentType::Controller, InterfaceMedia::Wireless, 'WLC');
    $h = apiHeaders($u['token'], $u['tenant']->getKey());
    $id = $this->withHeaders($h)->postJson('/api/v1/wifi-networks', ['ssid' => 'A', 'broadcaster_interface_ids' => [$r1->id]])->json('data.id');

    $this->withHeaders($h)->patchJson("/api/v1/wifi-networks/{$id}", ['ssid' => 'B'])
        ->assertOk()
        ->assertJsonPath('data.attributes.ssid', 'B')
        ->assertJsonPath('data.attributes.broadcaster_interface_ids', [$r1->id]);

    $this->withHeaders($h)->patchJson("/api/v1/wifi-networks/{$id}", ['broadcaster_interface_ids' => [$r2->id]])
        ->assertOk()
        ->assertJsonPath('data.attributes.broadcaster_interface_ids', [$r2->id]);
});

it('deletes a network', function (): void {
    $u = apiUser('admin');
    $h = apiHeaders($u['token'], $u['tenant']->getKey());
    $id = $this->withHeaders($h)->postJson('/api/v1/wifi-networks', ['ssid' => 'Tmp'])->json('data.id');

    $this->withHeaders($h)->deleteJson("/api/v1/wifi-networks/{$id}")->assertNoContent();
    expect(WifiNetwork::query()->whereKey($id)->exists())->toBeFalse();
});

it('attaches a client creating its wlan interface, refuses duplicates, detaches', function (): void {
    $u = apiUser('admin');
    $radio = deviceWithPort($u['tenant'], EquipmentType::AccessPoint, InterfaceMedia::Wireless, 'AP-1');
    TenantContext::setId($u['tenant']->getKey());
    $laptop = Equipment::factory()->create(['type' => EquipmentType::Other, 'name' => 'Laptop']);
    TenantContext::clear();
    $h = apiHeaders($u['token'], $u['tenant']->getKey());
    $net = $this->withHeaders($h)->postJson('/api/v1/wifi-networks', ['ssid' => 'A', 'broadcaster_interface_ids' => [$radio->id]])->json('data.id');

    $assoc = $this->withHeaders($h)->postJson("/api/v1/wifi-networks/{$net}/clients", [
        'equipment_id' => $laptop->id,
        'preferred_broadcaster_interface_id' => $radio->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.attributes.client_equipment_id', $laptop->id)
        ->assertJsonPath('data.attributes.client_interface_name', 'wlan0')
        ->json('data.id');

    $this->withHeaders($h)->postJson("/api/v1/wifi-networks/{$net}/clients", ['equipment_id' => $laptop->id])
        ->assertUnprocessable()->assertJsonValidationErrors('equipment_id');

    $this->withHeaders($h)->getJson("/api/v1/wifi-networks/{$net}/clients")->assertOk()->assertJsonCount(1, 'data');

    $this->withHeaders($h)->deleteJson("/api/v1/wifi-networks/{$net}/clients/{$assoc}")->assertNoContent();
    expect(WifiAssociation::withoutGlobalScopes()->count())->toBe(0);
});

it('validates the client interface and preferred broadcaster', function (): void {
    $u = apiUser('admin');
    $copper = deviceWithPort($u['tenant'], EquipmentType::Other, InterfaceMedia::Copper, 'PC');
    $h = apiHeaders($u['token'], $u['tenant']->getKey());
    $net = $this->withHeaders($h)->postJson('/api/v1/wifi-networks', ['ssid' => 'A'])->json('data.id');

    $this->withHeaders($h)->postJson("/api/v1/wifi-networks/{$net}/clients", ['equipment_id' => $copper->equipment_id, 'interface_id' => $copper->id])
        ->assertUnprocessable()->assertJsonValidationErrors('interface_id');
    $this->withHeaders($h)->postJson("/api/v1/wifi-networks/{$net}/clients", ['equipment_id' => $copper->equipment_id, 'preferred_broadcaster_interface_id' => $copper->id])
        ->assertUnprocessable()->assertJsonValidationErrors('preferred_broadcaster_interface_id');
    $this->withHeaders($h)->postJson("/api/v1/wifi-networks/{$net}/clients", ['equipment_id' => 999999])
        ->assertUnprocessable()->assertJsonValidationErrors('equipment_id');
});

it('does not detach a client through another network', function (): void {
    $u = apiUser('admin');
    TenantContext::setId($u['tenant']->getKey());
    $laptop = Equipment::factory()->create(['type' => EquipmentType::Other]);
    TenantContext::clear();
    $h = apiHeaders($u['token'], $u['tenant']->getKey());
    $a = $this->withHeaders($h)->postJson('/api/v1/wifi-networks', ['ssid' => 'A'])->json('data.id');
    $b = $this->withHeaders($h)->postJson('/api/v1/wifi-networks', ['ssid' => 'B'])->json('data.id');
    $assoc = $this->withHeaders($h)->postJson("/api/v1/wifi-networks/{$a}/clients", ['equipment_id' => $laptop->id])->json('data.id');

    $this->withHeaders($h)->deleteJson("/api/v1/wifi-networks/{$b}/clients/{$assoc}")->assertNotFound();
});

it('hides networks of other tenants and forbids cliente writes', function (): void {
    $u = apiUser('cliente');
    $other = Tenant::factory()->create();
    TenantContext::setId($other->getKey());
    $foreign = WifiNetwork::factory()->create();
    TenantContext::setId($u['tenant']->getKey());
    WifiNetwork::factory()->create();
    TenantContext::clear();
    $h = apiHeaders($u['token'], $u['tenant']->getKey());

    $this->withHeaders($h)->getJson('/api/v1/wifi-networks')->assertOk()->assertJsonCount(1, 'data');
    $this->withHeaders($h)->getJson("/api/v1/wifi-networks/{$foreign->id}")->assertNotFound();
    $this->withHeaders($h)->postJson('/api/v1/wifi-networks', ['ssid' => 'X'])->assertForbidden();
});

it('requires wifi abilities on the token', function (): void {
    ['tenant' => $tenant, 'token' => $token] = tokenWith(['wifi:read']);
    TenantContext::setId($tenant->getKey());
    $net = WifiNetwork::factory()->create();
    TenantContext::clear();
    $h = apiHeaders($token, $tenant->getKey());

    $this->withHeaders($h)->getJson('/api/v1/wifi-networks')->assertOk();
    $this->withHeaders($h)->getJson("/api/v1/wifi-networks/{$net->id}/clients")->assertOk();
    $this->withHeaders($h)->postJson('/api/v1/wifi-networks', ['ssid' => 'X'])
        ->assertForbidden()->assertJson(['error' => 'Token lacks ability: wifi:create']);
    // Attaching a client is an update of the network.
    $this->withHeaders($h)->postJson("/api/v1/wifi-networks/{$net->id}/clients", ['equipment_id' => 1])
        ->assertForbidden()->assertJson(['error' => 'Token lacks ability: wifi:update']);
});
