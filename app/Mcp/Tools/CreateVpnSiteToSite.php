<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\VpnProtocol;
use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

class CreateVpnSiteToSite extends TramaTool
{
    protected string $name = 'create_vpn_site_to_site';

    protected string $description = 'Create a site-to-site VPN tunnel between two firewall interfaces (both ends must be interfaces of firewall devices).';

    protected string $ability = 'vpn:create';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(100)->required(),
            'protocol' => $schema->string()->enum(VpnProtocol::class)->required(),
            'endpoint_a_interface_id' => $schema->integer()->required(),
            'endpoint_b_interface_id' => $schema->integer()->required(),
            'routed_vlans_a' => $schema->array()->items($schema->integer()),
            'routed_vlans_b' => $schema->array()->items($schema->integer()),
            'routed_networks_a' => $schema->array()->items($schema->string())->description('IPv4 networks in CIDR, e.g. 192.168.10.0/24.'),
            'routed_networks_b' => $schema->array()->items($schema->string()),
            'notes' => $schema->string(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        return $this->api('POST', 'vpn/site-to-site', $tenant, $this->only($request, ['name', 'protocol', 'endpoint_a_interface_id', 'endpoint_b_interface_id', 'routed_vlans_a', 'routed_vlans_b', 'routed_networks_a', 'routed_networks_b', 'notes']));
    }
}
