<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Livewire\Topology\Graph;
use App\Enums\InterfaceVlanMode;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\TopologySnapshot;
use App\Models\User;
use App\Services\TopologyService;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;

afterEach(function (): void {
    TenantContext::clear();
});

function bootTopologyScene(string $role): array
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
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);

    return [$tenant, $user, $site, $rack];
}

it('renders the topology page for an authenticated user', function (): void {
    [$tenant] = bootTopologyScene('admin');
    Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'TOPO-SW']);

    Livewire::test(Graph::class)
        ->assertOk()
        ->assertSee('Topologia');
});

it('toggles a type filter on and off', function (): void {
    [$tenant] = bootTopologyScene('admin');

    // Default state: filterTypes = [] means "all types selected" in the UI.
    // First toggle on "switch" deselects it from that all-selected set →
    // array now contains every type except switch. A second toggle adds
    // switch back, the set covers the full enum and is normalized to [].
    $component = Livewire::test(Graph::class)
        ->assertSet('filterTypes', [])
        ->call('toggleType', 'switch');

    $after = $component->get('filterTypes');
    expect($after)->not->toContain('switch');
    expect($after)->toContain('router');

    $component->call('toggleType', 'switch')
        ->assertSet('filterTypes', []);
});

it('clears all filters', function (): void {
    [$tenant, , $site] = bootTopologyScene('admin');

    Livewire::test(Graph::class)
        ->set('siteId', $site->getKey())
        ->set('statusFilter', 'active')
        ->set('vlanFilter', 10)
        ->call('toggleType', 'switch')
        ->call('clearFilters')
        ->assertSet('siteId', 0)
        ->assertSet('statusFilter', '')
        ->assertSet('vlanFilter', 0)
        ->assertSet('filterTypes', []);
});

it('exposes graphData scoped to the current tenant', function (): void {
    [$tenantA, , $siteA] = bootTopologyScene('admin');
    Equipment::factory()->ofType(EquipmentType::Router)->create(['name' => 'RTR-CURRENT']);

    // Other-tenant equipment must not appear
    $tenantB = Tenant::factory()->create();
    TenantContext::setId($tenantB->getKey());
    $siteB = Site::factory()->create();
    $roomB = Room::factory()->create(['site_id' => $siteB->getKey()]);
    $rackB = Rack::factory()->create(['room_id' => $roomB->getKey()]);
    Equipment::factory()->ofType(EquipmentType::Router)->create([
        'rack_id' => $rackB->getKey(),
        'name' => 'RTR-OTHER',
    ]);

    TenantContext::setId($tenantA->getKey());

    $instance = new Graph;
    $data = $instance->graphData(app(TopologyService::class));

    $labels = collect($data['nodes'])->pluck('data.label')->all();
    expect($labels)->toContain('RTR-CURRENT')
        ->and($labels)->not->toContain('RTR-OTHER');
});

it('fetchInterfaces returns the configured attributes for the active tenant', function (): void {
    [$tenant, , , $rack] = bootTopologyScene('admin');

    $eq = Equipment::factory()->ofType(EquipmentType::Switch)->create([
        'rack_id' => $rack->getKey(),
        'name' => 'SW-A',
    ]);
    NetworkInterface::factory()->create([
        'equipment_id' => $eq->getKey(),
        'name' => 'Gi0/1',
        'ip_address' => '10.0.0.1',
        'mac_address' => 'aa:bb:cc:dd:ee:ff',
        'vlan_mode' => InterfaceVlanMode::Trunk,
        'vlan_default' => 100,
        'vlans_allowed' => [100, 101, 102],
        'description' => 'uplink to core',
        'index' => 1,
    ]);

    // Call the public method directly so we get the typed array back; the
    // Livewire test wrapper exposes effects but not the raw return value.
    $list = (new Graph)->fetchInterfaces($eq->getKey());

    expect($list)->toBeArray()->and($list)->toHaveCount(1);
    $iface = $list[0];
    expect($iface['name'])->toBe('Gi0/1')
        ->and($iface['ip_address'])->toBe('10.0.0.1')
        ->and($iface['mac_address'])->toBe('aa:bb:cc:dd:ee:ff')
        ->and($iface['vlan_mode'])->toBe('trunk')
        ->and($iface['vlan_default'])->toBe(100)
        ->and($iface['vlans_allowed'])->toBe([100, 101, 102])
        ->and($iface['description'])->toBe('uplink to core');
});

it('fetchInterfaces refuses cross-tenant equipment', function (): void {
    [$tenantA] = bootTopologyScene('admin');
    $tenantB = Tenant::factory()->create();
    TenantContext::setId($tenantB->getKey());
    $eqB = Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'SW-B']);
    TenantContext::setId($tenantA->getKey());

    // BelongsToTenant scope already hides cross-tenant rows: findOrFail
    // throws ModelNotFoundException, which Laravel converts to a 404
    // outside of test context. Here we just assert the exception fires.
    expect(fn () => (new Graph)->fetchInterfaces($eqB->getKey()))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('hideAlways and showAlways flip the hidden_in_topology flag', function (): void {
    [$tenant, , , $rack] = bootTopologyScene('admin');

    $eq = Equipment::factory()->ofType(EquipmentType::Switch)->create([
        'rack_id' => $rack->getKey(),
        'name' => 'SW-HIDE',
        'hidden_in_topology' => false,
    ]);

    (new Graph)->hideAlways($eq->getKey());
    expect($eq->refresh()->hidden_in_topology)->toBeTrue();

    (new Graph)->showAlways($eq->getKey());
    expect($eq->refresh()->hidden_in_topology)->toBeFalse();
});

it('restores sessionHiddenIds from a snapshot preset', function (): void {
    [$tenant] = bootTopologyScene('admin');
    Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'SW-Z']);

    $snap = TopologySnapshot::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'view_state' => [
            'nodePositions' => ['eq-1' => [0, 0]],
            'sessionHiddenIds' => [7, 9],
        ],
    ]);

    Livewire::test(Graph::class, ['snapshotPreset' => $snap->getKey()])
        ->assertOk()
        ->assertViewHas('restore', function ($restore) {
            return is_array($restore)
                && ($restore['sessionHiddenIds'] ?? null) === [7, 9];
        });
});

it('restores portSettings from a snapshot preset into the view payload', function (): void {
    [$tenant] = bootTopologyScene('admin');
    Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'SW-Z']);

    $snap = TopologySnapshot::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'view_state' => [
            'nodePositions' => ['eq-1' => [0, 0]],
            'portSettings' => ['42' => ['ip' => true, 'vlan' => true]],
        ],
    ]);

    Livewire::test(Graph::class, ['snapshotPreset' => $snap->getKey()])
        ->assertOk()
        ->assertViewHas('restore', function ($restore) {
            return is_array($restore)
                && isset($restore['portSettings'])
                && (array) $restore['portSettings'] === ['42' => ['ip' => true, 'vlan' => true]];
        });
});
