<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

afterEach(function (): void {
    TenantContext::clear();
});

it('rejects unauthenticated requests with 401', function (): void {
    $this->getJson('/api/v1/sites')->assertUnauthorized();
});

it('rejects authenticated requests without X-Tenant-Id with 400', function (): void {
    $u = apiUser('admin');

    $this->withHeaders([
        'Authorization' => "Bearer {$u['token']}",
        'Accept' => 'application/json',
    ])->getJson('/api/v1/sites')->assertStatus(400);
});

it('rejects requests for a tenant a cliente is not assigned to with 403', function (): void {
    $u = apiUser('cliente');
    $other = Tenant::factory()->create();

    $this->withHeaders(apiHeaders($u['token'], $other->getKey()))
        ->getJson('/api/v1/sites')
        ->assertForbidden();
});

it('accepts a valid Bearer + X-Tenant-Id and returns 200', function (): void {
    $u = apiUser('admin');

    $this->withHeaders(apiHeaders($u['token'], $u['tenant']->getKey()))
        ->getJson('/api/v1/sites')
        ->assertOk();
});
