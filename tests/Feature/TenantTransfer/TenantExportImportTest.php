<?php

declare(strict_types=1);

use App\Actions\Interfaces\CreateKeystonePair;
use App\Enums\EquipmentType;
use App\Enums\InterfaceType;
use App\Livewire\Tenants\Import as TenantsImport;
use App\Models\Connection;
use App\Models\DeviceIcon;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\LinkGroup;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\RackPhoto;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TopologySnapshot;
use App\Models\User;
use App\Models\VpnRemoteAccess;
use App\Models\VpnRemoteAccessClient;
use App\Models\VpnSiteToSite;
use App\Models\WifiAssociation;
use App\Models\WifiNetwork;
use App\Services\TenantTransfer\InvalidTenantArchiveException;
use App\Services\TenantTransfer\TenantArchive;
use App\Services\TenantTransfer\TenantArchiveExporter;
use App\Services\TenantTransfer\TenantArchiveImporter;
use App\Services\TenantTransfer\TenantArchiveReader;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('public');
});

afterEach(function (): void {
    TenantContext::clear();
});

function transferUser(string $role, ?Tenant $tenant = null): User
{
    /** @var User $user */
    $user = User::factory()->create();
    $user->forceFill(['role' => $role])->save();
    if ($tenant !== null) {
        $user->tenants()->attach($tenant);
        actingAsInTenant($user, $tenant);
    }

    return $user;
}

/**
 * A tenant touching every exported section, with files on the fake disk.
 *
 * @return array<string, mixed>
 */
function buildFullTenant(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Acme', 'slug' => 'acme', 'settings' => ['topology_icon_size_px' => 60]]);
    $user = transferUser('admin', $tenant);
    $tid = $tenant->getKey();
    $disk = Storage::disk('public');

    $site = Site::factory()->create(['name' => 'HQ']);
    $disk->put("floor-plans/{$tid}/plan.png", 'PLAN');
    $room = Room::factory()->create(['site_id' => $site->getKey(), 'floor_plan_path' => "floor-plans/{$tid}/plan.png"]);
    $disk->put("icons/{$tid}/racks/rack.png", 'RACKICON');
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'height_units' => 42, 'icon_path' => "icons/{$tid}/racks/rack.png"]);
    $disk->put("rack-photos/{$tid}/{$rack->getKey()}/photo.jpg", 'PHOTO');
    RackPhoto::factory()->create(['rack_id' => $rack->getKey(), 'photo_path' => "rack-photos/{$tid}/{$rack->getKey()}/photo.jpg"]);

    $disk->put("icons/{$tid}/equipment/sw.png", 'SWICON');
    $sw = Equipment::factory()->mountedAt(1, 1)->create(['rack_id' => $rack->getKey(), 'room_id' => $room->getKey(), 'name' => 'SW-1', 'icon_path' => "icons/{$tid}/equipment/sw.png"]);
    $fw = Equipment::factory()->create(['name' => 'FW-1', 'type' => EquipmentType::Firewall, 'mounted' => false, 'room_id' => $room->getKey()]);
    $hv = Equipment::factory()->create(['name' => 'HV-1', 'type' => EquipmentType::Hypervisor, 'mounted' => false]);
    $vm = Equipment::factory()->create(['name' => 'VM-1', 'type' => EquipmentType::VirtualMachine, 'mounted' => false, 'host_equipment_id' => $hv->getKey()]);
    $ap = Equipment::factory()->create(['name' => 'AP-1', 'type' => EquipmentType::AccessPoint, 'mounted' => false]);
    $pp = Equipment::factory()->create(['name' => 'PP-1', 'type' => EquipmentType::PatchPanel, 'mounted' => false]);
    app(CreateKeystonePair::class)->execute($pp, ['name' => '1', 'index' => 1]);

    $sw1 = NetworkInterface::factory()->create(['equipment_id' => $sw->getKey(), 'name' => 'Gi0/1']);
    $sw2 = NetworkInterface::factory()->create(['equipment_id' => $sw->getKey(), 'name' => 'Gi0/2']);
    $fwWan = NetworkInterface::factory()->create(['equipment_id' => $fw->getKey(), 'name' => 'wan']);
    $fwLan = NetworkInterface::factory()->create(['equipment_id' => $fw->getKey(), 'name' => 'lan', 'vlans_allowed' => [10, 20]]);
    $fwSub = NetworkInterface::factory()->create(['equipment_id' => $fw->getKey(), 'name' => 'lan.10', 'type' => InterfaceType::Virtual, 'backed_by_interface_id' => $fwLan->getKey()]);
    $pnic = NetworkInterface::factory()->create(['equipment_id' => $hv->getKey(), 'name' => 'eno1']);
    $vnic = NetworkInterface::factory()->create(['equipment_id' => $vm->getKey(), 'name' => 'eth0', 'type' => InterfaceType::Virtual, 'backed_by_interface_id' => $pnic->getKey()]);
    $apRadio = NetworkInterface::factory()->create(['equipment_id' => $ap->getKey(), 'name' => 'wlan0', 'type' => InterfaceType::Wireless]);
    $apEth = NetworkInterface::factory()->create(['equipment_id' => $ap->getKey(), 'name' => 'eth0']);

    $c1 = Connection::create(['tenant_id' => $tid, 'from_interface_id' => $sw1->getKey(), 'to_interface_id' => $fwLan->getKey(), 'cable_type' => 'utp_cat6']);
    $c2 = Connection::create(['tenant_id' => $tid, 'from_interface_id' => $sw2->getKey(), 'to_interface_id' => $apEth->getKey(), 'cable_type' => 'utp_cat6']);
    $lag = LinkGroup::factory()->create(['name' => 'Po1']);
    $lag->connections()->attach([$c1->getKey(), $c2->getKey()]);

    $tag = Tag::factory()->create(['name' => 'core']);
    $sw->tags()->attach($tag);
    $c1->tags()->attach($tag);

    $wifi = WifiNetwork::factory()->create(['ssid' => 'Office', 'site_id' => $site->getKey()]);
    $wifi->broadcasters()->attach($apRadio->getKey());
    WifiAssociation::factory()->create(['wifi_network_id' => $wifi->getKey(), 'client_interface_id' => $vnic->getKey(), 'preferred_broadcaster_interface_id' => $apRadio->getKey()]);

    $ra = VpnRemoteAccess::factory()->create(['firewall_interface_id' => $fwWan->getKey()]);
    VpnRemoteAccessClient::factory()->create(['vpn_remote_access_id' => $ra->getKey(), 'client_interface_id' => $vnic->getKey()]);
    $s2s = VpnSiteToSite::factory()->create(['endpoint_a_interface_id' => $fwWan->getKey(), 'endpoint_b_interface_id' => $pnic->getKey()]);

    $disk->put("icons/{$tid}/device.png", 'DEVICON');
    DeviceIcon::create(['tenant_id' => $tid, 'kind' => 'switch', 'image_path' => "icons/{$tid}/device.png"]);

    $disk->put("topology-snapshots/{$tid}/snap.png", 'SNAP');
    $snap = TopologySnapshot::factory()->create([
        'image_path' => "topology-snapshots/{$tid}/snap.png",
        'view_state' => [
            'siteId' => $site->getKey(),
            'tagFilters' => [(string) $tag->getKey()],
            'sessionHiddenIds' => [$ap->getKey()],
            'portSettings' => [(string) $sw1->getKey() => ['ip' => true]],
            'nodePositions' => [
                'eq-'.$sw->getKey() => [1, 2],
                'host-'.$hv->getKey() => [3, 4],
                'rack-'.$rack->getKey() => [5, 6],
                'wifi-'.$wifi->getKey() => [7, 8],
                'vpn-ra-'.$ra->getKey() => [9, 10],
                'vpn-stos-'.$s2s->getKey() => [11, 12],
            ],
        ],
    ]);

    $disk->put("documents/{$tid}/document-1.pdf", '%PDF');
    Document::factory()->create([
        'pdf_path' => "documents/{$tid}/document-1.pdf",
        'parameters' => ['sections' => [
            'sites' => ['enabled' => true, 'ids' => [$site->getKey()]],
            'rooms' => ['enabled' => true, 'ids' => [$room->getKey()]],
            'racks' => ['enabled' => true, 'ids' => [$rack->getKey()]],
            'equipment' => ['enabled' => true, 'ids' => [$sw->getKey(), $fw->getKey()], 'ports_excluded' => [$sw2->getKey()]],
            'topologies' => ['enabled' => true, 'items' => [['id' => $snap->getKey(), 'orientation' => 'portrait']]],
        ]],
    ]);

    // Soft-deleted rows must not travel, nor the ports of a deleted device.
    $gone = Equipment::factory()->create(['name' => 'GONE', 'mounted' => false]);
    NetworkInterface::factory()->create(['equipment_id' => $gone->getKey(), 'name' => 'x']);
    $gone->delete();

    return compact('tenant', 'user', 'site', 'rack', 'sw', 'hv', 'tag');
}

function exportAndRead(Tenant $tenant): TenantArchive
{
    $zip = app(TenantArchiveExporter::class)->export($tenant);

    return app(TenantArchiveReader::class)->read($zip);
}

/** @return array<string, int> */
function tenantCounts(int $tenantId): array
{
    $out = [];
    foreach (['sites', 'rooms', 'racks', 'rack_photos', 'equipment', 'interfaces', 'connections', 'link_groups', 'tags', 'wifi_networks', 'wifi_associations', 'vpn_remote_access', 'vpn_remote_access_clients', 'vpn_site_to_site', 'device_icons', 'topology_snapshots', 'documents'] as $table) {
        $q = DB::table($table)->where('tenant_id', $tenantId);
        if (in_array($table, ['sites', 'rooms', 'racks', 'equipment'], true)) {
            $q->whereNull('deleted_at');
        }
        if ($table === 'interfaces') {
            $q->whereIn('equipment_id', DB::table('equipment')->whereNull('deleted_at')->select('id'));
        }
        $out[$table] = $q->count();
    }

    return $out;
}

it('round-trips a whole tenant into a new one with remapped ids and files', function (): void {
    $s = buildFullTenant();
    $old = $s['tenant'];
    $before = tenantCounts($old->getKey());

    $archive = exportAndRead($old);
    expect($archive->counts()['equipment'])->toBe(6)
        ->and($archive->counts()['files'])->toBe(7);

    $new = app(TenantArchiveImporter::class)->import($archive, $s['user'], ['name' => 'Acme copia', 'slug' => 'acme-copia', 'domain' => null]);
    $nid = $new->getKey();

    expect($new->name)->toBe('Acme copia')
        ->and($new->fresh()->settings)->toBe(['topology_icon_size_px' => 60])
        ->and(tenantCounts($nid))->toBe($before)
        ->and(tenantCounts($old->getKey()))->toBe($before);

    TenantContext::setId($nid);

    // Every interface reference points inside the new tenant.
    $newIfaceIds = DB::table('interfaces')->where('tenant_id', $nid)->pluck('id')->all();
    foreach (DB::table('connections')->where('tenant_id', $nid)->get() as $c) {
        expect($newIfaceIds)->toContain($c->from_interface_id)->toContain($c->to_interface_id);
    }

    $pp = Equipment::query()->where('name', 'PP-1')->firstOrFail();
    [$front, $rear] = $pp->interfaces()->orderBy('side')->get()->all();
    expect($front->paired_interface_id)->toBe($rear->getKey())
        ->and($rear->paired_interface_id)->toBe($front->getKey());

    $fw = Equipment::query()->where('name', 'FW-1')->firstOrFail();
    $byName = $fw->interfaces()->get()->keyBy('name');
    expect($byName['lan.10']->backed_by_interface_id)->toBe($byName['lan']->getKey())
        ->and($byName['lan']->vlans_allowed)->toBe([10, 20]);

    $hv = Equipment::query()->where('name', 'HV-1')->firstOrFail();
    $vm = Equipment::query()->where('name', 'VM-1')->firstOrFail();
    $pnic = $hv->interfaces()->firstOrFail();
    expect($vm->host_equipment_id)->toBe($hv->getKey())
        ->and($vm->interfaces()->firstOrFail()->backed_by_interface_id)->toBe($pnic->getKey());

    $sw = Equipment::query()->where('name', 'SW-1')->firstOrFail();
    $tag = Tag::query()->where('name', 'core')->firstOrFail();
    expect($sw->tags()->pluck('tags.id')->all())->toBe([$tag->getKey()])
        ->and(Connection::query()->whereHas('tags')->count())->toBe(1)
        ->and(LinkGroup::query()->firstOrFail()->connections()->count())->toBe(2);

    $wifi = WifiNetwork::query()->firstOrFail();
    $ap = Equipment::query()->where('name', 'AP-1')->firstOrFail();
    $apRadio = $ap->interfaces()->where('name', 'wlan0')->firstOrFail();
    expect($wifi->broadcasters()->pluck('interfaces.id')->all())->toBe([$apRadio->getKey()])
        ->and(WifiAssociation::query()->firstOrFail()->preferred_broadcaster_interface_id)->toBe($apRadio->getKey());

    $fwWan = $byName['wan'];
    expect(VpnRemoteAccess::query()->firstOrFail()->firewall_interface_id)->toBe($fwWan->getKey())
        ->and(VpnSiteToSite::query()->firstOrFail()->endpoint_b_interface_id)->toBe($pnic->getKey());

    // JSON blobs carry the new ids.
    $site = Site::query()->firstOrFail();
    $rack = Rack::query()->firstOrFail();
    $snap = TopologySnapshot::query()->firstOrFail();
    $ra = VpnRemoteAccess::query()->firstOrFail();
    $s2s = VpnSiteToSite::query()->firstOrFail();
    $sw1 = $sw->interfaces()->where('name', 'Gi0/1')->firstOrFail();
    $sw2 = $sw->interfaces()->where('name', 'Gi0/2')->firstOrFail();
    expect($snap->view_state['siteId'])->toBe($site->getKey())
        ->and($snap->view_state['tagFilters'])->toBe([(string) $tag->getKey()])
        ->and($snap->view_state['sessionHiddenIds'])->toBe([$ap->getKey()])
        ->and(array_keys($snap->view_state['portSettings']))->toBe([$sw1->getKey()])
        ->and(array_keys($snap->view_state['nodePositions']))->toBe([
            'eq-'.$sw->getKey(), 'host-'.$hv->getKey(), 'rack-'.$rack->getKey(),
            'wifi-'.$wifi->getKey(), 'vpn-ra-'.$ra->getKey(), 'vpn-stos-'.$s2s->getKey(),
        ]);

    $params = Document::query()->firstOrFail()->parameters['sections'];
    expect($params['sites']['ids'])->toBe([$site->getKey()])
        ->and($params['equipment']['ids'])->toBe([$sw->getKey(), $fw->getKey()])
        ->and($params['equipment']['ports_excluded'])->toBe([$sw2->getKey()])
        ->and($params['topologies']['items'][0]['id'])->toBe($snap->getKey());

    // Files are copied under the new tenant (and new rack for photos).
    $disk = Storage::disk('public');
    expect($sw->icon_path)->toBe("icons/{$nid}/equipment/sw.png")
        ->and($disk->get($sw->icon_path))->toBe('SWICON')
        ->and(Room::query()->firstOrFail()->floor_plan_path)->toBe("floor-plans/{$nid}/plan.png")
        ->and(RackPhoto::query()->firstOrFail()->photo_path)->toBe("rack-photos/{$nid}/{$rack->getKey()}/photo.jpg")
        ->and($disk->exists($snap->image_path))->toBeTrue()
        ->and($disk->exists(Document::query()->firstOrFail()->pdf_path))->toBeTrue()
        ->and($disk->exists("icons/{$old->getKey()}/equipment/sw.png"))->toBeTrue();

    expect(DB::table('imports')->where('tenant_id', $nid)->where('type', 'tenant_archive')->exists())->toBeTrue();
});

it('lets a tecnico download the archive and forbids a cliente', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    transferUser('tecnico', $tenant);
    Site::factory()->create();

    $this->get(route('tenants.export', $tenant))
        ->assertOk()
        ->assertHeader('content-type', 'application/zip')
        ->assertDownload();

    transferUser('cliente', $tenant);
    $this->get(route('tenants.export', $tenant))->assertForbidden();
});

it('previews the archive with editable client data and imports it', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.test']);
    transferUser('admin', $tenant);
    Site::factory()->count(2)->create();
    $zip = app(TenantArchiveExporter::class)->export($tenant);
    $upload = UploadedFile::fake()->createWithContent('acme.zip', (string) file_get_contents($zip));

    $component = Livewire::test(TenantsImport::class)
        ->set('archive', $upload)
        ->call('toPreview')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertSet('name', 'Acme')
        ->assertSet('slug', 'acme-2') // "acme" is taken: a free slug is proposed
        ->assertSet('domain', 'acme.test')
        ->assertSee('Sedi');

    expect($component->get('counts')['sites'])->toBe(2);

    $component->set('slug', 'acme')->call('runImport')->assertHasErrors(['slug' => 'unique']);

    $component->set('name', 'Acme Bis')->set('slug', 'acme-bis')->call('runImport')
        ->assertHasNoErrors()
        ->assertRedirect();

    $new = Tenant::query()->where('slug', 'acme-bis')->firstOrFail();
    expect($new->name)->toBe('Acme Bis')
        ->and(DB::table('sites')->where('tenant_id', $new->getKey())->count())->toBe(2);
});

it('forbids clienti from the import page', function (): void {
    $tenant = Tenant::factory()->create();
    transferUser('cliente', $tenant);

    Livewire::test(TenantsImport::class)->assertForbidden();
});

it('rejects files that are not tenant archives', function (string $json): void {
    $tenant = Tenant::factory()->create();
    transferUser('admin', $tenant);
    $path = tempnam(sys_get_temp_dir(), 'bad');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('data.json', $json);
    $zip->close();

    expect(fn () => app(TenantArchiveReader::class)->read($path))->toThrow(InvalidTenantArchiveException::class);
})->with([
    'wrong format' => ['{"format":"other","version":1}'],
    'wrong version' => ['{"format":"trama-tenant-export","version":99,"tenant":{"name":"x"}}'],
    'path traversal' => ['{"format":"trama-tenant-export","version":1,"tenant":{"name":"x"},"racks":[{"id":1,"room_id":1,"icon_path":"../../.env.png"}]}'],
]);

it('rejects a non-zip upload in the preview step', function (): void {
    $tenant = Tenant::factory()->create();
    transferUser('admin', $tenant);

    Livewire::test(TenantsImport::class)
        ->set('archive', UploadedFile::fake()->createWithContent('x.zip', 'not a zip'))
        ->call('toPreview')
        ->assertHasErrors('archive')
        ->assertSet('step', 1);
});

it('rolls back everything when the archive has a dangling reference', function (): void {
    $s = buildFullTenant();
    $zip = app(TenantArchiveExporter::class)->export($s['tenant']);

    // Point a connection at an interface that isn't in the archive.
    $za = new ZipArchive;
    $za->open($zip);
    $data = json_decode((string) $za->getFromName('data.json'), true);
    $data['connections'][0]['to_interface_id'] = 999999;
    $za->addFromString('data.json', json_encode($data));
    $za->close();

    $tenantsBefore = Tenant::query()->count();
    $filesBefore = count(Storage::disk('public')->allFiles());

    expect(fn () => app(TenantArchiveImporter::class)->import(
        app(TenantArchiveReader::class)->read($zip),
        $s['user'],
        ['name' => 'Broken', 'slug' => 'broken', 'domain' => null],
    ))->toThrow(InvalidTenantArchiveException::class);

    expect(Tenant::query()->count())->toBe($tenantsBefore)
        ->and(count(Storage::disk('public')->allFiles()))->toBe($filesBefore)
        ->and(TenantContext::id())->toBe($s['tenant']->getKey());
});
