<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Livewire\Equipment\Index as EquipmentIndex;
use App\Models\Equipment;
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

function bootVmScene(string $role = 'admin'): array
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

    $hypervisor = Equipment::factory()->hypervisor()->create([
        'rack_id' => $rack->getKey(),
        'room_id' => $room->getKey(),
        'name' => 'HV-Test',
    ]);

    return [$tenant, $user, $rack, $room, $hypervisor];
}

it('creates a virtual machine attached to a hypervisor host', function (): void {
    [$tenant, $user, $rack, $room, $hv] = bootVmScene();

    Livewire::test(EquipmentIndex::class)
        ->call('openCreate')
        ->set('name', 'VM-Web')
        ->set('type', EquipmentType::VirtualMachine->value)
        ->set('hostEquipmentId', $hv->getKey())
        ->set('vcpu', 4)
        ->set('ramMb', 4096)
        ->set('diskGb', 50)
        ->set('guestOs', 'Ubuntu 22.04')
        ->call('save')
        ->assertHasNoErrors();

    $vm = Equipment::query()->where('name', 'VM-Web')->firstOrFail();

    expect($vm->type)->toBe(EquipmentType::VirtualMachine)
        ->and($vm->host_equipment_id)->toBe($hv->getKey())
        ->and($vm->rack_id)->toBeNull()
        ->and((bool) $vm->mounted)->toBeFalse()
        ->and($vm->custom_fields['vcpu'])->toBe(4)
        ->and($vm->custom_fields['guest_os'])->toBe('Ubuntu 22.04');
});

it('rejects a virtual machine without a host hypervisor', function (): void {
    bootVmScene();

    Livewire::test(EquipmentIndex::class)
        ->call('openCreate')
        ->set('name', 'VM-Orphan')
        ->set('type', EquipmentType::VirtualMachine->value)
        ->set('hostEquipmentId', null)
        ->call('save')
        ->assertHasErrors(['hostEquipmentId']);

    expect(Equipment::query()->where('name', 'VM-Orphan')->count())->toBe(0);
});

it('rejects a VM whose declared host is not a hypervisor', function (): void {
    [, , $rack, , $hv] = bootVmScene();

    $switch = Equipment::factory()->ofType(EquipmentType::Switch)->create([
        'rack_id' => $rack->getKey(),
        'name' => 'SW-Wrong',
    ]);

    Livewire::test(EquipmentIndex::class)
        ->call('openCreate')
        ->set('name', 'VM-BadHost')
        ->set('type', EquipmentType::VirtualMachine->value)
        ->set('hostEquipmentId', $switch->getKey())
        ->call('save')
        ->assertHasErrors(['hostEquipmentId']);

    expect(Equipment::query()->where('name', 'VM-BadHost')->count())->toBe(0);
});

it('nulls VM host references when the hypervisor is deleted', function (): void {
    [, , , , $hv] = bootVmScene();

    $vm = Equipment::factory()->virtualMachine($hv)->create(['name' => 'VM-Keep']);

    $hv->forceDelete();

    $vm->refresh();
    expect($vm->host_equipment_id)->toBeNull()
        ->and(Equipment::query()->where('name', 'VM-Keep')->exists())->toBeTrue();
});

it('persists hypervisor vendor in custom_fields', function (): void {
    [, , $rack] = bootVmScene();

    Livewire::test(EquipmentIndex::class)
        ->call('openCreate')
        ->set('name', 'HV-New')
        ->set('type', EquipmentType::Hypervisor->value)
        ->set('hypervisorVendor', 'xcp_ng')
        ->set('mounted', true)
        ->set('rackId', $rack->getKey())
        ->set('positionUStart', 1)
        ->set('positionUHeight', 2)
        ->call('save')
        ->assertHasNoErrors();

    $hv = Equipment::query()->where('name', 'HV-New')->firstOrFail();
    expect($hv->type)->toBe(EquipmentType::Hypervisor)
        ->and($hv->custom_fields['hypervisor_vendor'])->toBe('xcp_ng');
});
