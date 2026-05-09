<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API gate that picks the tenant from `X-Tenant-Id` and verifies the
 * authenticated user is a member. Sets TenantContext + spatie's team scope
 * so global scopes filter correctly downstream — same effect as
 * SetCurrentTenant in the web flow, but driven by a header instead of the
 * user's stored current_tenant_id.
 */
class EnsureTenantHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $tenantId = (int) $request->header('X-Tenant-Id', '0');
        if ($tenantId <= 0) {
            return response()->json(['error' => 'Missing X-Tenant-Id header'], 400);
        }

        $tenant = $user->tenants()->whereKey($tenantId)->first();
        if ($tenant === null) {
            return response()->json(['error' => 'Forbidden tenant'], 403);
        }

        TenantContext::setId($tenant->getKey());
        setPermissionsTeamId($tenant->getKey());

        // Reflect the chosen tenant onto the model used by spatie/permission
        // (so $user->can(...) resolves roles in the right team scope) and
        // onto the User instance the controllers will receive.
        $user->setAttribute('current_tenant_id', $tenant->getKey());

        return $next($request);
    }
}
