<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

function bootEquipmentFor(array $u): Equipment
{
    TenantContext::setId($u['tenant']->getKey());
    $eq = Equipment::factory()->create();
    TenantContext::clear();

    return $eq;
}

it('lists interfaces of an equipment (nested route)', function (): void {
    $u = apiUser('admin');
    $eq = bootEquipmentFor($u);
    TenantContext::setId($u['tenant']->getKey());
    NetworkInterface::factory()->ethernet()->count(3)->create(['equipment_id' => $eq->getKey()])
        ->each(fn ($i, $idx) => $i->update(['name' => 'Gi0/'.($idx + 1)]));

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->getJson("/api/v1/equipment/{$eq->id}/interfaces")
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates an interface', function (): void {
    $u = apiUser('admin');
    $eq = bootEquipmentFor($u);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson("/api/v1/equipment/{$eq->id}/interfaces", [
            'equipment_id' => $eq->id,
            'name' => 'Gi0/99',
            'type' => 'ethernet',
            'media' => 'copper',
            'status' => 'up',
            'poe' => 'none',
        ])
        ->assertCreated()
        ->assertJsonPath('data.attributes.name', 'Gi0/99');
});

it('shows an interface on the shallow route', function (): void {
    $u = apiUser('admin');
    $eq = bootEquipmentFor($u);
    TenantContext::setId($u['tenant']->getKey());
    $if = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eq->getKey(), 'name' => 'IF-X']);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->getJson("/api/v1/interfaces/{$if->id}")
        ->assertOk()
        ->assertJsonPath('data.attributes.name', 'IF-X');
});

it('updates an interface', function (): void {
    $u = apiUser('admin');
    $eq = bootEquipmentFor($u);
    TenantContext::setId($u['tenant']->getKey());
    $if = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eq->getKey(), 'description' => 'old']);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->patchJson("/api/v1/interfaces/{$if->id}", ['description' => 'new'])
        ->assertOk()
        ->assertJsonPath('data.attributes.description', 'new');
});

it('deletes an interface', function (): void {
    $u = apiUser('admin');
    $eq = bootEquipmentFor($u);
    TenantContext::setId($u['tenant']->getKey());
    $if = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eq->getKey()]);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->deleteJson("/api/v1/interfaces/{$if->id}")
        ->assertNoContent();
});
