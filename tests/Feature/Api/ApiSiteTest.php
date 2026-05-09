<?php

declare(strict_types=1);

use App\Models\Site;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Spatie\Permission\PermissionRegistrar;

afterEach(function (): void {
    TenantContext::clear();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('lists sites of the active tenant only', function (): void {
    $u = apiUser('admin');

    TenantContext::setId($u['tenant']->getKey());
    Site::factory()->create(['name' => 'A1']);
    Site::factory()->create(['name' => 'A2']);

    // Site in a different tenant — must NOT appear
    $other = Tenant::factory()->create();
    TenantContext::setId($other->getKey());
    Site::factory()->create(['name' => 'OTHER']);

    $r = $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->getJson('/api/v1/sites')
        ->assertOk();

    $names = collect($r->json('data'))->pluck('attributes.name')->all();
    expect($names)->toContain('A1', 'A2')
        ->and($names)->not->toContain('OTHER');
});

it('shows a single site', function (): void {
    $u = apiUser('admin');
    TenantContext::setId($u['tenant']->getKey());
    $s = Site::factory()->create(['name' => 'SHOW-ME']);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->getJson("/api/v1/sites/{$s->id}")
        ->assertOk()
        ->assertJsonPath('data.attributes.name', 'SHOW-ME');
});

it('creates a site as admin', function (): void {
    $u = apiUser('admin');

    $r = $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson('/api/v1/sites', ['name' => 'NEW-SITE', 'address' => 'Via Test 1'])
        ->assertCreated();

    expect($r->json('data.attributes.name'))->toBe('NEW-SITE');
    expect(Site::query()->where('name', 'NEW-SITE')->exists())->toBeTrue();
});

it('rejects a site create with invalid payload', function (): void {
    $u = apiUser('admin');

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson('/api/v1/sites', ['address' => 'no name'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('updates a site as admin', function (): void {
    $u = apiUser('admin');
    TenantContext::setId($u['tenant']->getKey());
    $s = Site::factory()->create(['name' => 'OLD']);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->patchJson("/api/v1/sites/{$s->id}", ['name' => 'NEW'])
        ->assertOk()
        ->assertJsonPath('data.attributes.name', 'NEW');
});

it('deletes a site as admin', function (): void {
    $u = apiUser('admin');
    TenantContext::setId($u['tenant']->getKey());
    $s = Site::factory()->create();

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->deleteJson("/api/v1/sites/{$s->id}")
        ->assertNoContent();

    expect(Site::query()->whereKey($s->getKey())->exists())->toBeFalse();
});

it('forbids cliente from creating a site', function (): void {
    $u = apiUser('cliente');

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson('/api/v1/sites', ['name' => 'no-go'])
        ->assertForbidden();
});
