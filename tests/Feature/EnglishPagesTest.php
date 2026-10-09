<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Enums\UserRole;
use App\Livewire\Sites\Index;
use App\Models\Connection;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TopologySnapshot;
use App\Models\User;
use App\Models\VpnRemoteAccess;
use App\Models\VpnSiteToSite;
use App\Models\WifiNetwork;
use App\Support\Tenancy\TenantContext;

/**
 * Smoke test for the English localization: every main page, rendered for a
 * user whose preference is "en", must not leak common Italian UI words.
 * Entity names are English on purpose so only UI text can trip the check.
 */
afterEach(function (): void {
    TenantContext::clear();
});

/** Words that only appear in the Italian UI (matched as whole words). */
const ITALIAN_MARKERS = [
    'Nessun', 'Nessuna', 'Nessuno', 'Annulla', 'Salva', 'Modifica', 'Elimina', 'Azioni',
    'Dispositivi', 'Connessioni', 'Interfacce', 'Porte', 'Tutti', 'Tutte', 'Cerca', 'Nuovo', 'Nuova',
    'Descrizione', 'Indietro', 'Seleziona', 'Rimuovi', 'Caricamento', 'Sedi', 'Locali', 'Topologia',
    'Clienti', 'Utenti', 'Etichetta', 'Colore', 'Stato', 'Generale',
];

function englishScene(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    /** @var User $user */
    $user = User::factory()->create(['role' => UserRole::Admin, 'preferences' => ['locale' => 'en']]);
    $user->tenants()->attach($tenant);
    actingAsInTenant($user, $tenant);

    $site = Site::factory()->create(['name' => 'Headquarters', 'address' => 'Main street 1']);
    $room = Room::factory()->create(['site_id' => $site->getKey(), 'name' => 'Server room']);
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'name' => 'R1', 'height_units' => 12]);
    $sw = Equipment::factory()->mountedAt(1, 1)->create(['rack_id' => $rack->getKey(), 'room_id' => $room->getKey(), 'name' => 'Core switch']);
    $fw = Equipment::factory()->create(['name' => 'Edge firewall', 'type' => EquipmentType::Firewall, 'mounted' => false, 'room_id' => $room->getKey()]);
    $a = NetworkInterface::factory()->create(['equipment_id' => $sw->getKey(), 'name' => 'Gi0/1']);
    $b = NetworkInterface::factory()->create(['equipment_id' => $fw->getKey(), 'name' => 'lan']);
    $wan = NetworkInterface::factory()->create(['equipment_id' => $fw->getKey(), 'name' => 'wan']);
    $conn = Connection::create(['tenant_id' => $tenant->getKey(), 'from_interface_id' => $a->getKey(), 'to_interface_id' => $b->getKey(), 'cable_type' => 'utp_cat6']);
    Tag::factory()->create(['name' => 'core']);
    $wifi = WifiNetwork::factory()->create(['ssid' => 'Office']);
    $ra = VpnRemoteAccess::factory()->create(['name' => 'Remote VPN', 'firewall_interface_id' => $wan->getKey()]);
    $s2s = VpnSiteToSite::factory()->create(['name' => 'Branch tunnel', 'endpoint_a_interface_id' => $wan->getKey(), 'endpoint_b_interface_id' => $a->getKey()]);
    $snap = TopologySnapshot::factory()->create(['title' => 'Overview', 'description' => null]);
    $doc = Document::factory()->create(['title' => 'Handbook', 'description' => null]);

    return compact('tenant', 'site', 'room', 'rack', 'sw', 'conn', 'wifi', 'ra', 's2s', 'snap', 'doc');
}

it('renders the main pages in English without Italian UI text', function (): void {
    $s = englishScene();

    $pages = [
        route('dashboard'),
        route('tenants.index'),
        route('tenants.manage', $s['tenant']),
        route('tenants.import'),
        route('sites.index'),
        route('sites.show', $s['site']),
        route('rooms.show', $s['room']),
        route('racks.index'),
        route('racks.show', $s['rack']),
        route('equipment.index'),
        route('equipment.show', $s['sw']),
        route('equipment.show', ['equipment' => $s['sw'], 'tab' => 'interfaces']),
        route('equipment.show', ['equipment' => $s['sw'], 'tab' => 'connections']),
        route('equipment.import'),
        route('connections.index'),
        route('connections.create'),
        route('connections.edit', $s['conn']),
        route('wifi-networks.index'),
        route('wifi-networks.show', $s['wifi']),
        route('vpns.index'),
        route('vpns.remote-access.show', $s['ra']),
        route('vpns.site-to-site.show', $s['s2s']),
        route('topology.index'),
        route('topology.snapshots.index'),
        route('topology.snapshots.show', $s['snap']),
        route('documents.index'),
        route('documents.create'),
        route('documents.edit', $s['doc']),
        route('tags.index'),
        route('icons.index'),
        route('imports.index'),
        route('audit.index'),
        route('users.index'),
        route('settings.api-tokens'),
    ];

    $leaks = [];
    foreach ($pages as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        // Only visible text and attributes matter; drop scripts/styles and Livewire snapshots.
        $html = preg_replace('#<(script|style)\b.*?</\1>#s', ' ', $html);
        $html = preg_replace('#wire:snapshot="[^"]*"#', ' ', $html);
        foreach (ITALIAN_MARKERS as $word) {
            if (preg_match('/(?<![\p{L}\-_])'.$word.'(?![\p{L}\-_])/u', $html) === 1) {
                $leaks[] = "{$word} @ ".parse_url($url, PHP_URL_PATH).(parse_url($url, PHP_URL_QUERY) ? '?'.parse_url($url, PHP_URL_QUERY) : '');
            }
        }
    }

    expect($leaks)->toBe([]);
});

it('shows validation errors in the user language', function (string $locale, string $expected): void {
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['role' => UserRole::Admin, 'preferences' => ['locale' => $locale]]);
    actingAsInTenant($user, $tenant);
    app()->setLocale($locale);

    Livewire\Livewire::test(Index::class)
        ->call('openCreate')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name')
        ->assertSee($expected);
})->with([
    'italian' => ['it', 'Il campo nome è obbligatorio.'],
    'english' => ['en', 'The name field is required.'],
]);
