<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantController extends Controller
{
    /**
     * Tenants usable as X-Tenant-Id: those the user may access, narrowed by
     * the token's tenant allow-list. No tenant header needed.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        $tenants = ($user->canManageData() ? Tenant::query() : $user->tenants())
            ->when(
                $token instanceof PersonalAccessToken && $token->tenant_ids !== null,
                fn ($q) => $q->whereIn('tenants.id', $token->tenant_ids),
            )
            ->orderBy('tenants.name')
            ->get();

        return TenantResource::collection($tenants);
    }
}
