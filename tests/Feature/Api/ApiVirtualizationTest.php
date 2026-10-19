<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Enums\InterfaceType;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

/**
 * A hypervisor with one physical NIC and a VM on it with one vNIC.
 *
 * @return array{host: Equipment, pnic: NetworkInterface, vm: Equipment, vnic: NetworkInterface}
 */
function virtualLab(Tenant $tenant): array
{
    TenantContext::setId($tenant->getKey());
    $host = Equipment::factory()->hypervisor()->create(['name' => 'PVE-1']);
    $pnic = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $host->getKey(), 'name' => 'eno1']);
    $vm = Equipment::factory()->virtualMachine($host)->create(['name' => 'VM-1']);
    $vnic = NetworkInterface::factory()->create([
        'equipment_id' => $vm->getKey(), 'name' => 'net0', 'type' => InterfaceType::Virtual, 'media' => 'virtual',
    ]);
    TenantContext::clear();

    return ['host' => $host, 'pnic' => $pnic, 'vm' => $vm, 'vnic' => $vnic];
}

it('creates a VM on a hypervisor and lists the VMs of a host', function (): void {
    $u = apiUser('admin');
    ['host' => $host] = virtualLab($u['tenant']);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson('/api/v1/equipment', ['name' => 'VM-2', 'type' => 'virtual_machine', 'host_equipment_id' => $host->id])
        ->assertCreated()
        ->assertJsonPath('data.attributes.host_equipment_id', $host->id)
        ->assertJsonPath('data.attributes.mounted', false);

    $this->getJson("/api/v1/equipment?host_equipment_id={$host->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('requires a hypervisor host for a VM and refuses a host on other devices', function (): void {
    $u = apiUser('admin');
    ['vm' => $vm] = virtualLab($u['tenant']);
    $api = $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()));

    $api->postJson('/api/v1/equipment', ['name' => 'VM-X', 'type' => 'virtual_machine'])
        ->assertUnprocessable()->assertJsonValidationErrors('host_equipment_id');
    // A VM is not a hypervisor.
    $api->postJson('/api/v1/equipment', ['name' => 'VM-X', 'type' => 'virtual_machine', 'host_equipment_id' => $vm->id])
        ->assertUnprocessable()->assertJsonValidationErrors('host_equipment_id');
    $api->postJson('/api/v1/equipment', ['name' => 'SW-X', 'type' => 'switch', 'host_equipment_id' => $vm->id])
        ->assertUnprocessable()->assertJsonValidationErrors('host_equipment_id');
    $api->patchJson("/api/v1/equipment/{$vm->id}", ['mounted' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('mounted');
});

it('does not accept a hypervisor of another tenant as host', function (): void {
    $u = apiUser('admin');
    ['host' => $foreignHost] = virtualLab(Tenant::factory()->create());

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson('/api/v1/equipment', ['name' => 'VM-X', 'type' => 'virtual_machine', 'host_equipment_id' => $foreignHost->id])
        ->assertUnprocessable()->assertJsonValidationErrors('host_equipment_id');
});

it('sets and clears the backing pNIC of a vNIC', function (): void {
    $u = apiUser('admin');
    ['pnic' => $pnic, 'vm' => $vm, 'vnic' => $vnic] = virtualLab($u['tenant']);
    $api = $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()));

    $api->patchJson("/api/v1/interfaces/{$vnic->id}", ['backed_by_interface_id' => $pnic->id])
        ->assertOk()->assertJsonPath('data.attributes.backed_by_interface_id', $pnic->id);

    $api->postJson("/api/v1/equipment/{$vm->id}/interfaces", [
        'equipment_id' => $vm->id, 'name' => 'net1', 'type' => 'virtual', 'media' => 'virtual',
        'status' => 'up', 'poe' => 'none', 'backed_by_interface_id' => $pnic->id,
    ])->assertCreated()->assertJsonPath('data.attributes.backed_by_interface_id', $pnic->id);

    $api->patchJson("/api/v1/interfaces/{$vnic->id}", ['backed_by_interface_id' => null])
        ->assertOk()->assertJsonPath('data.attributes.backed_by_interface_id', null);
});

it('refuses a backing that is not a physical NIC of the host', function (): void {
    $u = apiUser('admin');
    ['host' => $host, 'vm' => $vm, 'vnic' => $vnic, 'pnic' => $pnic] = virtualLab($u['tenant']);
    TenantContext::setId($u['tenant']->getKey());
    $other = NetworkInterface::factory()->ethernet()->create(['equipment_id' => Equipment::factory()->create(['type' => EquipmentType::Switch])->getKey()]);
    $console = NetworkInterface::factory()->create(['equipment_id' => $host->getKey(), 'name' => 'con0', 'type' => InterfaceType::Console]);
    $ethOnVm = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $vm->getKey(), 'name' => 'eth9']);
    TenantContext::clear();
    $api = $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()));

    foreach ([$other->id, $console->id, $vnic->id] as $bad) {
        $api->patchJson("/api/v1/interfaces/{$vnic->id}", ['backed_by_interface_id' => $bad])
            ->assertUnprocessable()->assertJsonValidationErrors('backed_by_interface_id');
    }
    // Only virtual interfaces have a backing.
    $api->patchJson("/api/v1/interfaces/{$ethOnVm->id}", ['backed_by_interface_id' => $pnic->id])
        ->assertUnprocessable()->assertJsonValidationErrors('backed_by_interface_id');
});

it('backs a VLAN sub-interface with a port of the same device', function (): void {
    $u = apiUser('admin');
    TenantContext::setId($u['tenant']->getKey());
    $fw = Equipment::factory()->create(['type' => EquipmentType::Firewall]);
    $port = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $fw->getKey(), 'name' => 'port1']);
    TenantContext::clear();

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson("/api/v1/equipment/{$fw->id}/interfaces", [
            'equipment_id' => $fw->id, 'name' => 'port1.20', 'type' => 'virtual', 'media' => 'virtual',
            'status' => 'up', 'poe' => 'none', 'vlan_mode' => 'access', 'vlan_default' => 20,
            'backed_by_interface_id' => $port->id,
        ])->assertCreated()->assertJsonPath('data.attributes.backed_by_interface_id', $port->id);
});

it('drops stale backings when the VM moves host or the vNIC stops being virtual', function (): void {
    $u = apiUser('admin');
    ['pnic' => $pnic, 'vm' => $vm, 'vnic' => $vnic] = virtualLab($u['tenant']);
    TenantContext::setId($u['tenant']->getKey());
    $vnic->update(['backed_by_interface_id' => $pnic->id]);
    $other = Equipment::factory()->hypervisor()->create();
    $vnic2 = NetworkInterface::factory()->create([
        'equipment_id' => $vm->getKey(), 'name' => 'net1', 'type' => InterfaceType::Virtual, 'media' => 'virtual', 'backed_by_interface_id' => $pnic->id,
    ]);
    TenantContext::clear();
    $api = $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()));

    $api->patchJson("/api/v1/interfaces/{$vnic2->id}", ['type' => 'ethernet', 'media' => 'copper'])
        ->assertOk()->assertJsonPath('data.attributes.backed_by_interface_id', null);

    $api->patchJson("/api/v1/equipment/{$vm->id}", ['host_equipment_id' => $other->id])->assertOk();
    expect(NetworkInterface::withoutGlobalScopes()->find($vnic->id)?->backed_by_interface_id)->toBeNull();
});
