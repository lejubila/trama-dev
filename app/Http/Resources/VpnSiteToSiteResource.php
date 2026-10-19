<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VpnSiteToSite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VpnSiteToSite
 */
class VpnSiteToSiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'vpn-site-to-site',
            'attributes' => [
                'name' => $this->name,
                'protocol' => $this->protocol->value,
                'endpoint_a_interface_id' => $this->endpoint_a_interface_id,
                'endpoint_b_interface_id' => $this->endpoint_b_interface_id,
                'routed_vlans_a' => $this->routed_vlans_a,
                'routed_vlans_b' => $this->routed_vlans_b,
                'routed_networks_a' => $this->routed_networks_a,
                'routed_networks_b' => $this->routed_networks_b,
                'notes' => $this->notes,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
            'relationships' => [
                'endpoint_a' => new NetworkInterfaceResource($this->whenLoaded('endpointAInterface')),
                'endpoint_b' => new NetworkInterfaceResource($this->whenLoaded('endpointBInterface')),
            ],
            'links' => [
                'self' => route('api.v1.vpn.site-to-site.show', ['vpn' => $this->id]),
            ],
        ];
    }
}
