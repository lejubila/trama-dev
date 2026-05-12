<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Livewire\Equipment\Index as EquipmentIndex;
use App\Livewire\Equipment\Show as EquipmentShow;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

afterEach(function (): void {
    TenantContext::clear();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function bootEquipmentScene(string $role): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->tenants()->attach($tenant, ['role' => $role]);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
    test()->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    $user->syncRoles([$role]);
    actingAsInTenant($user, $tenant);

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'height_units' => 12]);

    return [$tenant, $user, $rack];
}

it('renders equipment index', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    Equipment::factory()->create(['name' => 'SW-Test']);

    Livewire::test(EquipmentIndex::class)->assertOk()->assertSee('SW-Test');
});

it('creates a non-mounted equipment', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');

    Livewire::test(EquipmentIndex::class)
        ->call('openCreate')
        ->set('name', 'AP-Test')
        ->set('type', EquipmentType::AccessPoint->value)
        ->set('mounted', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(Equipment::query()->where('name', 'AP-Test')->exists())->toBeTrue();
});

it('refuses to create a mounted equipment that overlaps another', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    Equipment::factory()->mountedAt(3, 2)->create(['rack_id' => $rack->getKey()]);

    Livewire::test(EquipmentIndex::class)
        ->call('openCreate')
        ->set('name', 'Conflict')
        ->set('type', 'switch')
        ->set('mounted', true)
        ->set('rackId', $rack->getKey())
        ->set('positionUStart', 4)
        ->set('positionUHeight', 1)
        ->call('save')
        ->assertHasErrors(['positionUStart']);

    expect(Equipment::query()->where('name', 'Conflict')->exists())->toBeFalse();
});

it('updates equipment', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create(['name' => 'Old']);

    Livewire::test(EquipmentIndex::class)
        ->call('openEdit', $eq->getKey())
        ->set('name', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($eq->fresh()->name)->toBe('New');
});

it('forbids cliente from creating equipment', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('cliente');

    Livewire::test(EquipmentIndex::class)->call('openCreate')->assertForbidden();
});

it('isolates equipment between tenants', function (): void {
    [$tenantA, $userA, $rackA] = bootEquipmentScene('admin');
    Equipment::factory()->create(['name' => 'Eq-A']);

    TenantContext::clear();
    $tenantB = Tenant::factory()->create();
    TenantContext::setId($tenantB->getKey());
    Equipment::factory()->create(['name' => 'Eq-B']);

    /** @var User $userB */
    $userB = User::factory()->create();
    $userB->tenants()->attach($tenantB, ['role' => 'admin']);
    test()->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->getKey());
    $userB->syncRoles(['admin']);
    actingAsInTenant($userB, $tenantB);

    Livewire::test(EquipmentIndex::class)->assertSee('Eq-B')->assertDontSee('Eq-A');
});

it('shows equipment detail with interfaces tab', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create(['name' => 'SW-Show']);

    $component = Livewire::test(EquipmentShow::class, ['equipment' => $eq]);
    $component->assertOk();
    $component->assertSee('SW-Show');
    $component->call('setTab', 'interfaces');
    $component->assertSet('activeTab', 'interfaces');
});

it('creates a new interface from the equipment Show', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create();

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifName', 'Gi0/99')
        ->call('saveIf')
        ->assertHasNoErrors();

    expect($eq->interfaces()->where('name', 'Gi0/99')->exists())->toBeTrue();
});

it('refuses to create an interface with duplicate name on same equipment', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create();
    NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $eq->getKey(),
        'name' => 'Gi0/1',
    ]);

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifName', 'Gi0/1')
        ->call('saveIf')
        ->assertHasErrors(['ifName']);

    expect($eq->interfaces()->where('name', 'Gi0/1')->count())->toBe(1);
});

it('allows the same interface name on different equipment', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eqA = Equipment::factory()->create();
    $eqB = Equipment::factory()->create();
    NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $eqA->getKey(),
        'name' => 'Gi0/1',
    ]);

    Livewire::test(EquipmentShow::class, ['equipment' => $eqB])
        ->call('openIfCreate')
        ->set('ifName', 'Gi0/1')
        ->call('saveIf')
        ->assertHasNoErrors();

    expect($eqB->interfaces()->where('name', 'Gi0/1')->exists())->toBeTrue();
});

it('refuses to rename an interface to a name already used on the same equipment', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create();
    NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $eq->getKey(),
        'name' => 'Gi0/1',
    ]);
    $second = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $eq->getKey(),
        'name' => 'Gi0/2',
    ]);

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfEdit', $second->getKey())
        ->set('ifName', 'Gi0/1')
        ->call('saveIf')
        ->assertHasErrors(['ifName']);

    expect($second->fresh()->name)->toBe('Gi0/2');
});

it('bulk-creates interfaces with zero-padded suffix based on max-digit count', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create();

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifBulk', true)
        ->set('ifBulkQuantity', 12)
        ->set('ifBulkStartFrom', 1)
        ->set('ifName', 'Port')
        ->call('saveIf')
        ->assertHasNoErrors();

    $names = $eq->interfaces()->pluck('name')->sort()->values()->all();
    expect($names)->toBe([
        'Port01', 'Port02', 'Port03', 'Port04', 'Port05', 'Port06',
        'Port07', 'Port08', 'Port09', 'Port10', 'Port11', 'Port12',
    ]);
});

it('pads bulk suffix only when the max number has more than one digit', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create();

    // qty=8 → max=8, single digit → no padding
    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifBulk', true)
        ->set('ifBulkQuantity', 8)
        ->set('ifName', 'P')
        ->call('saveIf')
        ->assertHasNoErrors();

    $names = $eq->interfaces()->pluck('name')->sort()->values()->all();
    expect($names)->toBe(['P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8']);
});

it('rolls back bulk creation when any generated name conflicts', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create();
    NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $eq->getKey(),
        'name' => 'Port05',
    ]);

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifBulk', true)
        ->set('ifBulkQuantity', 10)
        ->set('ifName', 'Port')
        ->call('saveIf')
        ->assertHasErrors(['ifName']);

    expect($eq->interfaces()->count())->toBe(1)
        ->and($eq->interfaces()->pluck('name')->all())->toBe(['Port05']);
});
