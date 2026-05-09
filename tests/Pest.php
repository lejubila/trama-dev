<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
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
 * Authenticate as $user with $tenant active in both Laravel auth state
 * and the TenantContext + spatie permission team scope, mirroring what
 * SetCurrentTenant middleware does in production requests.
 */
function actingAsInTenant(User $user, Tenant $tenant): User
{
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();

    test()->actingAs($user);

    TenantContext::setId($tenant->getKey());
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

    return $user;
}

function something(): void
{
    // ..
}

/**
 * Bootstrap an API user with a Sanctum token. Returns the tenant, user, and
 * a Bearer-ready plain-text token. Intentionally separate from
 * `actingAsInTenant` because API tests rely on token auth, not session.
 *
 * @return array{tenant: Tenant, user: User, token: string}
 */
function apiUser(string $role = 'admin'): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->tenants()->attach($tenant, ['role' => $role]);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();

    test()->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    $user->syncRoles([$role]);

    $token = $user->createToken('api-test', ['read', 'write'])->plainTextToken;

    TenantContext::clear();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);

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
