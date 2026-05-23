<?php

declare(strict_types=1);

use App\Actions\Interfaces\CreateKeystonePair;
use App\Enums\EquipmentType;
use App\Enums\InterfaceSide;
use App\Enums\InterfaceType;
use App\Livewire\Equipment\Show as EquipmentShow;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConnectionService;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;

afterEach(function (): void {
    TenantContext::clear();
});

function bootKeystoneScene(): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->forceFill(['role' => 'admin'])->save();
    $user->tenants()->attach($tenant);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
    actingAsInTenant($user, $tenant);

    return [$tenant, $user];
}

it('CreateKeystonePair builds a front+rear pair on a patch panel', function (): void {
    [$tenant] = bootKeystoneScene();
    $pp = Equipment::factory()->create(['type' => EquipmentType::PatchPanel]);

    [$front, $rear] = app(CreateKeystonePair::class)->execute($pp, [
        'name' => '1',
        'index' => 1,
    ]);

    expect($front->side)->toBe(InterfaceSide::Front);
    expect($rear->side)->toBe(InterfaceSide::Rear);
    expect($front->paired_interface_id)->toBe($rear->getKey());
    expect($rear->paired_interface_id)->toBe($front->getKey());
    expect($front->type)->toBe(InterfaceType::Keystone);
    expect($pp->interfaces()->count())->toBe(2);
});

it('CreateKeystonePair refuses to run on non-patch equipment', function (): void {
    [$tenant] = bootKeystoneScene();
    $sw = Equipment::factory()->create(['type' => EquipmentType::Switch]);

    app(CreateKeystonePair::class)->execute($sw, ['name' => 'bogus']);
})->throws(InvalidArgumentException::class);

it('Equipment\\Show creates a keystone pair when type is patch_panel', function (): void {
    [$tenant] = bootKeystoneScene();
    $pp = Equipment::factory()->create(['type' => EquipmentType::PatchPanel]);

    Livewire::test(EquipmentShow::class, ['equipment' => $pp])
        ->call('openIfCreate')
        ->assertSet('ifType', 'keystone')
        ->set('ifName', '7')
        ->call('saveIf')
        ->assertHasNoErrors();

    $rows = NetworkInterface::query()
        ->where('equipment_id', $pp->getKey())
        ->where('name', '7')
        ->orderBy('side')
        ->get();

    expect($rows)->toHaveCount(2);
    expect($rows->pluck('side')->all())->toEqual([InterfaceSide::Front, InterfaceSide::Rear]);
});

it('a wall_outlet behaves like a patch panel for pair creation', function (): void {
    [$tenant] = bootKeystoneScene();
    $wo = Equipment::factory()->create(['type' => EquipmentType::WallOutlet]);

    Livewire::test(EquipmentShow::class, ['equipment' => $wo])
        ->call('openIfCreate')
        ->assertSet('ifType', 'keystone')
        ->set('ifName', 'jack-A')
        ->call('saveIf')
        ->assertHasNoErrors();

    expect($wo->interfaces()->where('name', 'jack-A')->count())->toBe(2);
});

it('allows two independent connections on the front and rear of the same port', function (): void {
    [$tenant] = bootKeystoneScene();
    $pp = Equipment::factory()->create(['type' => EquipmentType::PatchPanel]);
    $sw = Equipment::factory()->create(['type' => EquipmentType::Switch]);
    $wo = Equipment::factory()->create(['type' => EquipmentType::WallOutlet]);

    [$ppFront, $ppRear] = app(CreateKeystonePair::class)->execute($pp, ['name' => '3']);
    [$woFront, $woRear] = app(CreateKeystonePair::class)->execute($wo, ['name' => '1']);
    $swPort = NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $sw->getKey(),
        'name' => 'Gi0/3',
    ]);

    $service = app(ConnectionService::class);
    $service->connect($swPort, $ppFront, ['cable_type' => 'utp_cat6']);
    $service->connect($ppRear, $woRear, ['cable_type' => 'utp_cat6']);

    expect($ppFront->fresh()->activeConnection())->not->toBeNull();
    expect($ppRear->fresh()->activeConnection())->not->toBeNull();
    expect($ppFront->fresh()->activeConnection()->id)
        ->not->toBe($ppRear->fresh()->activeConnection()->id);
});

it('deleting one half of a keystone pair cascades to the sibling', function (): void {
    [$tenant] = bootKeystoneScene();
    $pp = Equipment::factory()->create(['type' => EquipmentType::PatchPanel]);
    [$front, $rear] = app(CreateKeystonePair::class)->execute($pp, ['name' => '9']);

    $front->delete();

    expect(NetworkInterface::query()->whereKey($rear->getKey())->exists())->toBeFalse();
});

it('renaming the front half mirrors the rename to the rear half', function (): void {
    [$tenant] = bootKeystoneScene();
    $pp = Equipment::factory()->create(['type' => EquipmentType::PatchPanel]);
    [$front, $rear] = app(CreateKeystonePair::class)->execute($pp, ['name' => '5']);

    $front->update(['name' => '5-renamed']);

    expect($rear->fresh()->name)->toBe('5-renamed');
});
