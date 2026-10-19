<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WifiNetwork;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WifiNetwork
 */
class WifiNetworkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'wifi-networks',
            'attributes' => [
                'ssid' => $this->ssid,
                'site_id' => $this->site_id,
                'security_type' => $this->security_type,
                'vlan_id' => $this->vlan_id,
                'hidden_ssid' => (bool) $this->hidden_ssid,
                'notes' => $this->notes,
                'broadcaster_interface_ids' => $this->whenLoaded('broadcasters', fn () => $this->broadcasters->pluck('id')->values()),
                'clients_count' => $this->whenCounted('associations'),
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'relationships' => [
                'broadcasters' => NetworkInterfaceResource::collection($this->whenLoaded('broadcasters')),
                'clients' => WifiClientResource::collection($this->whenLoaded('associations')),
            ],
            'links' => [
                'self' => route('api.v1.wifi-networks.show', ['wifi_network' => $this->id]),
                'clients' => route('api.v1.wifi-networks.clients.index', ['wifi_network' => $this->id]),
            ],
        ];
    }
}
