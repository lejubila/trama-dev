<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Support\Tenancy\TenantContext;
use Spatie\Permission\PermissionRegistrar;

afterEach(function (): void {
    TenantContext::clear();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('returns nodes and edges scoped to current tenant', function (): void {
    $u = apiUser('admin');
    TenantContext::setId($u['tenant']->getKey());
    Equipment::factory()->ofType(EquipmentType::Switch)->create(['name' => 'TOPO-SW']);
    Equipment::factory()->ofType(EquipmentType::Router)->create(['name' => 'TOPO-RTR']);

    $r = $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->getJson('/api/v1/topology')
        ->assertOk();

    $labels = collect($r->json('data.nodes'))->pluck('data.label')->all();
    expect($labels)->toContain('TOPO-SW', 'TOPO-RTR');
});

it('filters topology by site', function (): void {
    $u = apiUser('admin');
    TenantContext::setId($u['tenant']->getKey());
    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);
    Equipment::factory()->mountedAt(1, 1)->create(['rack_id' => $rack->getKey(), 'name' => 'IN-SITE']);
    Equipment::factory()->create(['name' => 'OUT-OF-SITE']);

    $r = $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->getJson("/api/v1/topology?site_id={$site->id}")
        ->assertOk();

    $labels = collect($r->json('data.nodes'))->pluck('data.label')->all();
    expect($labels)->toContain('IN-SITE')
        ->and($labels)->not->toContain('OUT-OF-SITE');
});
