<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tenants an API/MCP caller may work on: those the user can access,
 * narrowed by the token's tenant allow-list.
 */
final class AccessibleTenants
{
    /**
     * @return Builder<Tenant>
     */
    public static function query(User $user): Builder
    {
        $token = $user->currentAccessToken();

        return $user->accessibleTenants()->when(
            $token instanceof PersonalAccessToken && $token->tenant_ids !== null,
            fn (Builder $q) => $q->whereIn('tenants.id', $token->tenant_ids),
        );
    }
}
