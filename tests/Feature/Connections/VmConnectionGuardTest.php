<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Enums\InterfaceType;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Services\ConnectionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

afterEach(function (): void {
    TenantContext::clear();
});

function bootVmConnectionScene(): array
{
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'height_units' => 12]);

    $hv = Equipment::factory()->hypervisor()->create([
        'rack_id' => $rack->getKey(),
        'room_id' => $room->getKey(),
        'name' => 'HV-Guard',
    ]);
    $pnic = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $hv->getKey(),
        'name' => 'eno1',
    ]);

    $vm = Equipment::factory()->virtualMachine($hv)->create(['name' => 'VM-Guard']);
    $vnic = NetworkInterface::factory()->create([
        'equipment_id' => $vm->getKey(),
        'name' => 'net0',
        'type' => InterfaceType::Virtual,
    ]);

    $sw = Equipment::factory()->ofType(EquipmentType::Switch)->create([
        'rack_id' => $rack->getKey(),
        'name' => 'SW-Guard',
    ]);
    $swPort = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $sw->getKey(),
        'name' => 'Gi0/1',
    ]);

    return compact('tenant', 'hv', 'pnic', 'vm', 'vnic', 'sw', 'swPort');
}

it('rejects ConnectionService::connect when an endpoint is a VM vNIC', function (): void {
    $s = bootVmConnectionScene();

    expect(fn () => app(ConnectionService::class)->connect($s['vnic'], $s['swPort'], [
        'cable_type' => 'utp_cat6',
    ]))->toThrow(ValidationException::class);

    expect(Connection::query()->count())->toBe(0);
});

it('rejects Connection::create with a VM interface as endpoint', function (): void {
    $s = bootVmConnectionScene();

    expect(fn () => Connection::create([
        'tenant_id' => $s['tenant']->getKey(),
        'from_interface_id' => $s['vnic']->getKey(),
        'to_interface_id' => $s['swPort']->getKey(),
        'cable_type' => 'utp_cat6',
        'status' => 'active',
    ]))->toThrow(ValidationException::class);

    expect(Connection::query()->count())->toBe(0);
});

it('rejects updating an existing Connection to point to a VM interface', function (): void {
    $s = bootVmConnectionScene();

    $other = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $s['sw']->getKey(),
        'name' => 'Gi0/2',
    ]);

    $conn = Connection::create([
        'tenant_id' => $s['tenant']->getKey(),
        'from_interface_id' => $s['swPort']->getKey(),
        'to_interface_id' => $other->getKey(),
        'cable_type' => 'utp_cat6',
        'status' => 'active',
    ]);

    expect(fn () => $conn->update(['to_interface_id' => $s['vnic']->getKey()]))
        ->toThrow(ValidationException::class);
});

it('allows a connection between two non-VM endpoints', function (): void {
    $s = bootVmConnectionScene();

    $other = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $s['hv']->getKey(),
        'name' => 'eno2',
    ]);

    $conn = app(ConnectionService::class)->connect($s['swPort'], $other, [
        'cable_type' => 'utp_cat6',
    ]);

    expect($conn->exists)->toBeTrue();
});
