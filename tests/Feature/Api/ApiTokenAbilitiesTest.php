<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Settings\ApiTokens;
use App\Models\Equipment;
use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Api\TokenAbilities;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Admin + tenant + a token with exactly the given abilities.
 *
 * @param  list<string>  $abilities
 * @param  list<int>|null  $tenantIds
 * @return array{user: User, tenant: Tenant, token: string}
 */
function tokenWith(array $abilities, ?array $tenantIds = null, ?Tenant $tenant = null): array
{
    $tenant ??= Tenant::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $new = $user->createToken('scoped', $abilities);
    $new->accessToken->forceFill(['tenant_ids' => $tenantIds])->save();

    return ['user' => $user, 'tenant' => $tenant, 'token' => $new->plainTextToken];
}

function equipmentIn(Tenant $tenant): Equipment
{
    TenantContext::setId($tenant->getKey());
    $eq = Equipment::factory()->create();
    TenantContext::clear();

    return $eq;
}

it('maps API route names to abilities', function (string $route, ?string $ability): void {
    expect(TokenAbilities::forRoute($route))->toBe($ability);
})->with([
    ['api.v1.equipment.index', 'equipment:read'],
    ['api.v1.equipment.show', 'equipment:read'],
    ['api.v1.equipment.store', 'equipment:create'],
    ['api.v1.equipment.update', 'equipment:update'],
    ['api.v1.equipment.destroy', 'equipment:delete'],
    ['api.v1.sites.rooms.store', 'rooms:create'],
    ['api.v1.racks.destroy', 'racks:delete'],
    ['api.v1.interfaces.index', 'interfaces:read'],
    ['api.v1.topology', 'topology:read'],
    ['api.v1.tenants.index', 'tenants:read'],
    ['api.v1.unknown.index', null],
    ['dashboard', null],
]);

it('lets a read-only equipment token read but not write', function (): void {
    ['tenant' => $tenant, 'token' => $token] = tokenWith(['equipment:read']);
    $eq = equipmentIn($tenant);
    $h = apiHeaders($token, $tenant->getKey());

    $this->withHeaders($h)->getJson('/api/v1/equipment')->assertOk();
    $this->withHeaders($h)->getJson("/api/v1/equipment/{$eq->id}")->assertOk();
    $this->withHeaders($h)->postJson('/api/v1/equipment', ['name' => 'X', 'type' => 'switch'])
        ->assertForbidden()->assertJson(['error' => 'Token lacks ability: equipment:create']);
    $this->withHeaders($h)->patchJson("/api/v1/equipment/{$eq->id}", ['name' => 'Y'])->assertForbidden();
    $this->withHeaders($h)->deleteJson("/api/v1/equipment/{$eq->id}")->assertForbidden();
    $this->withHeaders($h)->getJson('/api/v1/sites')->assertForbidden();
    $this->withHeaders($h)->getJson('/api/v1/topology')->assertForbidden();

    expect(Equipment::withoutGlobalScopes()->whereKey($eq->id)->exists())->toBeTrue();
});

it('lets a create token create but not delete', function (): void {
    ['tenant' => $tenant, 'token' => $token] = tokenWith(['equipment:read', 'equipment:create']);
    $h = apiHeaders($token, $tenant->getKey());

    $id = $this->withHeaders($h)->postJson('/api/v1/equipment', ['name' => 'SW-API', 'type' => 'switch'])
        ->assertCreated()->json('data.id');

    $this->withHeaders($h)->deleteJson("/api/v1/equipment/{$id}")->assertForbidden();
});

it('covers the topology with topology:read', function (): void {
    ['tenant' => $tenant, 'token' => $token] = tokenWith(['topology:read']);

    $this->withHeaders(apiHeaders($token, $tenant->getKey()))->getJson('/api/v1/topology')->assertOk();
});

it('restricts a token to its allowed tenants', function (): void {
    $a = Tenant::factory()->create(['name' => 'Alfa']);
    $b = Tenant::factory()->create(['name' => 'Beta']);
    ['token' => $token] = tokenWith(TokenAbilities::readOnly(), [$a->getKey()], $a);

    $this->withHeaders(apiHeaders($token, $a->getKey()))->getJson('/api/v1/sites')->assertOk();
    $this->withHeaders(apiHeaders($token, $b->getKey()))->getJson('/api/v1/sites')
        ->assertForbidden()->assertJson(['error' => 'Forbidden tenant']);

    $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
        ->getJson('/api/v1/tenants')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attributes.name', 'Alfa');
});

it('lists every tenant to an unrestricted token and needs tenants:read', function (): void {
    Tenant::factory()->count(2)->create();
    ['token' => $all] = tokenWith(['tenants:read']);
    ['token' => $none] = tokenWith(['sites:read']);
    $h = fn (string $t): array => ['Authorization' => "Bearer {$t}", 'Accept' => 'application/json'];

    $this->withHeaders($h($all))->getJson('/api/v1/tenants')->assertOk()
        ->assertJsonCount(Tenant::query()->count(), 'data');
    // The guard caches the resolved user between requests of the same test.
    $this->app['auth']->forgetGuards();
    $this->withHeaders($h($none))->getJson('/api/v1/tenants')->assertForbidden();
});

it('lists only assigned tenants to a cliente token', function (): void {
    $mine = Tenant::factory()->create();
    Tenant::factory()->create();
    $cliente = User::factory()->create(['role' => UserRole::Cliente]);
    $cliente->tenants()->attach($mine);
    $token = $cliente->createToken('c', ['tenants:read'])->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
        ->getJson('/api/v1/tenants')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->getKey());
});

it('rejects expired tokens', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $token = $user->createToken('old', TokenAbilities::all(), now()->subDay())->plainTextToken;

    $this->withHeaders(apiHeaders($token, $tenant->getKey()))->getJson('/api/v1/sites')->assertUnauthorized();
});

it('forbids token management via API to non-admins', function (string $role): void {
    $user = User::factory()->create(['role' => UserRole::from($role)]);
    $token = $user->createToken('x', TokenAbilities::grantable())->plainTextToken;
    $h = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

    $this->withHeaders($h)->getJson('/api/v1/tokens')->assertForbidden();
    $this->withHeaders($h)->postJson('/api/v1/tokens', ['name' => 'n', 'abilities' => ['sites:read']])->assertForbidden();
})->with(['tecnico', 'cliente']);

it('requires tokens:manage on the calling token', function (): void {
    ['token' => $token] = tokenWith(TokenAbilities::all());

    $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
        ->postJson('/api/v1/tokens', ['name' => 'n', 'abilities' => ['sites:read']])
        ->assertForbidden();
});

it('cannot mint a token broader than itself', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    ['token' => $token] = tokenWith(['sites:read', TokenAbilities::MANAGE_TOKENS], [$a->getKey()], $a);
    $h = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

    $this->withHeaders($h)->postJson('/api/v1/tokens', ['name' => 'n', 'abilities' => ['sites:delete'], 'tenant_ids' => [$a->getKey()]])
        ->assertUnprocessable()->assertJsonValidationErrors('abilities');
    $this->withHeaders($h)->postJson('/api/v1/tokens', ['name' => 'n', 'abilities' => ['sites:read']])
        ->assertUnprocessable()->assertJsonValidationErrors('tenant_ids');
    $this->withHeaders($h)->postJson('/api/v1/tokens', ['name' => 'n', 'abilities' => ['sites:read'], 'tenant_ids' => [$b->getKey()]])
        ->assertUnprocessable()->assertJsonValidationErrors('tenant_ids');

    $this->withHeaders($h)->postJson('/api/v1/tokens', ['name' => 'ok', 'abilities' => ['sites:read'], 'tenant_ids' => [$a->getKey()]])
        ->assertCreated()
        ->assertJsonPath('data.tenant_ids', [$a->getKey()]);
});

it('rejects unknown abilities', function (): void {
    $u = apiUser('admin');

    $this->withHeaders(['Authorization' => "Bearer {$u['token']}", 'Accept' => 'application/json'])
        ->postJson('/api/v1/tokens', ['name' => 'n', 'abilities' => ['write']])
        ->assertUnprocessable();
});

it('limits the token page to admins', function (string $role, bool $allowed): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => UserRole::from($role)]);
    $user->tenants()->attach($tenant);
    actingAsInTenant($user, $tenant);

    $page = $this->get(route('settings.api-tokens'));
    $allowed ? $page->assertOk() : $page->assertForbidden();

    $this->get(route('dashboard'))->{$allowed ? 'assertSee' : 'assertDontSee'}(route('settings.api-tokens'));
})->with([
    ['admin', true],
    ['tecnico', false],
    ['cliente', false],
]);

it('creates a token with the matrix, tenants and expiry from the UI', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    actingAsInTenant($admin, $tenant);

    $c = Livewire::test(ApiTokens::class)
        ->call('openCreate')
        ->assertSet('abilities', TokenAbilities::readOnly())
        ->call('preset', 'none')
        ->set('name', 'AI assistant')
        // Ticking create alone also grants read on the same resource.
        ->set('abilities', ['equipment:create'])
        ->assertSet('abilities', ['equipment:read', 'equipment:create'])
        ->set('tenantScope', 'selected')
        ->set('tenantIds', [$tenant->getKey()])
        ->set('expiresAt', now()->addMonth()->format('Y-m-d'))
        ->call('save')
        ->assertHasNoErrors();

    expect($c->get('newPlainToken'))->toBeString();

    /** @var PersonalAccessToken $token */
    $token = $admin->tokens()->sole();
    expect($token->abilities)->toBe(['equipment:read', 'equipment:create'])
        ->and($token->tenant_ids)->toBe([$tenant->getKey()])
        ->and($token->expires_at?->isFuture())->toBeTrue();
});

it('drops write actions when read is unticked in the UI', function (): void {
    $tenant = Tenant::factory()->create();
    actingAsInTenant(User::factory()->create(['role' => UserRole::Admin]), $tenant);

    Livewire::test(ApiTokens::class)
        ->call('openCreate')
        ->set('abilities', ['sites:read', 'sites:create', 'racks:read'])
        ->set('abilities', ['sites:create', 'racks:read'])
        ->assertSet('abilities', ['racks:read']);
});

it('validates the UI form', function (): void {
    $tenant = Tenant::factory()->create();
    actingAsInTenant(User::factory()->create(['role' => UserRole::Admin]), $tenant);

    Livewire::test(ApiTokens::class)
        ->call('openCreate')
        ->set('name', 'x')
        ->call('preset', 'none')
        ->set('tenantScope', 'selected')
        ->call('save')
        ->assertHasErrors(['abilities', 'tenantIds']);
});

it('edits the permissions of an existing token', function (): void {
    $a = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    actingAsInTenant($admin, $a);
    $id = $admin->createToken('t', TokenAbilities::readOnly())->accessToken->id;

    Livewire::test(ApiTokens::class)
        ->call('openEdit', $id)
        ->assertSet('name', 't')
        ->assertSet('tenantScope', 'all')
        ->call('preset', 'all')
        ->set('tenantScope', 'selected')
        ->set('tenantIds', [$a->getKey()])
        ->call('save')
        ->assertHasNoErrors();

    $token = PersonalAccessToken::query()->findOrFail($id);
    expect($token->abilities)->toBe(TokenAbilities::all())
        ->and($token->tenant_ids)->toBe([$a->getKey()]);
});

it('cannot edit or revoke another user\'s token from the UI', function (): void {
    $tenant = Tenant::factory()->create();
    actingAsInTenant(User::factory()->create(['role' => UserRole::Admin]), $tenant);
    $other = User::factory()->create(['role' => UserRole::Admin])->createToken('x', ['sites:read'])->accessToken->id;

    Livewire::test(ApiTokens::class)->call('openEdit', $other)->assertNotFound();
    Livewire::test(ApiTokens::class)->call('revoke', $other)->assertNotFound();
    expect(PersonalAccessToken::query()->whereKey($other)->exists())->toBeTrue();
});

it('migrates legacy tokens: revokes non-admin ones and converts admin ones', function (): void {
    $migration = require database_path('migrations/2026_10_19_100000_add_tenant_ids_to_personal_access_tokens_table.php');
    $migration->down();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $tecnico = User::factory()->create(['role' => UserRole::Tecnico]);
    $read = $admin->createToken('r', ['read'])->accessToken->id;
    $write = $admin->createToken('w', ['read', 'write'])->accessToken->id;
    $tec = $tecnico->createToken('t', ['read', 'write'])->accessToken->id;

    $migration->up();

    $abilities = fn (int $id): ?array => json_decode((string) DB::table('personal_access_tokens')->where('id', $id)->value('abilities'), true);
    expect(DB::table('personal_access_tokens')->where('id', $tec)->exists())->toBeFalse()
        ->and($abilities($read))->toBe(TokenAbilities::readOnly())
        ->and($abilities($write))->toBe(TokenAbilities::grantable());
});
