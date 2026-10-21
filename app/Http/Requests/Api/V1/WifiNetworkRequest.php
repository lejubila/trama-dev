<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\WifiNetwork;
use App\Validation\WifiNetworkRules;

class WifiNetworkRequest extends ResourceRequest
{
    protected string $model = WifiNetwork::class;

    protected string $routeKey = 'wifi_network';

    protected function baseRules(): array
    {
        return WifiNetworkRules::rules();
    }
}
