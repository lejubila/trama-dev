<?php

declare(strict_types=1);

use App\Actions\Tenancy\CreateTenant;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

it('creates a tenant and lands the creator inside it', function (): void {
    /** @var User $user */
    $user = User::factory()->admin()->create();

    $tenant = app(CreateTenant::class)->execute($user, [
        'name' => 'New Workspace Inc.',
    ]);

    expect($tenant->slug)->toBe('new-workspace-inc')
        ->and((int) $user->fresh()->current_tenant_id)->toBe($tenant->getKey());
});

it('auto-switches current_tenant_id to the new tenant', function (): void {
    /** @var User $user */
    $user = User::factory()->tecnico()->create();

    $tenant = app(CreateTenant::class)->execute($user, ['name' => 'Auto Switch']);

    expect((int) $user->fresh()->current_tenant_id)->toBe($tenant->getKey());
});

it('lets admin and tecnico update any tenant via policy', function (): void {
    $admin = User::factory()->admin()->create();
    $tecnico = User::factory()->tecnico()->create();
    $tenant = app(CreateTenant::class)->execute($admin, ['name' => 'Editable']);

    expect($admin->fresh()->can('update', $tenant->fresh()))->toBeTrue()
        ->and($tecnico->fresh()->can('update', $tenant->fresh()))->toBeTrue();
});

it('forbids a cliente from updating or deleting a tenant', function (): void {
    $admin = User::factory()->admin()->create();
    $tenant = app(CreateTenant::class)->execute($admin, ['name' => 'Locked']);

    /** @var User $cliente */
    $cliente = User::factory()->cliente()->create();
    $cliente->tenants()->attach($tenant->getKey());

    expect($cliente->fresh()->can('update', $tenant->fresh()))->toBeFalse()
        ->and($cliente->fresh()->can('delete', $tenant->fresh()))->toBeFalse();
});

it('cascades cleanup on tenant delete', function (): void {
    /** @var User $user */
    $user = User::factory()->admin()->create();
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
    $user = User::factory()->cliente()->create();
    $tenant = app(CreateTenant::class)->execute($user, ['name' => 'About to vanish']);
    $user->tenants()->attach($tenant->getKey());

    expect((int) $user->fresh()->current_tenant_id)->toBe($tenant->getKey());

    $tenant->delete();

    expect($user->fresh()->current_tenant_id)->toBeNull();
});
