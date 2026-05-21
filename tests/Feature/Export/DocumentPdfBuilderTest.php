<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\TopologySnapshot;
use App\Services\Export\DocumentPdfBuilder;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

it('loadData returns only the items selected per section', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $siteA = Site::factory()->create(['name' => 'Site-A']);
    $siteB = Site::factory()->create(['name' => 'Site-B']);
    $room = Room::factory()->create(['site_id' => $siteA->getKey(), 'name' => 'Room-1']);
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'name' => 'Rack-1']);
    $eq = Equipment::factory()->create(['rack_id' => $rack->getKey(), 'name' => 'SW-1']);
    $snap = TopologySnapshot::factory()->create(['title' => 'Snap-1']);

    $doc = Document::factory()->create([
        'parameters' => [
            'sections' => [
                'sites' => ['enabled' => true, 'description' => '', 'ids' => [$siteA->getKey()]],
                'rooms' => ['enabled' => true, 'description' => '', 'ids' => [$room->getKey()]],
                'racks' => ['enabled' => true, 'description' => '', 'ids' => [$rack->getKey()]],
                'equipment' => ['enabled' => true, 'description' => '', 'ids' => [$eq->getKey()]],
                'topologies' => [
                    'enabled' => true,
                    'description' => '',
                    'items' => [
                        ['id' => $snap->getKey(), 'orientation' => 'landscape'],
                    ],
                ],
            ],
            'options' => ['include_cover' => true, 'include_toc' => true],
        ],
    ]);

    $builder = app(DocumentPdfBuilder::class);
    $data = $builder->loadData($doc);

    expect($data['sites']->pluck('id')->all())->toBe([$siteA->getKey()])
        ->and($data['rooms']->pluck('id')->all())->toBe([$room->getKey()])
        ->and($data['racks']->pluck('id')->all())->toBe([$rack->getKey()])
        ->and($data['equipment']->pluck('id')->all())->toBe([$eq->getKey()])
        ->and($data['topologies']->first()->snapshot->id)->toBe($snap->getKey())
        ->and($data['topologies']->first()->orientation)->toBe('landscape');
});

it('builds a Site → Room → Rack hierarchy from the selected ids', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $siteA = Site::factory()->create(['name' => 'Sede-A']);
    $siteB = Site::factory()->create(['name' => 'Sede-B']);
    $roomA1 = Room::factory()->create(['site_id' => $siteA->getKey(), 'name' => 'Locale-A1']);
    $roomB1 = Room::factory()->create(['site_id' => $siteB->getKey(), 'name' => 'Locale-B1']);
    $rackA = Rack::factory()->create(['room_id' => $roomA1->getKey(), 'name' => 'Rack-A']);
    $rackB = Rack::factory()->create(['room_id' => $roomB1->getKey(), 'name' => 'Rack-B']);
    $racked = Equipment::factory()->create(['rack_id' => $rackA->getKey(), 'name' => 'SW-A1']);
    $unrackedA = Equipment::factory()->create([
        'rack_id' => null, 'room_id' => $roomA1->getKey(), 'name' => 'AP-A1',
    ]);

    $doc = Document::factory()->create([
        'parameters' => [
            'sections' => [
                'sites' => ['enabled' => true, 'description' => 's-desc', 'ids' => [$siteA->getKey(), $siteB->getKey()]],
                'rooms' => ['enabled' => true, 'description' => 'r-desc', 'ids' => [$roomA1->getKey(), $roomB1->getKey()]],
                'racks' => ['enabled' => true, 'description' => '', 'ids' => [$rackA->getKey(), $rackB->getKey()]],
                'equipment' => ['enabled' => true, 'description' => '', 'ids' => [$racked->getKey(), $unrackedA->getKey()]],
                'topologies' => ['enabled' => false, 'description' => '', 'items' => []],
            ],
            'options' => ['include_cover' => true, 'include_toc' => true],
        ],
    ]);

    $data = app(DocumentPdfBuilder::class)->loadData($doc);
    $hierarchy = $data['hierarchy'];

    expect($hierarchy)->toHaveCount(2);
    $siteANode = $hierarchy->firstWhere('site.id', $siteA->getKey());
    expect($siteANode)->not->toBeNull()
        ->and($siteANode->description)->toBe('s-desc')
        ->and($siteANode->rooms)->toHaveCount(1);

    $roomNode = $siteANode->rooms->first();
    expect($roomNode->room->id)->toBe($roomA1->getKey())
        ->and($roomNode->description)->toBe('r-desc')
        ->and($roomNode->racks->pluck('rack.id')->all())->toBe([$rackA->getKey()])
        ->and($roomNode->racks->first()->equipment->pluck('id')->all())->toBe([$racked->getKey()])
        ->and($roomNode->unracked->pluck('id')->all())->toBe([$unrackedA->getKey()]);
});

it('orders sites/rooms/racks by the saved id order, not by name', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    // Names deliberately reverse-alphabetical vs. the chosen order.
    $siteA = Site::factory()->create(['name' => 'Alpha']);
    $siteZ = Site::factory()->create(['name' => 'Zeta']);
    $roomA = Room::factory()->create(['site_id' => $siteZ->getKey(), 'name' => 'Aaa']);
    $roomB = Room::factory()->create(['site_id' => $siteZ->getKey(), 'name' => 'Bbb']);

    $doc = Document::factory()->create([
        'parameters' => [
            'sections' => [
                // Zeta before Alpha; Bbb before Aaa.
                'sites' => ['enabled' => true, 'description' => '', 'ids' => [$siteZ->getKey(), $siteA->getKey()]],
                'rooms' => ['enabled' => true, 'description' => '', 'ids' => [$roomB->getKey(), $roomA->getKey()]],
                'racks' => ['enabled' => false, 'description' => '', 'ids' => []],
                'equipment' => ['enabled' => false, 'description' => '', 'ids' => []],
                'topologies' => ['enabled' => false, 'description' => '', 'items' => []],
            ],
            'options' => ['include_cover' => true, 'include_toc' => true],
        ],
    ]);

    $hierarchy = app(DocumentPdfBuilder::class)->loadData($doc)['hierarchy'];

    expect($hierarchy->pluck('site.id')->all())->toBe([$siteZ->getKey(), $siteA->getKey()]);
    $zetaNode = $hierarchy->firstWhere('site.id', $siteZ->getKey());
    expect($zetaNode->rooms->pluck('room.id')->all())->toBe([$roomB->getKey(), $roomA->getKey()]);
});

it('rack node carries only the selected racked equipment, ordered', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);
    $eq1 = Equipment::factory()->create(['rack_id' => $rack->getKey(), 'name' => 'AAA']);
    $eq2 = Equipment::factory()->create(['rack_id' => $rack->getKey(), 'name' => 'BBB']);
    $eq3 = Equipment::factory()->create(['rack_id' => $rack->getKey(), 'name' => 'CCC']);

    $doc = Document::factory()->create([
        'parameters' => [
            'sections' => [
                'sites' => ['enabled' => true, 'description' => '', 'ids' => [$site->getKey()]],
                'rooms' => ['enabled' => true, 'description' => '', 'ids' => [$room->getKey()]],
                'racks' => ['enabled' => true, 'description' => '', 'ids' => [$rack->getKey()]],
                // Only eq3 and eq1 selected, in that order (eq2 excluded).
                'equipment' => ['enabled' => true, 'description' => '', 'ids' => [$eq3->getKey(), $eq1->getKey()]],
                'topologies' => ['enabled' => false, 'description' => '', 'items' => []],
            ],
            'options' => ['include_cover' => true, 'include_toc' => true],
        ],
    ]);

    $hierarchy = app(DocumentPdfBuilder::class)->loadData($doc)['hierarchy'];
    $rackNode = $hierarchy->first()->rooms->first()->racks->first();

    expect($rackNode->equipment->pluck('id')->all())->toBe([$eq3->getKey(), $eq1->getKey()]);
});

it('omits a rack whose room or site is not in the selection (strict mode)', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create();
    $roomA = Room::factory()->create(['site_id' => $site->getKey()]);
    $roomB = Room::factory()->create(['site_id' => $site->getKey()]);
    $rackA = Rack::factory()->create(['room_id' => $roomA->getKey()]);
    $rackB = Rack::factory()->create(['room_id' => $roomB->getKey()]);

    // Select both racks but only roomA → rackB must be dropped.
    $doc = Document::factory()->create([
        'parameters' => [
            'sections' => [
                'sites' => ['enabled' => true, 'description' => '', 'ids' => [$site->getKey()]],
                'rooms' => ['enabled' => true, 'description' => '', 'ids' => [$roomA->getKey()]],
                'racks' => ['enabled' => true, 'description' => '', 'ids' => [$rackA->getKey(), $rackB->getKey()]],
                'equipment' => ['enabled' => false, 'description' => '', 'ids' => []],
                'topologies' => ['enabled' => false, 'description' => '', 'items' => []],
            ],
            'options' => ['include_cover' => true, 'include_toc' => true],
        ],
    ]);

    $data = app(DocumentPdfBuilder::class)->loadData($doc);
    $hierarchy = $data['hierarchy'];

    expect($hierarchy)->toHaveCount(1);
    $rooms = $hierarchy->first()->rooms;
    expect($rooms)->toHaveCount(1)
        ->and($rooms->first()->racks->pluck('rack.id')->all())->toBe([$rackA->getKey()]);
});

it('eager-loads room racks so the floor plan partial can iterate them', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    Rack::factory()->create(['room_id' => $room->getKey()]);
    Rack::factory()->create(['room_id' => $room->getKey()]);

    $doc = Document::factory()->create([
        'parameters' => [
            'sections' => [
                'sites' => ['enabled' => true, 'description' => '', 'ids' => [$site->getKey()]],
                'rooms' => ['enabled' => true, 'description' => '', 'ids' => [$room->getKey()]],
                'racks' => ['enabled' => false, 'description' => '', 'ids' => []],
                'equipment' => ['enabled' => false, 'description' => '', 'ids' => []],
                'topologies' => ['enabled' => false, 'description' => '', 'items' => []],
            ],
            'options' => ['include_cover' => true, 'include_toc' => true],
        ],
    ]);

    $data = app(DocumentPdfBuilder::class)->loadData($doc);
    $roomLoaded = $data['rooms']->first();

    expect($roomLoaded->relationLoaded('racks'))->toBeTrue()
        ->and($roomLoaded->racks)->toHaveCount(2);
});

it('eager-loads rack equipment and photos for hierarchy rendering', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);
    Equipment::factory()->create(['rack_id' => $rack->getKey()]);

    $doc = Document::factory()->create([
        'parameters' => [
            'sections' => [
                'sites' => ['enabled' => true, 'description' => '', 'ids' => [$site->getKey()]],
                'rooms' => ['enabled' => true, 'description' => '', 'ids' => [$room->getKey()]],
                'racks' => ['enabled' => true, 'description' => '', 'ids' => [$rack->getKey()]],
                'equipment' => ['enabled' => false, 'description' => '', 'ids' => []],
                'topologies' => ['enabled' => false, 'description' => '', 'items' => []],
            ],
            'options' => ['include_cover' => true, 'include_toc' => true],
        ],
    ]);

    $data = app(DocumentPdfBuilder::class)->loadData($doc);
    $rackLoaded = $data['racks']->first();

    expect($rackLoaded->relationLoaded('equipment'))->toBeTrue()
        ->and($rackLoaded->relationLoaded('photos'))->toBeTrue()
        ->and($rackLoaded->relationLoaded('room'))->toBeTrue();
});

it('loadData returns null for disabled sections and empty collection for sections with no ids', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $doc = Document::factory()->create([
        'parameters' => [
            'sections' => [
                'sites' => ['enabled' => false, 'description' => '', 'ids' => []],
                'rooms' => ['enabled' => true, 'description' => '', 'ids' => []],
                'racks' => ['enabled' => false, 'description' => '', 'ids' => []],
                'equipment' => ['enabled' => false, 'description' => '', 'ids' => []],
                'topologies' => ['enabled' => false, 'description' => '', 'items' => []],
            ],
            'options' => ['include_cover' => true, 'include_toc' => true],
        ],
    ]);

    $data = app(DocumentPdfBuilder::class)->loadData($doc);

    expect($data['sites'])->toBeNull()
        ->and($data['rooms'])->not->toBeNull()
        ->and($data['rooms']->isEmpty())->toBeTrue()
        ->and($data['topologies'])->toBeNull();
});

it('loadData skips topology items whose snapshot no longer exists', function (): void {
    $tenant = Tenant::factory()->create();
    TenantContext::setId($tenant->getKey());

    $snap = TopologySnapshot::factory()->create();
    $doc = Document::factory()->create([
        'parameters' => [
            'sections' => [
                'sites' => ['enabled' => false, 'description' => '', 'ids' => []],
                'rooms' => ['enabled' => false, 'description' => '', 'ids' => []],
                'racks' => ['enabled' => false, 'description' => '', 'ids' => []],
                'equipment' => ['enabled' => false, 'description' => '', 'ids' => []],
                'topologies' => [
                    'enabled' => true,
                    'description' => '',
                    'items' => [
                        ['id' => $snap->getKey(), 'orientation' => 'portrait'],
                        ['id' => 99999, 'orientation' => 'landscape'],
                    ],
                ],
            ],
            'options' => ['include_cover' => true, 'include_toc' => true],
        ],
    ]);

    $data = app(DocumentPdfBuilder::class)->loadData($doc);

    expect($data['topologies']->count())->toBe(1)
        ->and($data['topologies']->first()->snapshot->id)->toBe($snap->getKey());
});
