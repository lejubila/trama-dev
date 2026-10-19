<?php

declare(strict_types=1);

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum token with an optional allow-list of tenants: null means every
 * tenant the owner can access, otherwise only the listed ids.
 *
 * @property list<int>|null $tenant_ids
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'tenant_ids',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'tenant_ids' => 'array',
        ];
    }

    public function allowsTenant(int $tenantId): bool
    {
        return $this->tenant_ids === null
            || in_array($tenantId, array_map('intval', $this->tenant_ids), true);
    }
}
