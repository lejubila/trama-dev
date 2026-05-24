<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Livewire\Topology\Graph;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
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
