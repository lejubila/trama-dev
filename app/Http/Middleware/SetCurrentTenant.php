<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
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

        $current = CurrentTenant::perSession()
            ? $request->session()->get(CurrentTenant::SESSION_KEY)
            : $user->current_tenant_id;

        $valid = $current !== null
            && (clone $accessibleQuery)->whereKey($current)->exists();

        if (! $valid) {
            // Bootstrap (or repair) the current tenant with the first accessible one.
            $first = (clone $accessibleQuery)->orderBy('tenants.id')->first();
            $current = $first?->getKey();
            CurrentTenant::set($user, $current);
        } elseif (CurrentTenant::perSession()) {
            // Policies read current_tenant_id from the model: mirror the
            // session value in memory, never on the shared demo row.
            $user->setAttribute('current_tenant_id', $current);
            $user->syncOriginalAttribute('current_tenant_id');
        }

        if ($current !== null) {
            TenantContext::setId($current);
        }

        return $next($request);
    }
}
