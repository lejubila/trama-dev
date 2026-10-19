<?php

declare(strict_types=1);

namespace App\Validation;

class WifiNetworkRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'ssid' => 'required|string|max:64',
            'site_id' => 'nullable|integer',
            'security_type' => 'nullable|string|max:32',
            'vlan_id' => 'nullable|integer|min:1|max:4094',
            'hidden_ssid' => 'boolean',
            'notes' => 'nullable|string|max:2000',
            'broadcaster_interface_ids' => 'array',
            'broadcaster_interface_ids.*' => 'integer|distinct',
        ];
    }
}
