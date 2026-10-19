<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VpnRemoteAccessClient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VpnRemoteAccessClient
 */
class VpnRemoteClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'vpn-remote-clients',
            'attributes' => [
                'vpn_remote_access_id' => $this->vpn_remote_access_id,
                'client_interface_id' => $this->client_interface_id,
                'client_equipment_id' => $this->clientInterface?->equipment_id,
                'client_equipment_name' => $this->clientInterface?->equipment?->name,
                'client_interface_name' => $this->clientInterface?->name,
                'username' => $this->username,
                'created_at' => $this->created_at?->toIso8601String(),
            ],
        ];
    }
}
