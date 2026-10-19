<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DetachVpnClient extends TramaTool
{
    protected string $name = 'detach_vpn_client';

    protected string $description = 'Remove a client from a remote-access VPN.';

    protected string $ability = 'vpn:update';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'vpn_id' => $schema->integer()->required(),
            'client_id' => $schema->integer()->description('Client id from get_vpn.')->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        $data = $request->validate(['vpn_id' => 'required|integer', 'client_id' => 'required|integer']);
        $this->api('DELETE', "vpn/remote-access/{$data['vpn_id']}/clients/{$data['client_id']}", $tenant);

        return ['detached' => true];
    }
}
