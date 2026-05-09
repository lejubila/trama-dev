<?php

declare(strict_types=1);

use App\Livewire\Layout\ThemeToggle;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;

afterEach(function (): void {
    TenantContext::clear();
});

it('persists the chosen theme into user preferences', function (): void {
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['preferences' => []]);
    $user->tenants()->attach($tenant, ['role' => 'admin']);
    actingAsInTenant($user, $tenant);

    Livewire::test(ThemeToggle::class)
        ->call('setTheme', 'dark')
        ->assertSet('theme', 'dark')
        ->assertDispatched('apply-theme');

    expect((array) $user->fresh()?->preferences)->toMatchArray(['theme' => 'dark']);
});

it('rejects unknown theme values', function (): void {
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['preferences' => ['theme' => 'light']]);
    $user->tenants()->attach($tenant, ['role' => 'admin']);
    actingAsInTenant($user, $tenant);

    Livewire::test(ThemeToggle::class)
        ->call('setTheme', 'punk-rock')
        ->assertSet('theme', 'light'); // unchanged

    expect((array) $user->fresh()?->preferences)->toMatchArray(['theme' => 'light']);
});
