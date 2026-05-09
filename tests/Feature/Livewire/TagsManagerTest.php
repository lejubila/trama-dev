<?php

declare(strict_types=1);

use App\Livewire\Tags\Manager;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

afterEach(function (): void {
    TenantContext::clear();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function setupTagsScene(string $role): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->tenants()->attach($tenant, ['role' => $role]);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
    test()->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    $user->syncRoles([$role]);
    actingAsInTenant($user, $tenant);

    return [$tenant, $user];
}

it('creates a tag', function (): void {
    [$tenant, $user] = setupTagsScene('admin');

    Livewire::test(Manager::class)
        ->set('name', 'critico')
        ->set('color', '#ff0000')
        ->call('save')
        ->assertHasNoErrors();

    expect(Tag::query()->where('name', 'critico')->exists())->toBeTrue();
});

it('validates the color format', function (): void {
    [$tenant, $user] = setupTagsScene('admin');

    Livewire::test(Manager::class)
        ->set('name', 'broken')
        ->set('color', 'red')
        ->call('save')
        ->assertHasErrors(['color']);
});

it('forbids cliente from creating tags', function (): void {
    [$tenant, $user] = setupTagsScene('cliente');

    Livewire::test(Manager::class)
        ->set('name', 'should-fail')
        ->call('save')
        ->assertForbidden();
});

it('deletes a tag as admin', function (): void {
    [$tenant, $user] = setupTagsScene('admin');
    $tag = Tag::factory()->create();

    Livewire::test(Manager::class)
        ->call('delete', $tag->getKey())
        ->assertHasNoErrors();

    expect(Tag::query()->whereKey($tag->getKey())->exists())->toBeFalse();
});
