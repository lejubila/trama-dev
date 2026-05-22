<?php

declare(strict_types=1);

use App\Livewire\Connections\Edit;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;

afterEach(function (): void {
    TenantContext::clear();
});

function setupEditScene(string $role): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->forceFill(['role' => $role])->save();
    $user->tenants()->attach($tenant);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
    actingAsInTenant($user, $tenant);

    $eq1 = Equipment::factory()->create();
    $eq2 = Equipment::factory()->create();
    $a = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eq1->getKey()]);
    $b = NetworkInterface::factory()->ethernet()->create(['equipment_id' => $eq2->getKey()]);

    $conn = Connection::create([
        'tenant_id' => $tenant->getKey(),
        'from_interface_id' => $a->getKey(),
        'to_interface_id' => $b->getKey(),
        'cable_type' => 'utp_cat6',
        'color' => '#2563EB',
        'status' => 'active',
    ]);

    return [$tenant, $user, $conn];
}

it('updates an existing connection with new color and label', function (): void {
    [$tenant, $user, $conn] = setupEditScene('admin');

    Livewire::test(Edit::class, ['connection' => $conn])
        ->assertSet('color', '#2563EB')
        ->set('color', '#DC2626')
        ->set('cableLabel', 'rack-A → rack-B')
        ->call('save')
        ->assertHasNoErrors();

    $conn->refresh();
    expect($conn->color)->toBe('#DC2626');
    expect($conn->cable_label)->toBe('rack-A → rack-B');
});

it('rejects a color that is not a valid hex code', function (): void {
    [$tenant, $user, $conn] = setupEditScene('admin');

    Livewire::test(Edit::class, ['connection' => $conn])
        ->set('color', 'not-a-hex')
        ->call('save')
        ->assertHasErrors(['color']);

    expect($conn->fresh()->color)->toBe('#2563EB');
});

it('accepts an empty color to clear the field', function (): void {
    [$tenant, $user, $conn] = setupEditScene('admin');

    Livewire::test(Edit::class, ['connection' => $conn])
        ->set('color', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($conn->fresh()->color)->toBeNull();
});

it('forbids cliente from editing connections', function (): void {
    [$tenant, $user, $conn] = setupEditScene('cliente');

    Livewire::test(Edit::class, ['connection' => $conn])->assertForbidden();
});

it('syncs tags on a connection via the edit form', function (): void {
    [$tenant, $user, $conn] = setupEditScene('admin');
    $tag = Tag::create(['name' => 'backbone', 'color' => '#00ff00']);

    Livewire::test(Edit::class, ['connection' => $conn])
        ->assertSet('selectedTagIds', [])
        ->set('selectedTagIds', [$tag->getKey()])
        ->call('save')
        ->assertHasNoErrors();

    expect($conn->fresh()->tags->pluck('id')->all())->toBe([$tag->getKey()]);
});
