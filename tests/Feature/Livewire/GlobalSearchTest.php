<?php

declare(strict_types=1);

use App\Livewire\Layout\GlobalSearch;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;

afterEach(function (): void {
    TenantContext::clear();
});

function bootSearchScene(): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->forceFill(['role' => 'admin'])->save();
    $user->tenants()->attach($tenant);
    actingAsInTenant($user, $tenant);

    return [$tenant, $user];
}

it('finds equipment by name', function (): void {
    [$tenant] = bootSearchScene();
    Equipment::factory()->create(['name' => 'CORE-SW1']);
    Equipment::factory()->create(['name' => 'AP-X']);

    Livewire::test(GlobalSearch::class)
        ->set('query', 'CORE')
        ->assertSet('open', true)
        ->assertSee('CORE-SW1')
        ->assertDontSee('AP-X');
});

it('finds equipment by serial', function (): void {
    [$tenant] = bootSearchScene();
    Equipment::factory()->create(['name' => 'X', 'serial' => 'SN-LOOK-ME-UP']);

    Livewire::test(GlobalSearch::class)
        ->set('query', 'LOOK-ME')
        ->assertSee('X');
});

it('groups results: interfaces by name match show under Interfaces', function (): void {
    [$tenant] = bootSearchScene();
    $eq = Equipment::factory()->create(['name' => 'OWNER']);
    NetworkInterface::factory()->ethernet()->create([
        'equipment_id' => $eq->getKey(),
        'name' => 'Gi0/UNIQUE',
    ]);

    Livewire::test(GlobalSearch::class)
        ->set('query', 'UNIQUE')
        ->assertSee('Interfaces')
        ->assertSee('Gi0/UNIQUE');
});

it('isolates results to the current tenant', function (): void {
    [$tenantA] = bootSearchScene();
    Site::factory()->create(['name' => 'CURRENT-SITE']);

    // Other-tenant Site should NOT show up
    $tenantB = Tenant::factory()->create();
    TenantContext::setId($tenantB->getKey());
    Site::factory()->create(['name' => 'OTHER-SITE']);
    TenantContext::setId($tenantA->getKey());

    Livewire::test(GlobalSearch::class)
        ->set('query', 'SITE')
        ->assertSee('CURRENT-SITE')
        ->assertDontSee('OTHER-SITE');
});

it('does not query under the min-chars threshold', function (): void {
    [$tenant] = bootSearchScene();
    Equipment::factory()->create(['name' => 'CORE-SW1']);

    Livewire::test(GlobalSearch::class)
        ->set('query', 'C') // 1 char, below MIN_CHARS=2
        ->assertSet('open', false);
});
