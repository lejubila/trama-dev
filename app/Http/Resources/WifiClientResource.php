<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WifiAssociation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WifiAssociation
 */
class WifiClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'wifi-clients',
            'attributes' => [
                'wifi_network_id' => $this->wifi_network_id,
                'client_interface_id' => $this->client_interface_id,
                'client_equipment_id' => $this->clientInterface?->equipment_id,
                'client_equipment_name' => $this->clientInterface?->equipment?->name,
                'client_interface_name' => $this->clientInterface?->name,
                'preferred_broadcaster_interface_id' => $this->preferred_broadcaster_interface_id,
                'created_at' => $this->created_at?->toIso8601String(),
            ],
        ];
    }
}
