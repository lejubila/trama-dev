<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enums\VpnProtocol;
use App\Enums\VpnRoutingMode;
use App\Rules\Ipv4Cidr;
use Illuminate\Validation\Rule;

class VpnRules
{
    /**
     * @return array<string, mixed>
     */
    public static function siteToSite(): array
    {
        return [
            'name' => 'required|string|max:100',
            'protocol' => ['required', Rule::enum(VpnProtocol::class)],
            'endpoint_a_interface_id' => 'required|integer',
            'endpoint_b_interface_id' => 'required|integer',
            'routed_vlans_a' => 'nullable|array',
            'routed_vlans_a.*' => 'integer|min:1|max:4094|distinct',
            'routed_vlans_b' => 'nullable|array',
            'routed_vlans_b.*' => 'integer|min:1|max:4094|distinct',
            'routed_networks_a' => 'nullable|array',
            'routed_networks_a.*' => ['string', 'distinct', new Ipv4Cidr],
            'routed_networks_b' => 'nullable|array',
            'routed_networks_b.*' => ['string', 'distinct', new Ipv4Cidr],
            'notes' => 'nullable|string|max:5000',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function remoteAccess(): array
    {
        return [
            'name' => 'required|string|max:100',
            'protocol' => ['required', Rule::enum(VpnProtocol::class)],
            'firewall_interface_id' => 'required|integer',
            'routing_mode' => ['required', Rule::enum(VpnRoutingMode::class)],
            'client_network_cidr' => ['nullable', 'string', new Ipv4Cidr],
            'routed_vlans' => 'nullable|array',
            'routed_vlans.*' => 'integer|min:1|max:4094|distinct',
            'notes' => 'nullable|string|max:5000',
        ];
    }
}
