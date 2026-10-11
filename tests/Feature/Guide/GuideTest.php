<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Enums\InterfaceType;
use App\Enums\UserRole;
use App\Livewire\Equipment\Index as EquipmentIndex;
use App\Livewire\Equipment\Show as EquipmentShow;
use App\Livewire\Racks\Elevation;
use App\Livewire\Racks\Index as RacksIndex;
use App\Livewire\Sites\Index as SitesIndex;
use App\Livewire\Sites\Show as SitesShow;
use App\Livewire\Tenants\Index as TenantsIndex;
use App\Livewire\Topology\SnapshotSaveModal;
use App\Livewire\Vpn\Index as VpnIndex;
use App\Livewire\WifiNetworks\Index as WifiIndex;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Guide\GuideTours;
use App\Support\Tenancy\TenantContext;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

afterEach(function (): void {
    TenantContext::clear();
});

function guideUser(UserRole $role = UserRole::Admin, ?Tenant $tenant = null): User
{
    $tenant ??= Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    if ($role === UserRole::Cliente) {
        $user->tenants()->attach($tenant);
    }

    return actingAsInTenant($user, $tenant);
}

/**
 * @param  TestResponse<Response>  $response
 * @return array<string, mixed>|null the <x-guide> payload of a page
 */
function guideConfig(TestResponse $response): ?array
{
    if (preg_match('#<script type="application/json" id="trama-guide">(.*?)</script>#s', $response->getContent(), $m) !== 1) {
        return null;
    }

    return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
}

it('shows the guide button and tours to admins and technicians only', function (UserRole $role, bool $visible): void {
    guideUser($role);

    $response = $this->get(route('dashboard'))->assertOk();

    if ($visible) {
        $response->assertSee('data-guide="guide.button"', false);
        expect(guideConfig($response)['tours'])->not->toBeEmpty();
    } else {
        $response->assertDontSee('data-guide="guide.button"', false);
        expect(guideConfig($response))->toBeNull();
    }
})->with([
    'admin' => [UserRole::Admin, true],
    'tecnico' => [UserRole::Tecnico, true],
    'cliente' => [UserRole::Cliente, false],
]);

it('chains the tours in the order of the full path', function (): void {
    $tours = app(GuideTours::class)->forUser(guideUser(UserRole::Tecnico));

    expect(array_keys($tours))->toBe(GuideTours::ORDER)
        ->and($tours['tenant']['next'])->toBe('site')
        ->and($tours['document']['next'])->toBeNull();
});

it('only offers the customer tour when no customer is active', function (): void {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    expect(array_keys(app(GuideTours::class)->forUser($admin)))->toBe(['tenant']);
});

it('translates every tour and step in both languages', function (string $locale): void {
    app()->setLocale($locale);
    $tours = app(GuideTours::class)->forUser(guideUser());

    $texts = [];
    foreach ($tours as $tour) {
        array_push($texts, $tour['title'], $tour['description']);
        foreach ($tour['steps'] as $step) {
            array_push($texts, $step['title'], $step['text']);
        }
    }

    expect(array_filter($texts, fn (string $t) => str_starts_with($t, 'guide.')))->toBe([]);
})->with(['it', 'en']);

it('gives the browser url templates for parametric steps', function (): void {
    $tours = app(GuideTours::class)->forUser(guideUser());

    expect($tours['room']['steps'][1]['url'])->toBe('/sites/{site}')
        ->and($tours['interfaces']['steps'][1]['query'])->toBe(['tab' => 'interfaces'])
        ->and(app(GuideTours::class)->routeTemplates()['racks.show'])->toBe(['/racks/{rack}', 'rack']);
});

it('finds every element a tour step points at', function (): void {
    $tenant = Tenant::factory()->create();
    guideUser(UserRole::Admin, $tenant);
    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey(), 'height_units' => 12]);
    $equipment = Equipment::factory()->create(['type' => EquipmentType::Switch]);

    $page = fn (string $url) => fn (string $target) => $this->get($url)->assertOk()->assertSee('data-guide="'.$target.'"', false);
    $modal = fn (string $component, array $params, string $method, array $args = []) => fn (string $target) => Livewire::test($component, $params)->call($method, ...$args)->assertSeeHtml('data-guide="'.$target.'"');

    $checks = [
        'tenants.new' => $page(route('tenants.index')),
        'tenants.form' => $modal(TenantsIndex::class, [], 'openCreate'),
        'sites.new' => $page(route('sites.index')),
        'sites.list' => $page(route('sites.index')),
        'sites.form' => $modal(SitesIndex::class, [], 'openCreate'),
        'rooms.new' => $page(route('sites.show', $site)),
        'rooms.form' => $modal(SitesShow::class, ['site' => $site], 'openRoomCreate'),
        'racks.new' => $page(route('racks.index')),
        'racks.list' => $page(route('racks.index')),
        'racks.form' => $modal(RacksIndex::class, [], 'openCreate'),
        'rack.elevation' => $page(route('racks.show', $rack)),
        'rack.equipment-form' => $modal(Elevation::class, ['rack' => $rack], 'slotClicked', [1]),
        'equipment.list' => $page(route('equipment.index')),
        'equipment.new' => $page(route('equipment.index')),
        'equipment.form' => $modal(EquipmentIndex::class, [], 'openCreate'),
        'interfaces.new' => $page(route('equipment.show', ['equipment' => $equipment, 'tab' => 'interfaces'])),
        'interfaces.form' => $modal(EquipmentShow::class, ['equipment' => $equipment], 'openIfCreate'),
        'connections.wizard' => $page(route('connections.create')),
        'wifi.new' => $page(route('wifi-networks.index')),
        'wifi.form' => $modal(WifiIndex::class, [], 'openCreate'),
        'vpn.new-remote' => $page(route('vpns.index')),
        'vpn.form' => $modal(VpnIndex::class, [], 'openCreateRemote'),
        'topology.canvas' => $page(route('topology.index')),
        'topology.save-snapshot' => $page(route('topology.index')),
        'snapshot.form' => $modal(SnapshotSaveModal::class, [], 'openModal', [[]]),
        'documents.info' => $page(route('documents.create')),
        'documents.sections' => $page(route('documents.create')),
        'documents.topologies' => $page(route('documents.create')),
        'documents.wifi' => $page(route('documents.create')),
        'documents.vpn' => $page(route('documents.create')),
        'documents.save' => $page(route('documents.create')),
    ];

    $targets = [];
    foreach (app(GuideTours::class)->forUser(auth()->user()) as $tour) {
        foreach ($tour['steps'] as $step) {
            $targets[] = $step['target'];
            if (str_starts_with($step['advance'], 'appear:')) {
                $targets[] = substr($step['advance'], 7);
            }
        }
    }

    // A new target must come with a check here, or a view refactor could
    // silently strand users mid-tour.
    expect(array_diff(array_unique($targets), array_keys($checks)))->toBe([]);

    foreach (array_unique($targets) as $target) {
        $checks[$target]($target);
    }
});

it('reports creations to the guide', function (): void {
    guideUser();

    Livewire::test(SitesIndex::class)
        ->call('openCreate')
        ->set('name', 'HQ')
        ->call('save')
        ->assertDispatched('guide-created', entity: 'site', id: Site::query()->where('name', 'HQ')->value('id'));

    expect(session(GuideTours::LAST_CREATED_KEY))->toMatchArray(['entity' => 'site']);
});

it('remembers a creation followed by a redirect for the next page', function (): void {
    guideUser();

    Livewire::test(TenantsIndex::class)
        ->call('openCreate')
        ->set('name', 'Nuovo cliente')
        ->call('save')
        ->assertRedirect();

    $created = session(GuideTours::LAST_CREATED_KEY);
    expect($created['entity'])->toBe('tenant');

    // Handed to the browser once, then forgotten.
    expect(guideConfig($this->get(route('dashboard')))['lastCreated'])->toBe($created)
        ->and(guideConfig($this->get(route('dashboard')))['lastCreated'])->toBeNull();
});

it('invites to the tour once, until dismissed', function (): void {
    $user = guideUser();

    expect(guideConfig($this->get(route('dashboard')))['offer'])->toBeTrue();

    $this->post(route('guide.offer.dismiss'))->assertNoContent();

    expect($user->fresh()->preferences['guide_offered'] ?? null)->toBeTrue()
        ->and(guideConfig($this->get(route('dashboard')))['offer'])->toBeFalse();
});

it('keeps the invitation per session for the shared demo account', function (): void {
    config(['demo.enabled' => true, 'demo.user.email' => 'demo@trama.test']);
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['email' => 'demo@trama.test', 'role' => UserRole::Admin]);
    actingAsInTenant($user, $tenant);

    $this->post(route('guide.offer.dismiss'))->assertNoContent();

    expect($user->fresh()->preferences['guide_offered'] ?? null)->toBeNull()
        ->and(guideConfig($this->get(route('dashboard')))['offer'])->toBeFalse();
});

it('knows whether the customer can host a Wi-Fi network and a VPN', function (): void {
    guideUser();
    $facts = fn () => app(GuideTours::class)->facts();
    $port = fn (Equipment $eq, InterfaceMedia $media) => NetworkInterface::factory()->create([
        'equipment_id' => $eq->getKey(),
        'type' => $media === InterfaceMedia::Wireless ? InterfaceType::Wireless : InterfaceType::Ethernet,
        'media' => $media,
    ]);

    expect($facts())->toBe(['wifi_ready' => false, 'vpn_ready' => false]);

    // A copper uplink cannot broadcast an SSID.
    $ap = Equipment::factory()->create(['type' => EquipmentType::AccessPoint]);
    $port($ap, InterfaceMedia::Copper);
    expect($facts()['wifi_ready'])->toBeFalse();

    $port($ap, InterfaceMedia::Wireless);
    $port(Equipment::factory()->create(['type' => EquipmentType::Firewall]), InterfaceMedia::Copper);

    expect($facts())->toBe(['wifi_ready' => true, 'vpn_ready' => true]);
});

it('asks for an access point and a firewall only when they are missing', function (): void {
    $tours = app(GuideTours::class)->forUser(guideUser());
    $skips = fn (string $tour) => array_column($tours[$tour]['steps'], 'skipWhen');

    expect($skips('wifi'))->toBe(['wifi_ready', 'wifi_ready', 'wifi_ready', 'wifi_ready', null, null])
        ->and($skips('vpn'))->toBe(['vpn_ready', 'vpn_ready', 'vpn_ready', 'vpn_ready', null, null])
        ->and(guideConfig($this->get(route('dashboard')))['facts'])->toBe(['wifi_ready' => false, 'vpn_ready' => false]);
});

it('puts a wireless port on a wireless medium', function (): void {
    guideUser();
    $ap = Equipment::factory()->create(['type' => EquipmentType::AccessPoint]);

    Livewire::test(EquipmentShow::class, ['equipment' => $ap])
        ->call('openIfCreate')
        ->assertSet('ifMedia', 'copper')
        ->set('ifType', 'wireless')
        ->assertSet('ifMedia', 'wireless');
});

it('serves fresh facts, so a device deleted on the same page counts', function (UserRole $role, int $status): void {
    guideUser($role);
    $fw = Equipment::factory()->create(['type' => EquipmentType::Firewall]);
    NetworkInterface::factory()->create(['equipment_id' => $fw->getKey()]);

    if ($status !== 200) {
        $this->getJson(route('guide.facts'))->assertStatus($status);

        return;
    }

    $this->getJson(route('guide.facts'))->assertOk()->assertJson(['vpn_ready' => true]);
    $fw->delete();
    $this->getJson(route('guide.facts'))->assertOk()->assertJson(['vpn_ready' => false]);
})->with([
    'tecnico' => [UserRole::Tecnico, 200],
    'cliente' => [UserRole::Cliente, 403],
]);
