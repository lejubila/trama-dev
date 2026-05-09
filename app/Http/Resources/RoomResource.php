<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Room
 */
class RoomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'rooms',
            'attributes' => [
                'name' => $this->name,
                'floor' => $this->floor,
                'site_id' => $this->site_id,
                'notes' => $this->notes,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'links' => [
                'self' => route('api.v1.sites.rooms.show', ['site' => $this->site_id, 'room' => $this->id]),
            ],
        ];
    }
}
