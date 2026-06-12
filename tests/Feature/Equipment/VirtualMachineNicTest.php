<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Enums\InterfaceType;
use App\Livewire\Equipment\Show as EquipmentShow;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;

afterEach(function (): void {
    TenantContext::clear();
});

function bootVnicScene(string $role = 'admin'): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->forceFill(['role' => $role])->save();
    $user->tenants()->attach($tenant);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
    actingAsInTenant($user, $tenant);

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'height_units' => 12]);

    $hv = Equipment::factory()->hypervisor()->create([
        'rack_id' => $rack->getKey(),
        'room_id' => $room->getKey(),
        'name' => 'HV-NIC',
    ]);

    $pnic = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $hv->getKey(),
        'name' => 'eno1',
    ]);

    $vm = Equipment::factory()->virtualMachine($hv)->create(['name' => 'VM-NIC']);

    return [$tenant, $user, $hv, $pnic, $vm];
}

it('lets multiple vNICs on a VM share the same backing pNIC', function (): void {
    [, , $hv, $pnic, $vm] = bootVnicScene();

    Livewire::test(EquipmentShow::class, ['equipment' => $vm])
        ->call('openIfCreate')
        ->set('ifName', 'net0')
        ->set('ifType', InterfaceType::Virtual->value)
        ->set('ifBackedById', $pnic->getKey())
        ->call('saveIf')
        ->assertHasNoErrors();

    Livewire::test(EquipmentShow::class, ['equipment' => $vm])
        ->call('openIfCreate')
        ->set('ifName', 'net1')
        ->set('ifType', InterfaceType::Virtual->value)
        ->set('ifBackedById', $pnic->getKey())
        ->call('saveIf')
        ->assertHasNoErrors();

    $vnics = NetworkInterface::query()
        ->where('equipment_id', $vm->getKey())
        ->get();

    expect($vnics)->toHaveCount(2)
        ->and($vnics->pluck('backed_by_interface_id')->all())
        ->each->toBe($pnic->getKey());
});

it('rejects a vNIC backing pointing at a NIC of a different equipment', function (): void {
    [, , $hv, , $vm] = bootVnicScene();

    $otherEq = Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'SW-Other']);
    $foreignNic = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $otherEq->getKey(),
        'name' => 'Gi0/1',
    ]);

    Livewire::test(EquipmentShow::class, ['equipment' => $vm])
        ->call('openIfCreate')
        ->set('ifName', 'net0')
        ->set('ifType', InterfaceType::Virtual->value)
        ->set('ifBackedById', $foreignNic->getKey())
        ->call('saveIf')
        ->assertHasErrors(['ifBackedById']);

    expect(NetworkInterface::query()->where('equipment_id', $vm->getKey())->count())->toBe(0);
});

it('rejects a vNIC backing pointing at a non-physical port type', function (): void {
    [, , $hv, , $vm] = bootVnicScene();

    $consolePort = NetworkInterface::factory()->create([
        'equipment_id' => $hv->getKey(),
        'name' => 'con0',
        'type' => InterfaceType::Console,
    ]);

    Livewire::test(EquipmentShow::class, ['equipment' => $vm])
        ->call('openIfCreate')
        ->set('ifName', 'net0')
        ->set('ifType', InterfaceType::Virtual->value)
        ->set('ifBackedById', $consolePort->getKey())
        ->call('saveIf')
        ->assertHasErrors(['ifBackedById']);
});

it('does not persist backing on a non-VM equipment even if posted', function (): void {
    [, , $hv, $pnic] = bootVnicScene();

    Livewire::test(EquipmentShow::class, ['equipment' => $hv])
        ->call('openIfCreate')
        ->set('ifName', 'eno2')
        ->set('ifType', InterfaceType::Ethernet->value)
        ->set('ifBackedById', $pnic->getKey())
        ->call('saveIf')
        ->assertHasNoErrors();

    $saved = NetworkInterface::query()->where('name', 'eno2')->firstOrFail();
    expect($saved->backed_by_interface_id)->toBeNull();
});
