<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Connection
 */
class ConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'connections',
            'attributes' => [
                'from_interface_id' => $this->from_interface_id,
                'to_interface_id' => $this->to_interface_id,
                'cable_type' => $this->cable_type,
                'cable_length_m' => $this->cable_length_m,
                'cable_label' => $this->cable_label,
                'color' => $this->color,
                'status' => $this->status?->value,
                'notes' => $this->notes,
                'established_at' => $this->established_at?->toDateString(),
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'links' => [
                'self' => route('api.v1.connections.show', ['connection' => $this->id]),
            ],
        ];
    }
}
