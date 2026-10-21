<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\NetworkInterface;
use App\Validation\InterfaceRules;

class InterfaceRequest extends ResourceRequest
{
    protected string $model = NetworkInterface::class;

    protected string $routeKey = 'interface';

    protected function baseRules(): array
    {
        return InterfaceRules::rules();
    }
}
