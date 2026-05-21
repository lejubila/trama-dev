<?php

declare(strict_types=1);

use App\Models\Room;
use App\Models\Site;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

function bootSiteFor(array $u): Site
{
    TenantContext::setId($u['tenant']->getKey());
    $s = Site::factory()->create();
    TenantContext::clear();

    return $s;
}

it('lists rooms of a site (nested route)', function (): void {
    $u = apiUser('admin');
    $s = bootSiteFor($u);
    TenantContext::setId($u['tenant']->getKey());
    Room::factory()->count(2)->create(['site_id' => $s->getKey()]);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->getJson("/api/v1/sites/{$s->id}/rooms")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('creates a room under a site', function (): void {
    $u = apiUser('admin');
    $s = bootSiteFor($u);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->postJson("/api/v1/sites/{$s->id}/rooms", ['name' => 'ROOM-X', 'site_id' => $s->id])
        ->assertCreated()
        ->assertJsonPath('data.attributes.name', 'ROOM-X');
});

it('shows a room', function (): void {
    $u = apiUser('admin');
    $s = bootSiteFor($u);
    TenantContext::setId($u['tenant']->getKey());
    $r = Room::factory()->create(['site_id' => $s->getKey(), 'name' => 'R-SHOW']);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->getJson("/api/v1/sites/{$s->id}/rooms/{$r->id}")
        ->assertOk()
        ->assertJsonPath('data.attributes.name', 'R-SHOW');
});

it('updates a room', function (): void {
    $u = apiUser('admin');
    $s = bootSiteFor($u);
    TenantContext::setId($u['tenant']->getKey());
    $r = Room::factory()->create(['site_id' => $s->getKey(), 'name' => 'OLD']);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->patchJson("/api/v1/sites/{$s->id}/rooms/{$r->id}", ['name' => 'NEW'])
        ->assertOk()
        ->assertJsonPath('data.attributes.name', 'NEW');
});

it('deletes a room', function (): void {
    $u = apiUser('admin');
    $s = bootSiteFor($u);
    TenantContext::setId($u['tenant']->getKey());
    $r = Room::factory()->create(['site_id' => $s->getKey()]);

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->deleteJson("/api/v1/sites/{$s->id}/rooms/{$r->id}")
        ->assertNoContent();
});
