<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
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

        // Resolve the set of tenants the user may operate in: admins/tecnici can
        // access every tenant; clienti only the ones they are assigned to.
        $accessibleQuery = $user->canManageData()
            ? Tenant::query()
            : $user->tenants();

        $current = $user->current_tenant_id;

        $valid = $current !== null
            && (clone $accessibleQuery)->whereKey($current)->exists();

        if (! $valid) {
            // Bootstrap (or repair) the current tenant with the first accessible one.
            $first = (clone $accessibleQuery)->orderBy('tenants.id')->first();
            $current = $first?->getKey();
            $user->forceFill(['current_tenant_id' => $current])->save();
        }

        if ($current !== null) {
            TenantContext::setId($current);
        }

        return $next($request);
    }
}
