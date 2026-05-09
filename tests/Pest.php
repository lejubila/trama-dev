<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
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
