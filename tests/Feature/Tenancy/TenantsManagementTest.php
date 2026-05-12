<?php

declare(strict_types=1);

use App\Actions\Tenancy\CreateTenant;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

afterEach(function (): void {
    TenantContext::clear();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('creates a tenant and attaches the creator as admin', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $tenant = app(CreateTenant::class)->execute($user, [
        'name' => 'New Workspace Inc.',
    ]);

    expect($tenant->slug)->toBe('new-workspace-inc')
        ->and($user->fresh()->belongsToTenant($tenant))->toBeTrue()
        ->and($user->fresh()->roleInTenant($tenant))->toBe('admin');
});

it('bootstraps the three spatie roles inside the new tenant', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $tenant = app(CreateTenant::class)->execute($user, ['name' => 'Roles Test']);

    $roleNames = Role::query()
        ->where('tenant_id', $tenant->getKey())
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($roleNames)->toBe(['admin', 'cliente', 'tecnico']);
});

it('auto-switches current_tenant_id to the new tenant', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $tenant = app(CreateTenant::class)->execute($user, ['name' => 'Auto Switch']);

    expect((int) $user->fresh()->current_tenant_id)->toBe($tenant->getKey());
});

it('lets a tenant admin update the tenant via policy', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $tenant = app(CreateTenant::class)->execute($user, ['name' => 'Editable']);

    expect($user->fresh()->can('update', $tenant->fresh()))->toBeTrue();
});

it('forbids non-admin members from updating someone else\'s tenant', function (): void {
    /** @var User $admin */
    $admin = User::factory()->create();
    $tenant = app(CreateTenant::class)->execute($admin, ['name' => 'Locked']);

    /** @var User $tecnico */
    $tecnico = User::factory()->create();
    $tecnico->tenants()->attach($tenant->getKey(), ['role' => 'tecnico']);

    expect($tecnico->fresh()->can('update', $tenant->fresh()))->toBeFalse()
        ->and($tecnico->fresh()->can('delete', $tenant->fresh()))->toBeFalse();
});

it('cascades cleanup on tenant delete', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $tenant = app(CreateTenant::class)->execute($user, ['name' => 'Doomed']);

    TenantContext::setId($tenant->getKey());
    $site = Site::factory()->create();
    $room = Room::factory()->create(['site_id' => $site->getKey()]);
    $rack = Rack::factory()->create(['room_id' => $room->getKey()]);
    $eq = Equipment::factory()->create(['rack_id' => $rack->getKey()]);

    TenantContext::clear();
    $tenant->delete();

    expect(Site::query()->whereKey($site->getKey())->exists())->toBeFalse()
        ->and(Room::query()->whereKey($room->getKey())->exists())->toBeFalse()
        ->and(Rack::query()->whereKey($rack->getKey())->exists())->toBeFalse()
        ->and(Equipment::query()->whereKey($eq->getKey())->exists())->toBeFalse();
});

it('resets current_tenant_id of members when the tenant is deleted', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    $tenant = app(CreateTenant::class)->execute($user, ['name' => 'About to vanish']);

    expect((int) $user->fresh()->current_tenant_id)->toBe($tenant->getKey());

    $tenant->delete();

    expect($user->fresh()->current_tenant_id)->toBeNull();
});
