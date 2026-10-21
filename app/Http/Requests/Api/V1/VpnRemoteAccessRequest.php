<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\VpnRemoteAccess;
use App\Validation\VpnRules;

class VpnRemoteAccessRequest extends ResourceRequest
{
    protected string $model = VpnRemoteAccess::class;

    protected string $routeKey = 'vpn';

    protected function baseRules(): array
    {
        return VpnRules::remoteAccess();
    }
}
