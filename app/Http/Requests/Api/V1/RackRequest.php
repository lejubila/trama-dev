<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Rack;
use App\Validation\RackRules;

class RackRequest extends ResourceRequest
{
    protected string $model = Rack::class;

    protected string $routeKey = 'rack';

    protected function baseRules(): array
    {
        return RackRules::rules();
    }
}
