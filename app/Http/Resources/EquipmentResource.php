<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Equipment
 */
class EquipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'equipment',
            'attributes' => [
                'name' => $this->name,
                'kind' => $this->type?->value,
                'vendor' => $this->vendor,
                'model' => $this->model,
                'serial' => $this->serial,
                'firmware' => $this->firmware,
                'asset_tag' => $this->asset_tag,
                'mounted' => (bool) $this->mounted,
                'locked' => (bool) $this->locked,
                'position' => $this->mounted ? [
                    'rack_id' => $this->rack_id,
                    'u_start' => $this->position_u_start,
                    'u_height' => $this->position_u_height,
                    'orient' => $this->position_orient,
                ] : null,
                'status' => $this->status?->value,
                'management_ip' => $this->management_ip,
                'description' => $this->description,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'relationships' => [
                'interfaces' => NetworkInterfaceResource::collection($this->whenLoaded('interfaces')),
                'rack' => new RackResource($this->whenLoaded('rack')),
            ],
            'links' => [
                'self' => route('api.v1.equipment.show', ['equipment' => $this->id]),
            ],
        ];
    }
}
