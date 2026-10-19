<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API gate that picks the tenant from `X-Tenant-Id` and verifies the
 * authenticated user may access it (admins/tecnici: any tenant; clienti: only
 * the ones they are assigned to) and the token's own tenant allow-list, if
 * any. Sets TenantContext so global scopes filter
 * correctly downstream — the header-driven counterpart of SetCurrentTenant.
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

        $tenant = Tenant::query()->find($tenantId);
        $token = $user->currentAccessToken();
        if ($tenant === null
            || ! $user->canAccessTenant($tenant)
            || ($token instanceof PersonalAccessToken && ! $token->allowsTenant($tenant->getKey()))) {
            return response()->json(['error' => 'Forbidden tenant'], 403);
        }

        TenantContext::setId($tenant->getKey());

        // Reflect the chosen tenant onto the User instance the controllers receive.
        $user->setAttribute('current_tenant_id', $tenant->getKey());

        return $next($request);
    }
}
