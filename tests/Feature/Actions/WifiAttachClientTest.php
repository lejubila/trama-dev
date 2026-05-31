<?php

declare(strict_types=1);

use App\Actions\WifiNetworks\AttachClient;
use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Tenant;
use App\Models\WifiNetwork;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

it('auto-creates a wlan0 interface on the client when none exists', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());
    $net = WifiNetwork::create(['tenant_id' => $tenant->getKey(), 'ssid' => 'Office']);
    $client = Equipment::factory()->ofType(EquipmentType::Notebook)->create(['name' => 'NB']);

    expect($client->interfaces()->count())->toBe(0);
    $assoc = app(AttachClient::class)->execute($net, $client);

    $iface = NetworkInterface::query()->find($assoc->client_interface_id);
    expect($iface->name)->toBe('wlan0')
        ->and($iface->media)->toBe(InterfaceMedia::Wireless)
        ->and($iface->equipment_id)->toBe($client->id);
});

it('picks the next available wlanN when wlan0 is taken', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());
    $net = WifiNetwork::create(['tenant_id' => $tenant->getKey(), 'ssid' => 'Office']);
    $client = Equipment::factory()->ofType(EquipmentType::Notebook)->create();
    NetworkInterface::factory()->create([
        'equipment_id' => $client->getKey(),
        'name' => 'wlan0',
        'media' => InterfaceMedia::Wireless,
    ]);

    $assoc = app(AttachClient::class)->execute($net, $client);
    $iface = NetworkInterface::query()->find($assoc->client_interface_id);
    expect($iface->name)->toBe('wlan1');
});

it('reuses the provided existing interface', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());
    $net = WifiNetwork::create(['tenant_id' => $tenant->getKey(), 'ssid' => 'Office']);
    $client = Equipment::factory()->ofType(EquipmentType::Notebook)->create();
    $iface = NetworkInterface::factory()->create([
        'equipment_id' => $client->getKey(),
        'name' => 'wlan0',
        'media' => InterfaceMedia::Wireless,
    ]);

    $assoc = app(AttachClient::class)->execute($net, $client, $iface);
    expect($assoc->client_interface_id)->toBe($iface->id)
        ->and(NetworkInterface::query()->where('equipment_id', $client->getKey())->count())->toBe(1);
});
