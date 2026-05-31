<?php

declare(strict_types=1);

use App\Actions\Vpn\AttachRemoteClient;
use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Enums\VpnProtocol;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Tenant;
use App\Models\VpnRemoteAccess;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

it('auto-creates a vpn0 virtual interface on the client when none exists', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    // The VPN row needs a firewall interface; create a minimal firewall + iface.
    $fw = Equipment::factory()->ofType(EquipmentType::Firewall)->create();
    $fwIface = NetworkInterface::factory()->create(['equipment_id' => $fw->getKey(), 'name' => 'wan0']);

    $vpn = VpnRemoteAccess::create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Office RA',
        'protocol' => VpnProtocol::WireGuard->value,
        'firewall_interface_id' => $fwIface->id,
    ]);
    $client = Equipment::factory()->ofType(EquipmentType::Notebook)->create();

    expect($client->interfaces()->count())->toBe(0);
    $assoc = app(AttachRemoteClient::class)->execute($vpn, $client);

    $iface = NetworkInterface::query()->find($assoc->client_interface_id);
    expect($iface->name)->toBe('vpn0')
        ->and($iface->media)->toBe(InterfaceMedia::Virtual)
        ->and($iface->equipment_id)->toBe($client->id);
});

it('reuses the provided existing virtual interface', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $fw = Equipment::factory()->ofType(EquipmentType::Firewall)->create();
    $fwIface = NetworkInterface::factory()->create(['equipment_id' => $fw->getKey(), 'name' => 'wan0']);

    $vpn = VpnRemoteAccess::create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Office RA',
        'protocol' => VpnProtocol::WireGuard->value,
        'firewall_interface_id' => $fwIface->id,
    ]);
    $client = Equipment::factory()->ofType(EquipmentType::Notebook)->create();
    $iface = NetworkInterface::factory()->create([
        'equipment_id' => $client->getKey(),
        'name' => 'vpn0',
        'media' => InterfaceMedia::Virtual,
    ]);

    $assoc = app(AttachRemoteClient::class)->execute($vpn, $client, $iface);
    expect($assoc->client_interface_id)->toBe($iface->id)
        ->and(NetworkInterface::query()->where('equipment_id', $client->getKey())->count())->toBe(1);
});
