<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NetworkInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NetworkInterface
 */
class NetworkInterfaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'interfaces',
            'attributes' => [
                'name' => $this->name,
                'equipment_id' => $this->equipment_id,
                'kind' => $this->getAttribute('type')?->value,
                'index' => $this->getAttribute('index'),
                'speed_mbps' => $this->speed_mbps,
                'media' => $this->media?->value,
                'connector' => $this->connector,
                'vlan_mode' => $this->vlan_mode?->value,
                'vlan_default' => $this->vlan_default,
                'vlans_allowed' => $this->vlans_allowed,
                'ip_address' => $this->ip_address,
                'mac_address' => $this->mac_address,
                'status' => $this->status?->value,
                'poe' => $this->poe?->value,
                'description' => $this->description,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'links' => [
                'self' => route('api.v1.interfaces.show', ['interface' => $this->id]),
            ],
        ];
    }
}
