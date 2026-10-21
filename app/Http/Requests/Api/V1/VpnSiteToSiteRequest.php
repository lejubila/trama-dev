<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\VpnSiteToSite;
use App\Validation\VpnRules;

class VpnSiteToSiteRequest extends ResourceRequest
{
    protected string $model = VpnSiteToSite::class;

    protected string $routeKey = 'vpn';

    protected function baseRules(): array
    {
        return VpnRules::siteToSite();
    }
}
