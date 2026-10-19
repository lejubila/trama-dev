<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\VpnRemoteAccess;
use App\Models\WifiNetwork;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Attaching a client device to a Wi-Fi network or a remote-access VPN:
 * both count as an update of the parent.
 */
class AttachClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $parent = $this->route('wifi_network') ?? $this->route('vpn');

        return ($parent instanceof WifiNetwork || $parent instanceof VpnRemoteAccess)
            && ($this->user()?->can('update', $parent) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'equipment_id' => 'required|integer',
            // Omitted: a new wlanN / vpnN interface is created on the client.
            'interface_id' => 'nullable|integer',
            'preferred_broadcaster_interface_id' => 'nullable|integer',
            'username' => 'nullable|string|max:255',
        ];
    }
}
