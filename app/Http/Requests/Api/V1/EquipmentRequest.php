<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Equipment;
use App\Validation\EquipmentRules;

class EquipmentRequest extends ResourceRequest
{
    protected string $model = Equipment::class;

    protected string $routeKey = 'equipment';

    protected function baseRules(): array
    {
        return EquipmentRules::rules();
    }
}
