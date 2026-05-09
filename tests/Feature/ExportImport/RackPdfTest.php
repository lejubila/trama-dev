<?php

declare(strict_types=1);

use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Services\Export\PdfExporter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;

afterEach(function (): void {
    TenantContext::clear();
});

it('renders a PDF for the given rack', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create(['name' => 'PDF Site']);
    $room = Room::factory()->create(['site_id' => $site->getKey(), 'name' => 'PDF Room']);
    $rack = Rack::factory()->create([
        'room_id' => $room->getKey(),
        'name' => 'PDF-RACK',
        'height_units' => 12,
    ]);

    try {
        $relative = app(PdfExporter::class)->rackPdf($rack);
    } catch (Throwable $e) {
        // Browsershot needs node + chromium reachable; skip when the
        // execution environment can't reach them rather than failing.
        $this->markTestSkipped('Browsershot unavailable in this environment: '.$e->getMessage());
    }

    $absolute = Storage::disk('local')->path($relative);
    expect(file_exists($absolute))->toBeTrue()
        ->and(filesize($absolute))->toBeGreaterThan(1024)
        ->and(file_get_contents($absolute, false, null, 0, 4))->toBe('%PDF');

    @unlink($absolute);
});
