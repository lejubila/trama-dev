<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VpnRemoteAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VpnRemoteAccess
 */
class VpnRemoteAccessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'vpn-remote-access',
            'attributes' => [
                'name' => $this->name,
                'protocol' => $this->protocol->value,
                'firewall_interface_id' => $this->firewall_interface_id,
                'routing_mode' => $this->routing_mode->value,
                'client_network_cidr' => $this->client_network_cidr,
                'routed_vlans' => $this->routed_vlans,
                'notes' => $this->notes,
                'clients_count' => $this->whenCounted('clients'),
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'relationships' => [
                'firewall_interface' => new NetworkInterfaceResource($this->whenLoaded('firewallInterface')),
                'clients' => VpnRemoteClientResource::collection($this->whenLoaded('clients')),
            ],
            'links' => [
                'self' => route('api.v1.vpn.remote-access.show', ['vpn' => $this->id]),
                'clients' => route('api.v1.vpn.remote-access.clients.index', ['vpn' => $this->id]),
            ],
        ];
    }
}
