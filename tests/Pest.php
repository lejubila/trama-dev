<?php

declare(strict_types=1);

use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Enums\InterfaceType;
use App\Enums\UserRole;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Api\TokenAbilities;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Authenticate as $user with $tenant active in both Laravel auth state and the
 * TenantContext, mirroring what SetCurrentTenant does in production requests.
 * The user's capabilities derive from its global `role`.
 */
function actingAsInTenant(User $user, Tenant $tenant): User
{
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();

    test()->actingAs($user);

    TenantContext::setId($tenant->getKey());

    return $user;
}

/**
 * Bootstrap an API user with the given global role and a Sanctum token.
 * Clienti are assigned to the tenant; admins/tecnici access any tenant.
 *
 * @return array{tenant: Tenant, user: User, token: string}
 */
function apiUser(string $role = 'admin'): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['role' => UserRole::from($role)]);

    if ($role === UserRole::Cliente->value) {
        $user->tenants()->attach($tenant);
    }

    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();

    $token = $user->createToken('api-test', TokenAbilities::grantable())->plainTextToken;

    TenantContext::clear();

    return ['tenant' => $tenant, 'user' => $user, 'token' => $token];
}

/**
 * Standard `Authorization: Bearer …` + `X-Tenant-Id` headers expected by
 * every protected API route.
 *
 * @return array<string, string>
 */
function apiHeaders(string $token, int $tenantId): array
{
    return [
        'Authorization' => "Bearer {$token}",
        'X-Tenant-Id' => (string) $tenantId,
        'Accept' => 'application/json',
    ];
}

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

/**
 * Equipment of the given type with one interface of the given media.
 */
function deviceWithPort(Tenant $tenant, EquipmentType $type, InterfaceMedia $media, string $name = 'DEV'): NetworkInterface
{
    TenantContext::setId($tenant->getKey());
    $eq = Equipment::factory()->create(['type' => $type, 'name' => $name]);
    $iface = NetworkInterface::factory()->create([
        'equipment_id' => $eq->getKey(),
        'name' => $media->value.'0',
        'media' => $media,
        'type' => $media === InterfaceMedia::Wireless ? InterfaceType::Wireless : InterfaceType::Ethernet,
    ]);
    TenantContext::clear();

    return $iface;
}
