<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum token with an optional allow-list of tenants: null means every
 * tenant the owner can access, otherwise only the listed ids.
 *
 * @property list<int>|null $tenant_ids
 * @property int|null $oauth_grant_id Set on tokens issued through OAuth (claude.ai, ChatGPT…).
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'tenant_ids',
        'expires_at',
        'oauth_grant_id',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'tenant_ids' => 'array',
        ];
    }

    /**
     * @return BelongsTo<OAuthGrant, $this>
     */
    public function oauthGrant(): BelongsTo
    {
        return $this->belongsTo(OAuthGrant::class, 'oauth_grant_id');
    }

    public function allowsTenant(int $tenantId): bool
    {
        return $this->tenant_ids === null
            || in_array($tenantId, array_map('intval', $this->tenant_ids), true);
    }
}
