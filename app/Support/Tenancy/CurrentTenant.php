<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\User;
use App\Support\Demo;

/**
 * Changes the user's active tenant. Normally it's persisted on the user row;
 * in demo mode every visitor shares the same account, so the choice lives in
 * the session instead — otherwise one visitor would switch it for everyone.
 */
final class CurrentTenant
{
    public const SESSION_KEY = 'demo.current_tenant_id';

    public static function set(User $user, ?int $tenantId): void
    {
        if (self::perSession()) {
            session()->put(self::SESSION_KEY, $tenantId);
            $user->setAttribute('current_tenant_id', $tenantId);
            $user->syncOriginalAttribute('current_tenant_id');
        } else {
            $user->forceFill(['current_tenant_id' => $tenantId])->save();
        }

        TenantContext::setId($tenantId);
    }

    public static function perSession(): bool
    {
        return Demo::enabled() && app()->bound('session.store') && request()->hasSession();
    }
}
