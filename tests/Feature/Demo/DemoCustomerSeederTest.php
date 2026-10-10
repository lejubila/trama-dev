<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Enums\UserRole;
use App\Models\Connection;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\TopologySnapshot;
use App\Models\User;
use App\Models\VpnRemoteAccess;
use App\Models\WifiNetwork;
use App\Services\TenantTransfer\TenantArchiveExporter;
use App\Services\TenantTransfer\TenantArchiveImporter;
use App\Services\TenantTransfer\TenantArchiveReader;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\DemoCustomerSeeder;
use Illuminate\Support\Facades\Storage;

afterEach(function (): void {
    TenantContext::clear();
});

/**
 * @return array<string, int>
 */
function demoCustomerCounts(Tenant $tenant): array
{
    TenantContext::setId((int) $tenant->getKey());

    return [
        'sites' => Site::query()->count(),
        'rooms' => Room::query()->count(),
        'racks' => Rack::query()->count(),
        'equipment' => Equipment::query()->count(),
        'vms' => Equipment::query()->where('type', EquipmentType::VirtualMachine)->whereNotNull('host_equipment_id')->count(),
        'connections' => Connection::query()->count(),
        'wifi' => WifiNetwork::query()->count(),
        'vpn' => VpnRemoteAccess::query()->count(),
    ];
}

it('builds the demo customer', function (): void {
    $this->seed(DemoCustomerSeeder::class);

    $tenant = Tenant::query()->where('slug', DemoCustomerSeeder::SLUG)->firstOrFail();

    expect(demoCustomerCounts($tenant))->toBe([
        'sites' => 1, 'rooms' => 2, 'racks' => 1, 'equipment' => 24, 'vms' => 4,
        'connections' => 24, 'wifi' => 2, 'vpn' => 1,
    ]);

    // Rack devices must actually be mounted, or the rack elevation is empty.
    expect(Equipment::query()->whereNotNull('rack_id')->where('mounted', true)->orderByDesc('position_u_start')->pluck('name')->all())
        ->toBe(['PP-01', 'RTR-INTERNET', 'FW-01', 'SW-CORE', 'PVE-01', 'NAS-01', 'UPS-01']);
});

it('refuses to create the demo customer twice', function (): void {
    $this->seed(DemoCustomerSeeder::class);
    $this->seed(DemoCustomerSeeder::class);
})->throws(RuntimeException::class);

it('survives an export / import round trip', function (): void {
    Storage::fake('public');
    $this->seed(DemoCustomerSeeder::class);
    $tenant = Tenant::query()->where('slug', DemoCustomerSeeder::SLUG)->firstOrFail();
    $expected = demoCustomerCounts($tenant);

    $zip = app(TenantArchiveExporter::class)->export($tenant);
    $archive = app(TenantArchiveReader::class)->read($zip);
    $copy = app(TenantArchiveImporter::class)->import($archive, User::factory()->create(['role' => UserRole::Admin]), [
        'name' => 'Copia', 'slug' => null, 'domain' => null,
    ]);
    $archive->close();

    expect(demoCustomerCounts($copy))->toBe($expected);
});

it('ships a demo archive that the reset can load', function (): void {
    Storage::fake('public');
    config(['demo.enabled' => true, 'demo.archive' => 'database/demo']);

    $this->artisan('demo:reset', ['--no-maintenance' => true])->assertSuccessful();

    $tenant = Tenant::query()->sole();
    TenantContext::setId((int) $tenant->getKey());
    expect($tenant->name)->toBe('Studio Rossetti & Partners')
        ->and(demoCustomerCounts($tenant)['equipment'])->toBe(24)
        ->and(TopologySnapshot::query()->count())->toBe(5)
        ->and(Document::query()->count())->toBe(2);
});

it('falls back to the seeder when no archive is shipped', function (): void {
    Storage::fake('public');
    config(['demo.enabled' => true, 'demo.archive' => 'database/does-not-exist']);

    $this->artisan('demo:reset', ['--no-maintenance' => true])->assertSuccessful();

    expect(Tenant::query()->pluck('slug')->all())->toBe([DemoCustomerSeeder::SLUG]);
});
