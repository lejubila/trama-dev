<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Support\Api\AccessibleTenants;
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
        $tenants = AccessibleTenants::query($request->user())->orderBy('tenants.name')->get();

        return TenantResource::collection($tenants);
    }
}
