<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

class AttachVpnClient extends TramaTool
{
    protected string $name = 'attach_vpn_client';

    protected string $description = 'Connect a client device to a remote-access VPN. Without interface_id a virtual interface (vpnN) is created on the device.';

    protected string $ability = 'vpn:update';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'vpn_id' => $schema->integer()->description('Remote-access VPN id.')->required(),
            'equipment_id' => $schema->integer()->required(),
            'interface_id' => $schema->integer()->description('Existing virtual interface of the client.'),
            'username' => $schema->string(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        $request->validate(['vpn_id' => 'required|integer']);

        return $this->api('POST', 'vpn/remote-access/'.$request->get('vpn_id').'/clients', $tenant, $this->only($request, ['equipment_id', 'interface_id', 'username']));
    }
}
