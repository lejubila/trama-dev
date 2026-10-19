<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Enums\UserRole;
use App\Livewire\Settings\ApiTokens;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Api\TokenAbilities;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

afterEach(function (): void {
    TenantContext::clear();
});

/**
 * JSON-RPC call to the MCP endpoint, as an MCP client would send it.
 *
 * @param  array<string, mixed>  $params
 * @return TestResponse<JsonResponse>
 */
function mcp(string $token, string $method, array $params = []): TestResponse
{
    // The auth guard caches the user between requests of the same test.
    app('auth')->forgetGuards();

    // Inside a running test, test() resolves to the TestCase itself.
    /** @var TestCase $test @phpstan-ignore varTag.nativeType */
    $test = test();

    return $test->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json, text/event-stream',
        'MCP-Protocol-Version' => '2025-06-18',
    ])->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
}

/**
 * Calls a tool and returns [isError, decoded text payload].
 *
 * @param  array<string, mixed>  $arguments
 * @return array{0: bool, 1: mixed}
 */
function mcpTool(string $token, string $tool, array $arguments = []): array
{
    $result = mcp($token, 'tools/call', ['name' => $tool, 'arguments' => $arguments])->assertOk()->json('result');
    $text = (string) ($result['content'][0]['text'] ?? '');

    return [(bool) ($result['isError'] ?? false), json_decode($text, true) ?? $text];
}

/**
 * @return list<string>
 */
function mcpToolNames(string $token): array
{
    return array_column(mcp($token, 'tools/list')->assertOk()->json('result.tools'), 'name');
}

it('requires a token with mcp:access', function (): void {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])->assertUnauthorized();

    ['token' => $noMcp] = tokenWith(TokenAbilities::all());
    mcp($noMcp, 'tools/list')->assertForbidden()->assertJson(['error' => 'Token lacks ability: mcp:access']);

    ['token' => $ok] = tokenWith([TokenAbilities::MCP]);
    mcp($ok, 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1']])
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Trama Network');
});

it('is disabled in demo mode', function (): void {
    ['token' => $token] = tokenWith([TokenAbilities::MCP]);
    config(['demo.enabled' => true]);

    mcp($token, 'tools/list')->assertNotFound();
});

it('lists only the tools the token can use', function (): void {
    ['token' => $token] = tokenWith([TokenAbilities::MCP, 'equipment:read']);

    expect(mcpToolNames($token))->toBe(['search', 'list_equipment', 'get_equipment']);

    ['token' => $all] = tokenWith([TokenAbilities::MCP, ...TokenAbilities::all()]);
    expect(mcpToolNames($all))->toHaveCount(35)->toContain('create_equipment', 'delete_vpn', 'get_topology');
});

it('refuses tools the token cannot see', function (): void {
    ['token' => $token, 'tenant' => $tenant] = tokenWith([TokenAbilities::MCP, 'equipment:read'], [1]);

    mcp($token, 'tools/call', ['name' => 'create_equipment', 'arguments' => ['tenant_id' => $tenant->getKey()]])
        ->assertJsonPath('error.message', 'Tool [create_equipment] not found.');
});

it('resolves the customer from tenant_id or the single allowed one', function (): void {
    $a = Tenant::factory()->create(['name' => 'Alfa']);
    $b = Tenant::factory()->create(['name' => 'Beta']);
    TenantContext::setId($a->getKey());
    Site::factory()->create(['name' => 'Sede Alfa']);
    TenantContext::setId($b->getKey());
    Site::factory()->create(['name' => 'Sede Beta']);
    TenantContext::clear();

    ['token' => $single] = tokenWith([TokenAbilities::MCP, 'sites:read'], [$a->getKey()], $a);
    [$err, $sites] = mcpTool($single, 'list_sites');
    expect($err)->toBeFalse()->and(array_column($sites['data'], 'name'))->toBe(['Sede Alfa']);

    [$err, $msg] = mcpTool($single, 'list_sites', ['tenant_id' => $b->getKey()]);
    expect($err)->toBeTrue()->and($msg)->toContain('not accessible');

    ['token' => $many] = tokenWith([TokenAbilities::MCP, 'sites:read']);
    [$err, $msg] = mcpTool($many, 'list_sites');
    expect($err)->toBeTrue()->and($msg)->toContain('list_tenants');

    [$err, $sites] = mcpTool($many, 'list_sites', ['tenant_id' => $b->getKey()]);
    expect($err)->toBeFalse()->and(array_column($sites['data'], 'name'))->toBe(['Sede Beta']);
});

it('creates a device through the API bridge, with audit', function (): void {
    ['token' => $token, 'tenant' => $tenant] = tokenWith([TokenAbilities::MCP, 'equipment:create', 'equipment:read']);

    [$err, $res] = mcpTool($token, 'create_equipment', [
        'tenant_id' => $tenant->getKey(), 'name' => 'SW-MCP', 'type' => 'switch', 'vendor' => 'Cisco',
    ]);

    expect($err)->toBeFalse()->and($res['data']['name'])->toBe('SW-MCP')->and($res['data'])->not->toHaveKey('created_at');
    $eq = Equipment::withoutGlobalScopes()->where('name', 'SW-MCP')->sole();
    expect($eq->tenant_id)->toBe($tenant->getKey())
        ->and(Audit::query()->where('auditable_type', Equipment::class)->where('auditable_id', $eq->id)->exists())->toBeTrue();
});

it('reports API validation errors to the model', function (): void {
    ['token' => $token, 'tenant' => $tenant] = tokenWith([TokenAbilities::MCP, 'equipment:create']);

    [$err, $msg] = mcpTool($token, 'create_equipment', ['tenant_id' => $tenant->getKey(), 'name' => 'X', 'type' => 'spaceship']);

    expect($err)->toBeTrue()->and($msg)->toMatch('/\b(tipo|type)\b/');
});

it('refuses a remote-access VPN on a non-firewall port', function (): void {
    ['token' => $token, 'tenant' => $tenant] = tokenWith([TokenAbilities::MCP, 'vpn:create']);
    $sw = deviceWithPort($tenant, EquipmentType::Switch, InterfaceMedia::Copper, 'SW');

    [$err, $msg] = mcpTool($token, 'create_vpn_remote_access', [
        'tenant_id' => $tenant->getKey(), 'name' => 'RA', 'protocol' => 'wireguard',
        'firewall_interface_id' => $sw->id, 'routing_mode' => 'routed',
    ]);

    expect($err)->toBeTrue()->and($msg)->toContain('Not an interface of a firewall');
});

it('adds ports and cables them, then shows what each port is connected to', function (): void {
    ['token' => $token, 'tenant' => $tenant] = tokenWith([TokenAbilities::MCP, ...TokenAbilities::all()]);
    $fw = deviceWithPort($tenant, EquipmentType::Firewall, InterfaceMedia::Copper, 'FW-1');
    $t = $tenant->getKey();

    [, $sw] = mcpTool($token, 'create_equipment', ['tenant_id' => $t, 'name' => 'SW-1', 'type' => 'switch']);
    [$err, $port] = mcpTool($token, 'add_interface', ['tenant_id' => $t, 'equipment_id' => $sw['data']['id'], 'name' => 'Gi0/1', 'type' => 'ethernet', 'media' => 'copper']);
    expect($err)->toBeFalse()->and($port['data']['status'])->toBe('up');

    [$err] = mcpTool($token, 'connect_interfaces', ['tenant_id' => $t, 'from_interface_id' => $fw->id, 'to_interface_id' => $port['data']['id'], 'cable_type' => 'cat6']);
    expect($err)->toBeFalse()->and(Connection::withoutGlobalScopes()->count())->toBe(1);

    [, $detail] = mcpTool($token, 'get_equipment', ['tenant_id' => $t, 'equipment_id' => $sw['data']['id']]);
    expect($detail['interfaces'][0]['connected_to'])->toMatchArray(['equipment' => 'FW-1', 'interface' => $fw->name]);
});

it('creates a VM with a vNIC backed by a host NIC and shows the link on both sides', function (): void {
    ['token' => $token, 'tenant' => $tenant] = tokenWith([TokenAbilities::MCP, ...TokenAbilities::all()]);
    $t = $tenant->getKey();

    [, $host] = mcpTool($token, 'create_equipment', ['tenant_id' => $t, 'name' => 'PVE-1', 'type' => 'hypervisor']);
    [, $pnic] = mcpTool($token, 'add_interface', ['tenant_id' => $t, 'equipment_id' => $host['data']['id'], 'name' => 'eno1', 'type' => 'ethernet', 'media' => 'copper']);
    [$err, $vm] = mcpTool($token, 'create_equipment', ['tenant_id' => $t, 'name' => 'VM-1', 'type' => 'virtual_machine', 'host_equipment_id' => $host['data']['id']]);
    expect($err)->toBeFalse()->and($vm['data']['host_equipment_id'])->toBe($host['data']['id']);

    [$err, $vnic] = mcpTool($token, 'add_interface', [
        'tenant_id' => $t, 'equipment_id' => $vm['data']['id'], 'name' => 'net0', 'type' => 'virtual', 'media' => 'virtual',
        'backed_by_interface_id' => $pnic['data']['id'],
    ]);
    expect($err)->toBeFalse()->and($vnic['data']['backed_by_interface_id'])->toBe($pnic['data']['id']);

    [, $vmDetail] = mcpTool($token, 'get_equipment', ['tenant_id' => $t, 'equipment_id' => $vm['data']['id']]);
    expect($vmDetail['interfaces'][0]['backed_by'])->toMatchArray(['interface' => 'eno1', 'equipment' => 'PVE-1']);

    [, $hostDetail] = mcpTool($token, 'get_equipment', ['tenant_id' => $t, 'equipment_id' => $host['data']['id']]);
    expect($hostDetail['interfaces'][0]['carries'][0])->toMatchArray(['interface' => 'net0', 'equipment' => 'VM-1'])
        ->and($hostDetail['virtual_machines'][0]['name'])->toBe('VM-1');

    [$err, $msg] = mcpTool($token, 'update_interface', ['tenant_id' => $t, 'interface_id' => $pnic['data']['id'], 'backed_by_interface_id' => $vnic['data']['id']]);
    expect($err)->toBeTrue()->and($msg)->toMatch('/virtual/i');
});

it('finds an interface by IP with search', function (): void {
    ['token' => $token, 'tenant' => $tenant] = tokenWith([TokenAbilities::MCP, 'equipment:read']);
    $port = deviceWithPort($tenant, EquipmentType::Router, InterfaceMedia::Copper, 'RTR');
    $port->forceFill(['ip_address' => '10.20.30.1/24'])->save();

    [$err, $res] = mcpTool($token, 'search', ['tenant_id' => $tenant->getKey(), 'query' => '10.20.30']);

    expect($err)->toBeFalse()->and($res['interfaces'][0])->toMatchArray(['id' => $port->id, 'equipment' => 'RTR']);
});

it('reports the free units of a rack', function (): void {
    ['token' => $token, 'tenant' => $tenant] = tokenWith([TokenAbilities::MCP, 'racks:read', 'equipment:read']);
    TenantContext::setId($tenant->getKey());
    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->id]);
    $rack = Rack::factory()->create(['room_id' => $room->id, 'height_units' => 6]);
    Equipment::factory()->create(['name' => 'SW', 'rack_id' => $rack->id, 'mounted' => true, 'position_u_start' => 2, 'position_u_height' => 2, 'position_orient' => 'front']);
    TenantContext::clear();

    [$err, $res] = mcpTool($token, 'get_rack', ['tenant_id' => $tenant->getKey(), 'rack_id' => $rack->id]);

    expect($err)->toBeFalse()
        ->and($res['free_units']['front'])->toBe([1, 4, 5, 6])
        ->and($res['free_units']['rear'])->toBe([1, 2, 3, 4, 5, 6])
        ->and($res['devices'][0])->toMatchArray(['name' => 'SW', 'u_start' => 2]);
});

it('summarises the topology', function (): void {
    ['token' => $token, 'tenant' => $tenant] = tokenWith([TokenAbilities::MCP, 'topology:read']);
    $a = deviceWithPort($tenant, EquipmentType::Router, InterfaceMedia::Copper, 'RTR');
    $b = deviceWithPort($tenant, EquipmentType::Switch, InterfaceMedia::Copper, 'SW');
    TenantContext::setId($tenant->getKey());
    Connection::factory()->create(['from_interface_id' => $a->id, 'to_interface_id' => $b->id]);
    TenantContext::clear();

    [$err, $res] = mcpTool($token, 'get_topology', ['tenant_id' => $tenant->getKey()]);

    expect($err)->toBeFalse()
        ->and(array_column($res['devices'], 'name'))->toEqualCanonicalizing(['RTR', 'SW'])
        ->and($res['links'])->toHaveCount(1);
});

it('runs several API calls in one tool without leaking state', function (): void {
    ['token' => $token, 'tenant' => $tenant] = tokenWith([TokenAbilities::MCP, 'vpn:read']);

    [$err, $res] = mcpTool($token, 'list_vpns', ['tenant_id' => $tenant->getKey()]);

    expect($err)->toBeFalse()->and($res)->toBe(['site_to_site' => [], 'remote_access' => []]);
});

it('offers MCP access in the token UI', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    actingAsInTenant($admin, $tenant);

    Livewire::test(ApiTokens::class)
        ->call('openCreate')
        ->assertSee(__('settings.ability_mcp'))
        ->set('name', 'Claude')
        ->set('abilities', [...TokenAbilities::readOnly(), TokenAbilities::MCP])
        ->call('save')
        ->assertHasNoErrors();

    expect($admin->tokens()->sole()->can(TokenAbilities::MCP))->toBeTrue();
});
