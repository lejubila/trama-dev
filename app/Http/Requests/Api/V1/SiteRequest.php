<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Site;
use App\Validation\SiteRules;

class SiteRequest extends ResourceRequest
{
    protected string $model = Site::class;

    protected string $routeKey = 'site';

    protected function baseRules(): array
    {
        return SiteRules::rules();
    }
}
