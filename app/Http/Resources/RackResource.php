<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Rack;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Rack
 */
class RackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'racks',
            'attributes' => [
                'name' => $this->name,
                'room_id' => $this->room_id,
                'height_units' => $this->height_units,
                'width_mm' => $this->width_mm,
                'depth_mm' => $this->depth_mm,
                'numbering' => $this->numbering->value,
                'notes' => $this->notes,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'links' => [
                'self' => route('api.v1.racks.show', ['rack' => $this->id]),
            ],
        ];
    }
}
