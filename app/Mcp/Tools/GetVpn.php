<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetVpn extends TramaTool
{
    protected string $name = 'get_vpn';

    protected string $description = 'Details of a VPN. Remote-access VPNs include their connected clients.';

    protected string $ability = 'vpn:read';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(['site_to_site', 'remote_access'])->required(),
            'vpn_id' => $schema->integer()->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $data = $request->validate(['kind' => 'required|in:site_to_site,remote_access', 'vpn_id' => 'required|integer']);
        $path = $data['kind'] === 'site_to_site' ? 'vpn/site-to-site' : 'vpn/remote-access';

        return $this->api('GET', "{$path}/{$data['vpn_id']}", $this->tenant($request));
    }
}
