<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TestNote;

afterEach(function (): void {
    TenantContext::clear();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function makeUserForTenant(Tenant $tenant, string $role): User
{
    /** @var User $user */
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $user->tenants()->attach($tenant, ['role' => $role]);

    return $user;
}

/**
 * spatie roles are per-tenant when teams=true. The RolePermissionSeeder loops
 * over existing tenants, so it must be run AFTER the test's tenant is created.
 */
function seedRolesForTenant(Tenant $tenant): void
{
    test()->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
}

it('isolates data between tenants', function (): void {
    $acme = Tenant::factory()->create(['name' => 'ACME', 'slug' => 'acme-iso']);
    $beta = Tenant::factory()->create(['name' => 'Beta', 'slug' => 'beta-iso']);

    // Seed one note in each tenant
    TenantContext::setId($acme->getKey());
    TestNote::create(['body' => 'note in ACME']);

    TenantContext::setId($beta->getKey());
    TestNote::create(['body' => 'note in Beta']);

    // Verify ACME context only sees ACME notes
    TenantContext::setId($acme->getKey());
    $acmeNotes = TestNote::all();
    expect($acmeNotes)->toHaveCount(1)
        ->and($acmeNotes->first()->body)->toBe('note in ACME');

    // Verify Beta context only sees Beta notes
    TenantContext::setId($beta->getKey());
    $betaNotes = TestNote::all();
    expect($betaNotes)->toHaveCount(1)
        ->and($betaNotes->first()->body)->toBe('note in Beta');
});

it('auto-fills tenant_id on model create when context is set', function (): void {
    $acme = Tenant::factory()->create(['slug' => 'acme-autofill']);

    TenantContext::setId($acme->getKey());

    /** @var TestNote $note */
    $note = TestNote::create(['body' => 'auto-tenant']);

    expect($note->tenant_id)->toBe($acme->getKey());
});

it('blocks tenant switch if user does not belong', function (): void {
    $home = Tenant::factory()->create(['slug' => 'home-tenant']);
    $stranger = Tenant::factory()->create(['slug' => 'stranger-tenant']);

    seedRolesForTenant($home);
    $user = makeUserForTenant($home, 'tecnico');
    $user->syncRoles(['tecnico']);

    $this->actingAs($user)
        ->post(route('tenant.switch', $stranger))
        ->assertForbidden();

    expect($user->fresh()->current_tenant_id)
        ->not->toBe($stranger->getKey());
});

it('updates current_tenant_id on a valid switch', function (): void {
    $first = Tenant::factory()->create(['slug' => 'first']);
    $second = Tenant::factory()->create(['slug' => 'second']);
    seedRolesForTenant($first);

    $user = User::factory()->create();
    $user->tenants()->attach([
        $first->getKey() => ['role' => 'tecnico'],
        $second->getKey() => ['role' => 'tecnico'],
    ]);
    $user->forceFill(['current_tenant_id' => $first->getKey()])->save();

    $this->actingAs($user)
        ->post(route('tenant.switch', $second))
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->current_tenant_id)->toBe($second->getKey());
});

it('grants admin role the manage_users permission', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'admin-can']);
    seedRolesForTenant($tenant);
    $admin = makeUserForTenant($tenant, 'admin');
    $admin->syncRoles(['admin']);

    actingAsInTenant($admin, $tenant);

    expect($admin->can('manage_users'))->toBeTrue()
        ->and($admin->can('manage_equipment'))->toBeTrue();
});

it('denies cliente role the manage_equipment permission', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'cliente-cannot']);
    seedRolesForTenant($tenant);
    $cliente = makeUserForTenant($tenant, 'cliente');
    $cliente->syncRoles(['cliente']);

    actingAsInTenant($cliente, $tenant);

    expect($cliente->can('manage_equipment'))->toBeFalse()
        ->and($cliente->can('manage_users'))->toBeFalse()
        ->and($cliente->can('view_only'))->toBeTrue();
});
