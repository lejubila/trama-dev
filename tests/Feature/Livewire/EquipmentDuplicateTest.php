<?php

declare(strict_types=1);

use App\Actions\Interfaces\CreateKeystonePair;
use App\Enums\EquipmentType;
use App\Enums\InterfaceType;
use App\Livewire\Equipment\Index as EquipmentIndex;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

afterEach(function (): void {
    TenantContext::clear();
});

function bootDuplicateScene(string $role): array
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

    return [$tenant, $user, $rack];
}

it('pre-fills the create form from the source device', function (): void {
    [, , $rack] = bootDuplicateScene('admin');
    $eq = Equipment::factory()->mountedAt(3, 1)->create([
        'rack_id' => $rack->getKey(),
        'name' => 'SW-Core',
        'vendor' => 'Cisco',
        'serial' => 'ABC123',
        'asset_tag' => 'AT-1',
        'management_ip' => '10.0.0.1',
    ]);

    Livewire::test(EquipmentIndex::class)
        ->call('openDuplicate', $eq->getKey())
        ->assertSet('showForm', true)
        ->assertSet('editingId', null)
        ->assertSet('duplicatingFromId', $eq->getKey())
        ->assertSet('name', 'SW-Core 2')
        ->assertSet('vendor', 'Cisco')
        ->assertSet('serial', '')
        ->assertSet('assetTag', '')
        ->assertSet('managementIp', '')
        ->assertSet('rackId', $rack->getKey())
        ->assertSet('positionUStart', null)
        ->assertSee('Duplica dispositivo');
});

it('copies interfaces but not connections on save', function (): void {
    [$tenant] = bootDuplicateScene('admin');
    $eq = Equipment::factory()->create(['name' => 'SW-A', 'mounted' => false]);
    $other = Equipment::factory()->create(['name' => 'SW-B', 'mounted' => false]);

    $gi1 = NetworkInterface::factory()->create([
        'equipment_id' => $eq->getKey(),
        'name' => 'Gi0/1',
        'vlan_default' => 20,
        'mac_address' => 'aa:bb:cc:dd:ee:01',
        'ip_address' => '10.0.0.5/24',
    ]);
    NetworkInterface::factory()->create(['equipment_id' => $eq->getKey(), 'name' => 'Gi0/2']);
    $remote = NetworkInterface::factory()->create(['equipment_id' => $other->getKey(), 'name' => 'Gi0/1']);

    Connection::create([
        'tenant_id' => $tenant->getKey(),
        'from_interface_id' => $gi1->getKey(),
        'to_interface_id' => $remote->getKey(),
        'cable_type' => 'utp_cat6',
    ]);

    Livewire::test(EquipmentIndex::class)
        ->call('openDuplicate', $eq->getKey())
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertSet('duplicatingFromId', null);

    $copy = Equipment::query()->where('name', 'SW-A 2')->firstOrFail();
    $copies = $copy->interfaces()->orderBy('name')->get();

    expect($copies->pluck('name')->all())->toBe(['Gi0/1', 'Gi0/2']);
    expect($copies[0]->vlan_default)->toBe(20)
        ->and($copies[0]->mac_address)->toBeNull()
        ->and($copies[0]->ip_address)->toBeNull();

    $copyIds = $copies->pluck('id')->all();
    expect(Connection::query()->whereIn('from_interface_id', $copyIds)->orWhereIn('to_interface_id', $copyIds)->count())->toBe(0);
    expect(Connection::query()->count())->toBe(1);
    expect($eq->interfaces()->count())->toBe(2);
});

it('remaps keystone pairs onto the copies', function (): void {
    bootDuplicateScene('admin');
    $pp = Equipment::factory()->create(['name' => 'PP-1', 'type' => EquipmentType::PatchPanel, 'mounted' => false]);
    app(CreateKeystonePair::class)->execute($pp, ['name' => '1', 'index' => 1]);

    Livewire::test(EquipmentIndex::class)
        ->call('openDuplicate', $pp->getKey())
        ->call('save')
        ->assertHasNoErrors();

    $copy = Equipment::query()->where('name', 'PP-1 2')->firstOrFail();
    [$front, $rear] = $copy->interfaces()->orderBy('side')->get()->all();

    expect($front->paired_interface_id)->toBe($rear->getKey())
        ->and($rear->paired_interface_id)->toBe($front->getKey());
});

it('remaps internal backing and keeps external backing', function (): void {
    bootDuplicateScene('admin');
    $fw = Equipment::factory()->create(['name' => 'FW', 'type' => EquipmentType::Firewall, 'mounted' => false]);
    $hv = Equipment::factory()->create(['name' => 'HV', 'type' => EquipmentType::Hypervisor, 'mounted' => false]);
    $port = NetworkInterface::factory()->create(['equipment_id' => $fw->getKey(), 'name' => 'port1']);
    NetworkInterface::factory()->create([
        'equipment_id' => $fw->getKey(),
        'name' => 'port1.10',
        'type' => InterfaceType::Virtual,
        'backed_by_interface_id' => $port->getKey(),
    ]);
    $pnic = NetworkInterface::factory()->create(['equipment_id' => $hv->getKey(), 'name' => 'eno1']);
    NetworkInterface::factory()->create([
        'equipment_id' => $fw->getKey(),
        'name' => 'vnic0',
        'type' => InterfaceType::Virtual,
        'backed_by_interface_id' => $pnic->getKey(),
    ]);

    Livewire::test(EquipmentIndex::class)
        ->call('openDuplicate', $fw->getKey())
        ->call('save')
        ->assertHasNoErrors();

    $copy = Equipment::query()->where('name', 'FW 2')->firstOrFail();
    $byName = $copy->interfaces()->get()->keyBy('name');

    expect($byName['port1.10']->backed_by_interface_id)->toBe($byName['port1']->getKey())
        ->and($byName['vnic0']->backed_by_interface_id)->toBe($pnic->getKey());
});

it('picks the next free progressive number', function (): void {
    bootDuplicateScene('admin');
    $sw = Equipment::factory()->create(['name' => 'SW', 'mounted' => false]);
    $sw2 = Equipment::factory()->create(['name' => 'SW 2', 'mounted' => false]);
    Equipment::factory()->create(['name' => 'SWITCH 9', 'mounted' => false]);

    Livewire::test(EquipmentIndex::class)
        ->call('openDuplicate', $sw->getKey())
        ->assertSet('name', 'SW 3')
        ->call('openDuplicate', $sw2->getKey())
        ->assertSet('name', 'SW 3');
});

it('forbids cliente from duplicating', function (): void {
    bootDuplicateScene('cliente');
    $eq = Equipment::factory()->create(['mounted' => false]);

    Livewire::test(EquipmentIndex::class)->call('openDuplicate', $eq->getKey())->assertForbidden();
});

it('cannot duplicate equipment of another tenant', function (): void {
    bootDuplicateScene('admin');
    $foreign = Equipment::factory()->create(['name' => 'Eq-A', 'mounted' => false]);

    TenantContext::clear();
    $tenantB = Tenant::factory()->create();
    /** @var User $userB */
    $userB = User::factory()->create();
    $userB->forceFill(['role' => 'tecnico'])->save();
    actingAsInTenant($userB, $tenantB);

    expect(fn () => Livewire::test(EquipmentIndex::class)->call('openDuplicate', $foreign->getKey()))
        ->toThrow(ModelNotFoundException::class);
});
