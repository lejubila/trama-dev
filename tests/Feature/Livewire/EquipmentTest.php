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
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;

afterEach(function (): void {
    TenantContext::clear();
});

function bootEquipmentScene(string $role): array
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

it('renders equipment index', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    Equipment::factory()->create(['name' => 'SW-Test']);

    Livewire::test(EquipmentIndex::class)->assertOk()->assertSee('SW-Test');
});

it('persists and reloads the hidden_in_topology flag via the edit form', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create(['name' => 'PDU-Hidden']);

    Livewire::test(EquipmentIndex::class)
        ->call('openEdit', $eq->getKey())
        ->assertSet('hiddenInTopology', false)
        ->set('hiddenInTopology', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($eq->fresh()->hidden_in_topology)->toBeTrue();

    Livewire::test(EquipmentIndex::class)
        ->call('openEdit', $eq->getKey())
        ->assertSet('hiddenInTopology', true);
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

it('allows creating a mounted equipment overlapping another (multi-device per U)', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    Equipment::factory()->mountedAt(3, 2)->create(['rack_id' => $rack->getKey()]);

    Livewire::test(EquipmentIndex::class)
        ->call('openCreate')
        ->set('name', 'Stacked')
        ->set('type', 'switch')
        ->set('mounted', true)
        ->set('rackId', $rack->getKey())
        ->set('positionUStart', 4)
        ->set('positionUHeight', 1)
        ->call('save')
        ->assertHasNoErrors();

    expect(Equipment::query()->where('name', 'Stacked')->exists())->toBeTrue();
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
    $userB->forceFill(['role' => 'admin'])->save();
    $userB->tenants()->attach($tenantB);
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
    // Force a non-keystone equipment type so bulk doesn't create
    // front/rear pairs (which would double the row count).
    $eq = Equipment::factory()->ofType(EquipmentType::Switch)->create();

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
    $eq = Equipment::factory()->ofType(EquipmentType::Switch)->create();

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
    $eq = Equipment::factory()->ofType(EquipmentType::Switch)->create();
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

it('auto-syncs room_id from rack when rack is selected via the form', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');

    Livewire::test(EquipmentIndex::class)
        ->call('openCreate')
        ->set('name', 'SW-Sync')
        ->set('type', 'switch')
        ->set('mounted', true)
        ->set('rackId', $rack->getKey())
        ->set('positionUStart', 1)
        ->set('positionUHeight', 1)
        ->call('save')
        ->assertHasNoErrors();

    $eq = Equipment::query()->where('name', 'SW-Sync')->first();
    expect($eq->room_id)->toBe($rack->room_id);
});

it('stores room_id directly for unracked equipment', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');

    $otherSite = Site::factory()->create();
    $otherRoom = Room::factory()->create(['site_id' => $otherSite->getKey()]);

    Livewire::test(EquipmentIndex::class)
        ->call('openCreate')
        ->set('name', 'AP-Ceiling')
        ->set('type', 'access_point')
        ->set('mounted', false)
        ->set('roomId', $otherRoom->getKey())
        ->call('save')
        ->assertHasNoErrors();

    $eq = Equipment::query()->where('name', 'AP-Ceiling')->first();
    expect($eq->room_id)->toBe($otherRoom->getKey())
        ->and($eq->rack_id)->toBeNull();
});

it('syncs tags on equipment via the edit form', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create(['name' => 'SW-Tagged']);
    $tag = Tag::create(['name' => 'critico', 'color' => '#ff0000']);

    Livewire::test(EquipmentIndex::class)
        ->call('openEdit', $eq->getKey())
        ->assertSet('selectedTagIds', [])
        ->set('selectedTagIds', [$tag->getKey()])
        ->call('save')
        ->assertHasNoErrors();

    expect($eq->fresh()->tags->pluck('id')->all())->toBe([$tag->getKey()]);

    // Reopening reflects the persisted association.
    Livewire::test(EquipmentIndex::class)
        ->call('openEdit', $eq->getKey())
        ->assertSet('selectedTagIds', [$tag->getKey()]);
});

it('filters equipment by tag', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $tag = Tag::create(['name' => 'core', 'color' => '#00ff00']);
    $tagged = Equipment::factory()->create(['name' => 'SW-Core']);
    $other = Equipment::factory()->create(['name' => 'SW-Edge']);
    $tagged->tags()->sync([$tag->getKey()]);

    Livewire::test(EquipmentIndex::class)
        ->set('tagFilter', $tag->getKey())
        ->assertSee('SW-Core')
        ->assertDontSee('SW-Edge');
});

it('remembers the last filter in session across visits', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');

    Livewire::test(EquipmentIndex::class)
        ->set('typeFilter', 'router')
        ->set('search', 'border');

    // A fresh component instance restores the filters from the session.
    Livewire::test(EquipmentIndex::class)
        ->assertSet('typeFilter', 'router')
        ->assertSet('search', 'border');
});

it('bulk-creates interfaces with the % placeholder mid-name', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->ofType(EquipmentType::Switch)->create();

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifBulk', true)
        ->set('ifBulkQuantity', 3)
        ->set('ifBulkStartFrom', 1)
        ->set('ifName', 'Port-%-LAN')
        ->call('saveIf')
        ->assertHasNoErrors();

    $names = $eq->interfaces()->pluck('name')->sort()->values()->all();
    expect($names)->toBe(['Port-1-LAN', 'Port-2-LAN', 'Port-3-LAN']);
});

it('bulk-creates interfaces with the % placeholder at the end (equivalent to legacy suffix)', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->ofType(EquipmentType::Switch)->create();

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifBulk', true)
        ->set('ifBulkQuantity', 12)
        ->set('ifBulkStartFrom', 1)
        ->set('ifName', 'Gi0/%')
        ->call('saveIf')
        ->assertHasNoErrors();

    $names = $eq->interfaces()->pluck('name')->sort()->values()->all();
    expect($names)->toBe([
        'Gi0/01', 'Gi0/02', 'Gi0/03', 'Gi0/04', 'Gi0/05', 'Gi0/06',
        'Gi0/07', 'Gi0/08', 'Gi0/09', 'Gi0/10', 'Gi0/11', 'Gi0/12',
    ]);
});

it('escapes the %% sequence to a literal percent sign in bulk names', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->ofType(EquipmentType::Switch)->create();

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifBulk', true)
        ->set('ifBulkQuantity', 2)
        ->set('ifBulkStartFrom', 1)
        ->set('ifName', '100%% load %')
        ->call('saveIf')
        ->assertHasNoErrors();

    $names = $eq->interfaces()->pluck('name')->sort()->values()->all();
    expect($names)->toBe(['100% load 1', '100% load 2']);
});

it('parses vlans_allowed from a comma-and-range list when creating an interface', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create();

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifName', 'Gi0/24')
        ->set('ifVlanMode', 'trunk')
        ->set('ifVlanDefault', 1)
        ->set('ifVlansAllowedText', '1, 60, 100-105')
        ->call('saveIf')
        ->assertHasNoErrors();

    $if = $eq->interfaces()->where('name', 'Gi0/24')->firstOrFail();
    expect($if->vlans_allowed)->toEqual([1, 60, 100, 101, 102, 103, 104, 105]);
});

it('reloads vlans_allowed into a compact range string when editing', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create();
    $if = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $eq->getKey(),
        'name' => 'Gi0/1',
        'vlans_allowed' => [1, 2, 3, 5, 6, 60],
    ]);

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfEdit', $if->getKey())
        ->assertSet('ifVlansAllowedText', '1-3, 5-6, 60');
});

it('rejects an invalid vlans_allowed string', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create();

    // Need trunk mode for the parser to actually run — access/none/transparent
    // skip parsing and silently null the field.
    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifName', 'Gi0/24')
        ->set('ifVlanMode', 'trunk')
        ->set('ifVlansAllowedText', '5000')
        ->call('saveIf')
        ->assertHasErrors(['ifVlansAllowedText']);

    expect($eq->interfaces()->where('name', 'Gi0/24')->exists())->toBeFalse();
});

it('rejects a descending range in vlans_allowed', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create();

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifName', 'Gi0/24')
        ->set('ifVlanMode', 'trunk')
        ->set('ifVlansAllowedText', '10-5')
        ->call('saveIf')
        ->assertHasErrors(['ifVlansAllowedText']);
});

it('stores null vlans_allowed when the field is blank', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create();

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifName', 'Gi0/24')
        ->set('ifVlansAllowedText', '')
        ->call('saveIf')
        ->assertHasNoErrors();

    expect($eq->interfaces()->where('name', 'Gi0/24')->firstOrFail()->vlans_allowed)->toBeNull();
});

it('blanks vlan_default and vlans_allowed when vlan_mode is transparent', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create();

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifName', 'uplink')
        ->set('ifVlanMode', 'transparent')
        ->set('ifVlanDefault', 999)
        ->set('ifVlansAllowedText', '1, 60, 100-105')
        ->call('saveIf')
        ->assertHasNoErrors();

    $if = $eq->interfaces()->where('name', 'uplink')->firstOrFail();
    expect($if->vlan_default)->toBeNull();
    expect($if->vlans_allowed)->toBeNull();
});

it('blanks vlan_default and vlans_allowed when vlan_mode is none', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create();

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifName', 'console')
        ->set('ifVlanMode', 'none')
        ->set('ifVlanDefault', 999)
        ->set('ifVlansAllowedText', '1, 60')
        ->call('saveIf')
        ->assertHasNoErrors();

    $if = $eq->interfaces()->where('name', 'console')->firstOrFail();
    expect($if->vlan_default)->toBeNull();
    expect($if->vlans_allowed)->toBeNull();
});

it('skips vlans_allowed parsing error when vlan_mode is transparent', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->create();

    // Garbage in the text field, but transparent mode means we never parse it.
    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifName', 'uplink2')
        ->set('ifVlanMode', 'transparent')
        ->set('ifVlansAllowedText', 'invalid garbage')
        ->call('saveIf')
        ->assertHasNoErrors();
});

it('blanks vlans_allowed when vlan_mode is access (single-VLAN port)', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->ofType(EquipmentType::Switch)->create();

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifName', 'access-port')
        ->set('ifVlanMode', 'access')
        ->set('ifVlanDefault', 10)
        ->set('ifVlansAllowedText', '1, 60')
        ->call('saveIf')
        ->assertHasNoErrors();

    $if = $eq->interfaces()->where('name', 'access-port')->firstOrFail();
    expect($if->vlan_default)->toBe(10);  // vlan_default stays — it's the access VLAN
    expect($if->vlans_allowed)->toBeNull(); // vlans_allowed is wiped on access
});

it('keeps vlans_allowed for trunk mode', function (): void {
    [$tenant, $user, $rack] = bootEquipmentScene('admin');
    $eq = Equipment::factory()->ofType(EquipmentType::Switch)->create();

    Livewire::test(EquipmentShow::class, ['equipment' => $eq])
        ->call('openIfCreate')
        ->set('ifName', 'trunk-port')
        ->set('ifVlanMode', 'trunk')
        ->set('ifVlanDefault', 1)
        ->set('ifVlansAllowedText', '1, 60, 100-102')
        ->call('saveIf')
        ->assertHasNoErrors();

    $if = $eq->interfaces()->where('name', 'trunk-port')->firstOrFail();
    expect($if->vlans_allowed)->toEqual([1, 60, 100, 101, 102]);
});
