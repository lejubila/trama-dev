<?php

declare(strict_types=1);

use App\Actions\Export\ExportEquipmentCsv;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;

afterEach(function (): void {
    TenantContext::clear();
});

it('writes a CSV with the canonical header and one row per equipment', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create(['name' => 'Sede Test']);
    $room = Room::factory()->create(['site_id' => $site->getKey(), 'name' => 'CED']);
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'name' => 'R1']);

    Equipment::factory()->mountedAt(1, 1)->create([
        'rack_id' => $rack->getKey(),
        'name' => 'SW-CSV-1',
        'vendor' => 'Cisco',
    ]);
    Equipment::factory()->create([
        'name' => 'AP-CSV-1',
        'vendor' => 'Ubiquiti',
    ]);

    $relative = app(ExportEquipmentCsv::class)->execute();

    $content = Storage::disk('local')->get($relative);
    expect($content)->toContain('name,type,vendor');
    expect($content)->toContain('SW-CSV-1', 'AP-CSV-1', 'Cisco', 'Ubiquiti');

    $lines = array_filter(explode("\n", trim($content)));
    expect($lines)->toHaveCount(3); // header + 2 rows
});

it('isolates the CSV to the active tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    TenantContext::setId($tenantA->getKey());
    Equipment::factory()->create(['name' => 'EQ-A-CSV']);

    $tenantB = Tenant::factory()->create();
    TenantContext::setId($tenantB->getKey());
    Equipment::factory()->create(['name' => 'EQ-B-CSV']);

    TenantContext::setId($tenantA->getKey());
    $relative = app(ExportEquipmentCsv::class)->execute();
    $content = Storage::disk('local')->get($relative);

    expect($content)->toContain('EQ-A-CSV')
        ->and($content)->not->toContain('EQ-B-CSV');
});
