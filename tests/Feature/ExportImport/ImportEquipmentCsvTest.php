<?php

declare(strict_types=1);

use App\Actions\Import\ImportEquipmentCsv;
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

function writeFixtureCsv(string $body): string
{
    Storage::disk('local')->makeDirectory('imports');
    $relative = 'imports/'.uniqid('fixture-').'.csv';
    Storage::disk('local')->put($relative, $body);

    return Storage::disk('local')->path($relative);
}

it('imports valid rows and creates missing site/room/rack hierarchy', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $csv = "name,type,vendor,model,serial,firmware,asset_tag,site,room,rack,mounted,position_u_start,position_u_height,status,management_ip,description\n"
        ."SW-IMP-01,switch,Cisco,C9300,SN1,17.9,AT-1,SedeImp,RoomImp,RackImp,true,5,1,active,10.0.0.10,desc\n"
        ."AP-IMP-01,access_point,Ubiquiti,U6,SN2,1.0,AT-2,,,,false,,,active,,desc\n";

    $path = writeFixtureCsv($csv);

    $import = app(ImportEquipmentCsv::class)->execute($path);

    expect($import->status)->toBe('completed')
        ->and($import->summary['created'])->toBe(2)
        ->and(Equipment::query()->where('name', 'SW-IMP-01')->exists())->toBeTrue()
        ->and(Equipment::query()->where('name', 'AP-IMP-01')->exists())->toBeTrue()
        // Hierarchy auto-created
        ->and(Site::query()->where('name', 'SedeImp')->exists())->toBeTrue()
        ->and(Room::query()->where('name', 'RoomImp')->exists())->toBeTrue()
        ->and(Rack::query()->where('name', 'RackImp')->exists())->toBeTrue();
});

it('rolls back the whole transaction when any row is invalid', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $csv = "name,type,vendor,model,serial,firmware,asset_tag,site,room,rack,mounted,position_u_start,position_u_height,status,management_ip,description\n"
        ."SW-OK,switch,Cisco,X,,,,,,,false,,,active,,\n"
        ."BAD-TYPE,nonexistent_type,X,X,,,,,,,false,,,active,,\n";

    $path = writeFixtureCsv($csv);
    $import = app(ImportEquipmentCsv::class)->execute($path);

    expect($import->status)->toBe('failed')
        ->and(count($import->summary['errors']))->toBe(1)
        ->and(Equipment::query()->where('name', 'SW-OK')->exists())->toBeFalse(); // rolled back
});

it('keeps valid rows when ignoreErrors=true even if other rows fail', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $csv = "name,type,vendor,model,serial,firmware,asset_tag,site,room,rack,mounted,position_u_start,position_u_height,status,management_ip,description\n"
        ."SW-OK,switch,Cisco,X,,,,,,,false,,,active,,\n"
        ."BAD-TYPE,nope,X,X,,,,,,,false,,,active,,\n";

    $path = writeFixtureCsv($csv);
    $import = app(ImportEquipmentCsv::class)->execute($path, ignoreErrors: true);

    expect($import->status)->toBe('completed')
        ->and($import->summary['created'])->toBe(1)
        ->and(count($import->summary['errors']))->toBe(1)
        ->and(Equipment::query()->where('name', 'SW-OK')->exists())->toBeTrue();
});

it('allows importing a mounted equipment that overlaps another (multi-device per U)', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create(['name' => 'Sede X']);
    $room = Room::factory()->create(['site_id' => $site->getKey(), 'name' => 'Room X']);
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'name' => 'Rack X', 'height_units' => 12]);
    Equipment::factory()->mountedAt(3, 2)->create(['rack_id' => $rack->getKey()]);

    $csv = "name,type,vendor,model,serial,firmware,asset_tag,site,room,rack,mounted,position_u_start,position_u_height,status,management_ip,description\n"
        ."STACKED,switch,X,X,,,,Sede X,Room X,Rack X,true,4,1,active,,\n";

    $path = writeFixtureCsv($csv);
    $import = app(ImportEquipmentCsv::class)->execute($path);

    expect($import->status)->toBe('completed')
        ->and(Equipment::query()->where('name', 'STACKED')->exists())->toBeTrue();
});
