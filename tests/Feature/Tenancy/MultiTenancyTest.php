<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Tests\Support\TestNote;

afterEach(function (): void {
    TenantContext::clear();
});

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

it('blocks a cliente from switching to a tenant they are not assigned to', function (): void {
    $home = Tenant::factory()->create(['slug' => 'home-tenant']);
    $stranger = Tenant::factory()->create(['slug' => 'stranger-tenant']);

    $user = User::factory()->cliente()->create();
    $user->tenants()->attach($home);

    $this->actingAs($user)
        ->post(route('tenant.switch', $stranger))
        ->assertForbidden();

    expect($user->fresh()->current_tenant_id)
        ->not->toBe($stranger->getKey());
});

it('lets a cliente switch to an assigned tenant', function (): void {
    $first = Tenant::factory()->create(['slug' => 'first']);
    $second = Tenant::factory()->create(['slug' => 'second']);

    $user = User::factory()->cliente()->create();
    $user->tenants()->attach([$first->getKey(), $second->getKey()]);
    $user->forceFill(['current_tenant_id' => $first->getKey()])->save();

    $this->actingAs($user)
        ->post(route('tenant.switch', $second))
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->current_tenant_id)->toBe($second->getKey());
});

it('lets admin and tecnico access any tenant without assignment', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'unassigned']);
    $admin = User::factory()->admin()->create();
    $tecnico = User::factory()->tecnico()->create();

    expect($admin->canAccessTenant($tenant))->toBeTrue()
        ->and($tecnico->canAccessTenant($tenant))->toBeTrue();

    $this->actingAs($tecnico)
        ->post(route('tenant.switch', $tenant))
        ->assertRedirect(route('dashboard'));
});

it('reflects data-management capability per global role', function (): void {
    expect(User::factory()->admin()->create()->canManageData())->toBeTrue()
        ->and(User::factory()->tecnico()->create()->canManageData())->toBeTrue()
        ->and(User::factory()->cliente()->create()->canManageData())->toBeFalse();
});
