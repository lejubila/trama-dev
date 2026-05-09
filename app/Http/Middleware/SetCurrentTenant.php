<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $currentTenantId = $user->current_tenant_id;

        if ($currentTenantId === null) {
            // No tenant chosen yet — bootstrap with the first one the user belongs to,
            // if any. Avoids forcing a "pick a tenant" screen for users with one tenant.
            $first = $user->tenants()->first();

            if ($first !== null) {
                $user->forceFill(['current_tenant_id' => $first->getKey()])->save();
                TenantContext::setId($first->getKey());
                setPermissionsTeamId($first->getKey());
            }

            return $next($request);
        }

        // Defense in depth: if the saved current_tenant_id no longer matches a tenant
        // the user belongs to (membership revoked, tenant deleted), reset it.
        $stillMember = $user->tenants()->whereKey($currentTenantId)->exists();

        if (! $stillMember) {
            $user->forceFill(['current_tenant_id' => null])->save();

            return $next($request);
        }

        TenantContext::setId($currentTenantId);
        setPermissionsTeamId($currentTenantId);

        return $next($request);
    }
}
