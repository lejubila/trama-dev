<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Room;
use App\Validation\RoomRules;

class RoomRequest extends ResourceRequest
{
    protected string $model = Room::class;

    protected string $routeKey = 'room';

    protected function baseRules(): array
    {
        return RoomRules::rules();
    }
}
