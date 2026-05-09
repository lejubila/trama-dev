<?php

declare(strict_types=1);

use App\Enums\ConnectionStatus;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Tenant;
use App\Services\ConnectionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;

afterEach(function (): void {
    TenantContext::clear();
});

/**
 * @return array{0: NetworkInterface, 1: NetworkInterface, 2: Tenant}
 */
function makeInterfacePair(): array
{
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $eq1 = Equipment::factory()->create();
    $eq2 = Equipment::factory()->create();

    return [
        NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eq1->getKey(), 'name' => 'p1']),
        NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eq2->getKey(), 'name' => 'p1']),
        $tenant,
    ];
}

it('connects two free interfaces in the same tenant', function (): void {
    [$a, $b] = makeInterfacePair();

    $conn = app(ConnectionService::class)->connect($a, $b, ['cable_type' => 'utp_cat6']);

    expect($conn->status)->toBe(ConnectionStatus::Active)
        ->and($conn->from_interface_id)->toBe($a->getKey())
        ->and($conn->to_interface_id)->toBe($b->getKey());
});

it('refuses to connect an interface to itself', function (): void {
    [$a] = makeInterfacePair();

    app(ConnectionService::class)->connect($a, $a, ['cable_type' => 'utp_cat6']);
})->throws(InvalidArgumentException::class, 'itself');

it('refuses connection between interfaces of different tenants', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    TenantContext::setId($tenantA->getKey());
    $eqA = Equipment::factory()->create();
    $ifA = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eqA->getKey(), 'name' => 'p1']);

    TenantContext::setId($tenantB->getKey());
    $eqB = Equipment::factory()->create();
    $ifB = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eqB->getKey(), 'name' => 'p1']);

    app(ConnectionService::class)->connect($ifA, $ifB, ['cable_type' => 'utp_cat6']);
})->throws(InvalidArgumentException::class, 'different tenants');

it('refuses a second active connection on the same interface (service-level)', function (): void {
    [$a, $b] = makeInterfacePair();

    $eq3 = Equipment::factory()->create();
    $c = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eq3->getKey(), 'name' => 'p1']);

    app(ConnectionService::class)->connect($a, $b, ['cable_type' => 'utp_cat6']);

    app(ConnectionService::class)->connect($a, $c, ['cable_type' => 'utp_cat6']);
})->throws(InvalidArgumentException::class, 'already has an active connection');

it('enforces the partial unique index at the DB level too', function (): void {
    [$a, $b, $tenant] = makeInterfacePair();

    Connection::create([
        'tenant_id' => $tenant->getKey(),
        'from_interface_id' => $a->getKey(),
        'to_interface_id' => $b->getKey(),
        'cable_type' => 'utp_cat6',
        'status' => ConnectionStatus::Active,
    ]);

    Connection::create([
        'tenant_id' => $tenant->getKey(),
        'from_interface_id' => $a->getKey(),
        'to_interface_id' => $b->getKey(),
        'cable_type' => 'utp_cat6',
        'status' => ConnectionStatus::Active,
    ]);
})->throws(UniqueConstraintViolationException::class);

it('allows multiple non-active connections on the same interface', function (): void {
    [$a, $b, $tenant] = makeInterfacePair();

    Connection::create([
        'tenant_id' => $tenant->getKey(),
        'from_interface_id' => $a->getKey(),
        'to_interface_id' => $b->getKey(),
        'cable_type' => 'utp_cat6',
        'status' => ConnectionStatus::Decommissioned,
    ]);
    Connection::create([
        'tenant_id' => $tenant->getKey(),
        'from_interface_id' => $a->getKey(),
        'to_interface_id' => $b->getKey(),
        'cable_type' => 'utp_cat6',
        'status' => ConnectionStatus::Planned,
    ]);

    expect(Connection::query()->count())->toBe(2);
});
