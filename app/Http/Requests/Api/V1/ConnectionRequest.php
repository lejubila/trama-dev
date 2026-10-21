<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Connection;
use App\Validation\ConnectionRules;

class ConnectionRequest extends ResourceRequest
{
    protected string $model = Connection::class;

    protected string $routeKey = 'connection';

    protected function baseRules(): array
    {
        return ConnectionRules::rules();
    }
}
