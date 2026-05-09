<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SwitchTenantController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        if (! $user->belongsToTenant($tenant)) {
            throw new AccessDeniedHttpException('Non sei membro di questo tenant.');
        }

        $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();

        return redirect()->intended(route('dashboard'));
    }
}
