<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\VpnProtocol;
use App\Enums\VpnRoutingMode;
use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

class CreateVpnRemoteAccess extends TramaTool
{
    protected string $name = 'create_vpn_remote_access';

    protected string $description = 'Create a remote-access VPN terminating on a firewall interface.';

    protected string $ability = 'vpn:create';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(100)->required(),
            'protocol' => $schema->string()->enum(VpnProtocol::class)->required(),
            'firewall_interface_id' => $schema->integer()->required(),
            'routing_mode' => $schema->string()->enum(VpnRoutingMode::class)->required(),
            'client_network_cidr' => $schema->string()->description('Network assigned to clients, e.g. 10.8.0.0/24.'),
            'routed_vlans' => $schema->array()->items($schema->integer()),
            'notes' => $schema->string(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        return $this->api('POST', 'vpn/remote-access', $tenant, $this->only($request, ['name', 'protocol', 'firewall_interface_id', 'routing_mode', 'client_network_cidr', 'routed_vlans', 'notes']));
    }
}
