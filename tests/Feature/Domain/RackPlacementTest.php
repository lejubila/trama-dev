<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Tenant;
use App\Services\RackPlacementService;
use App\Support\Tenancy\TenantContext;

beforeEach(function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());
    $this->rack = Rack::factory()->create(['height_units' => 12]);
    $this->svc = app(RackPlacementService::class);
});

afterEach(function (): void {
    TenantContext::clear();
});

it('detects free slot in an empty rack', function (): void {
    expect($this->svc->canPlace($this->rack, 1, 2))->toBeTrue();
});

it('rejects placement that overflows rack height', function (): void {
    expect($this->svc->canPlace($this->rack, 11, 3))->toBeFalse();
});

it('rejects overlap with already mounted equipment', function (): void {
    Equipment::factory()->mountedAt(3, 2)->create([
        'rack_id' => $this->rack->getKey(),
    ]);

    expect($this->svc->canPlace($this->rack, 4, 1))->toBeFalse()
        ->and($this->svc->canPlace($this->rack, 2, 2))->toBeFalse()
        ->and($this->svc->canPlace($this->rack, 1, 2))->toBeTrue()
        ->and($this->svc->canPlace($this->rack, 5, 8))->toBeTrue();
});

it('ignores the equipment we are relocating', function (): void {
    $eq = Equipment::factory()->mountedAt(3, 2)->create([
        'rack_id' => $this->rack->getKey(),
    ]);

    // Without exclusion the slot is occupied; with it, free.
    expect($this->svc->canPlace($this->rack, 3, 2))->toBeFalse()
        ->and($this->svc->canPlace($this->rack, 3, 2, $eq))->toBeTrue();
});

it('lists available start-U positions for a given height', function (): void {
    Equipment::factory()->mountedAt(3, 2)->create(['rack_id' => $this->rack->getKey()]);
    Equipment::factory()->mountedAt(8, 1)->create(['rack_id' => $this->rack->getKey()]);

    $slots = $this->svc->findAvailableSlots($this->rack, 1);

    expect($slots)->toContain(1, 2, 5, 6, 7, 9, 10, 11, 12)
        ->and($slots)->not->toContain(3, 4, 8);
});
