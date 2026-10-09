<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SwitchTenantController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        if (! $user->canAccessTenant($tenant)) {
            throw new AccessDeniedHttpException(__('tenants.err_no_access'));
        }

        CurrentTenant::set($user, (int) $tenant->getKey());

        return redirect()->intended(route('dashboard'));
    }
}
