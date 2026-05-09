<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tag;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

it('auto-fills tenant_id on Site/Room/Rack/Equipment/Tag create', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);
    $eq = Equipment::factory()->create();
    $tag = Tag::factory()->create();

    expect($site->tenant_id)->toBe($tenant->getKey())
        ->and($room->tenant_id)->toBe($tenant->getKey())
        ->and($rack->tenant_id)->toBe($tenant->getKey())
        ->and($eq->tenant_id)->toBe($tenant->getKey())
        ->and($tag->tenant_id)->toBe($tenant->getKey());
});

it('isolates Equipment between tenants via the global scope', function (): void {
    $a = Tenant::factory()->create(['slug' => 'tenant-a']);
    $b = Tenant::factory()->create(['slug' => 'tenant-b']);

    TenantContext::setId($a->getKey());
    Equipment::factory()->count(3)->create();

    TenantContext::setId($b->getKey());
    Equipment::factory()->count(2)->create();

    TenantContext::setId($a->getKey());
    expect(Equipment::query()->count())->toBe(3);

    TenantContext::setId($b->getKey());
    expect(Equipment::query()->count())->toBe(2);
});

it('cascades cleanup when a Site is deleted', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    Rack::factory()->create(['room_id' => $room->getKey()]);

    expect(Room::query()->count())->toBe(1)
        ->and(Rack::query()->count())->toBe(1);

    // Force-delete to bypass soft-deletes and trigger DB cascade
    $site->forceDelete();

    expect(Room::query()->withTrashed()->count())->toBe(0)
        ->and(Rack::query()->withTrashed()->count())->toBe(0);
});
