<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

function bootRackFor(array $u, int $units = 12): Rack
{
    TenantContext::setId($u['tenant']->getKey());
    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'height_units' => $units]);
    TenantContext::clear();

    return $rack;
}

it('lists equipment scoped to current tenant', function (): void {
    $u = apiUser('admin');
    TenantContext::setId($u['tenant']->getKey());
    Equipment::factory()->count(3)->create();

    $other = Tenant::factory()->create();
    TenantContext::setId($other->getKey());
    Equipment::factory()->count(2)->create();

    $r = $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->getJson('/api/v1/equipment')
        ->assertOk();

    expect($r->json('data'))->toHaveCount(3);
});

it('shows an equipment with relationships loaded', function (): void {
    $u = apiUser('admin');
    TenantContext::setId($u['tenant']->getKey());
    $eq = Equipment::factory()->create(['name' => 'SHOW-EQ']);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->getJson("/api/v1/equipment/{$eq->id}")
        ->assertOk()
        ->assertJsonPath('data.attributes.name', 'SHOW-EQ');
});

it('creates a non-mounted equipment', function (): void {
    $u = apiUser('admin');

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson('/api/v1/equipment', [
            'name' => 'NEW-EQ',
            'type' => EquipmentType::Switch->value,
            'mounted' => false,
        ])
        ->assertCreated();

    expect(Equipment::query()->where('name', 'NEW-EQ')->exists())->toBeTrue();
});

it('allows creating a mounted equipment overlapping another (multi-device per U)', function (): void {
    $u = apiUser('admin');
    $rack = bootRackFor($u, 12);
    TenantContext::setId($u['tenant']->getKey());
    Equipment::factory()->mountedAt(3, 2)->create(['rack_id' => $rack->getKey()]);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson('/api/v1/equipment', [
            'name' => 'STACKED',
            'type' => 'switch',
            'mounted' => true,
            'rack_id' => $rack->id,
            'position_u_start' => 4,
            'position_u_height' => 1,
        ])
        ->assertStatus(201);

    expect(Equipment::query()->where('name', 'STACKED')->exists())->toBeTrue();
});

it('updates an equipment', function (): void {
    $u = apiUser('admin');
    TenantContext::setId($u['tenant']->getKey());
    $eq = Equipment::factory()->create(['name' => 'OLD']);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->patchJson("/api/v1/equipment/{$eq->id}", ['name' => 'NEW'])
        ->assertOk()
        ->assertJsonPath('data.attributes.name', 'NEW');
});

it('deletes an equipment', function (): void {
    $u = apiUser('admin');
    TenantContext::setId($u['tenant']->getKey());
    $eq = Equipment::factory()->create();

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->deleteJson("/api/v1/equipment/{$eq->id}")
        ->assertNoContent();
});

it('forbids cliente from creating equipment', function (): void {
    $u = apiUser('cliente');

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson('/api/v1/equipment', [
            'name' => 'no-go',
            'type' => 'switch',
            'mounted' => false,
        ])
        ->assertForbidden();
});
