<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('lists the user tokens', function (): void {
    $u = apiUser('admin');

    $this->withHeaders([
        'Authorization' => "Bearer {$u['token']}",
        'Accept' => 'application/json',
    ])->getJson('/api/v1/tokens')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('creates a new token and returns the plain text once', function (): void {
    $u = apiUser('admin');

    $r = $this->withHeaders([
        'Authorization' => "Bearer {$u['token']}",
        'Accept' => 'application/json',
    ])->postJson('/api/v1/tokens', ['name' => 'CI-runner', 'abilities' => ['read']])
        ->assertCreated();

    expect($r->json('data.plain_text'))->toBeString()
        ->and(strlen((string) $r->json('data.plain_text')))->toBeGreaterThan(20);
    expect(PersonalAccessToken::query()->where('name', 'CI-runner')->exists())->toBeTrue();
});

it('the new token can authenticate immediately', function (): void {
    $u = apiUser('admin');
    /** @var User $user */
    $user = $u['user'];

    $token = $user->createToken('fresh', ['read'])->plainTextToken;

    $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'X-Tenant-Id' => (string) $u['tenant']->getKey(),
        'Accept' => 'application/json',
    ])->getJson('/api/v1/sites')->assertOk();
});

it('revokes a token', function (): void {
    $u = apiUser('admin');
    /** @var User $user */
    $user = $u['user'];
    $extra = $user->createToken('to-revoke', ['read']);

    $this->withHeaders([
        'Authorization' => "Bearer {$u['token']}",
        'Accept' => 'application/json',
    ])->deleteJson("/api/v1/tokens/{$extra->accessToken->id}")
        ->assertNoContent();

    expect(PersonalAccessToken::query()->whereKey($extra->accessToken->id)->exists())->toBeFalse();
});
