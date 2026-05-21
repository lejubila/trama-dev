<?php

declare(strict_types=1);

use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

function bootIfPair(array $u): array
{
    TenantContext::setId($u['tenant']->getKey());
    $a = Equipment::factory()->create();
    $b = Equipment::factory()->create();
    $ifA = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $a->getKey(), 'name' => 'p1']);
    $ifB = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $b->getKey(), 'name' => 'p1']);
    TenantContext::clear();

    return [$ifA, $ifB];
}

it('lists connections', function (): void {
    $u = apiUser('admin');
    [$a, $b] = bootIfPair($u);
    TenantContext::setId($u['tenant']->getKey());
    Connection::create([
        'tenant_id' => $u['tenant']->getKey(),
        'from_interface_id' => $a->id,
        'to_interface_id' => $b->id,
        'cable_type' => 'utp_cat6',
        'status' => 'active',
    ]);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->getJson('/api/v1/connections')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('creates a connection through the service layer', function (): void {
    $u = apiUser('admin');
    [$a, $b] = bootIfPair($u);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson('/api/v1/connections', [
            'from_interface_id' => $a->id,
            'to_interface_id' => $b->id,
            'cable_type' => 'utp_cat6',
        ])
        ->assertCreated();

    expect(Connection::query()->count())->toBe(1);
});

it('refuses self-connection at the API boundary', function (): void {
    $u = apiUser('admin');
    [$a] = bootIfPair($u);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson('/api/v1/connections', [
            'from_interface_id' => $a->id,
            'to_interface_id' => $a->id,
            'cable_type' => 'utp_cat6',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['from_interface_id']);
});

it('deletes a connection', function (): void {
    $u = apiUser('admin');
    [$a, $b] = bootIfPair($u);
    TenantContext::setId($u['tenant']->getKey());
    $c = Connection::create([
        'tenant_id' => $u['tenant']->getKey(),
        'from_interface_id' => $a->id,
        'to_interface_id' => $b->id,
        'cable_type' => 'utp_cat6',
        'status' => 'active',
    ]);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->deleteJson("/api/v1/connections/{$c->id}")
        ->assertNoContent();
});
