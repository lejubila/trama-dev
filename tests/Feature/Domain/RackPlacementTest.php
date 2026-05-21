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

it('allows overlap with already mounted equipment (multi-device per U is supported)', function (): void {
    Equipment::factory()->mountedAt(3, 2)->create([
        'rack_id' => $this->rack->getKey(),
    ]);

    // Overlap is now permitted; only bounds matter.
    expect($this->svc->canPlace($this->rack, 4, 1))->toBeTrue()
        ->and($this->svc->canPlace($this->rack, 2, 2))->toBeTrue()
        ->and($this->svc->canPlace($this->rack, 3, 2))->toBeTrue()
        ->and($this->svc->canPlace($this->rack, 5, 8))->toBeTrue();
});

it('still rejects placements that overflow the rack', function (): void {
    Equipment::factory()->mountedAt(3, 2)->create([
        'rack_id' => $this->rack->getKey(),
    ]);

    expect($this->svc->canPlace($this->rack, 11, 3))->toBeFalse() // 11..13 in a 12U rack
        ->and($this->svc->canPlace($this->rack, 0, 1))->toBeFalse(); // startU < 1
});

it('lists every in-bounds start-U position as available', function (): void {
    Equipment::factory()->mountedAt(3, 2)->create(['rack_id' => $this->rack->getKey()]);
    Equipment::factory()->mountedAt(8, 1)->create(['rack_id' => $this->rack->getKey()]);

    $slots = $this->svc->findAvailableSlots($this->rack, 1);

    // With multi-device-per-U allowed, every U from 1..12 is a valid start.
    expect($slots)->toEqualCanonicalizing(range(1, 12));
});
