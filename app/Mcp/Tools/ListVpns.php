<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListVpns extends TramaTool
{
    protected string $name = 'list_vpns';

    protected string $description = 'List the VPNs of a customer: site-to-site tunnels (between two firewall interfaces) and remote-access VPNs (clients connecting to a firewall).';

    protected string $ability = 'vpn:read';

    protected function arguments(JsonSchema $schema): array
    {
        return [];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        return [
            'site_to_site' => $this->api('GET', 'vpn/site-to-site', $tenant, ['per_page' => 200])['data'] ?? [],
            'remote_access' => $this->api('GET', 'vpn/remote-access', $tenant, ['per_page' => 200])['data'] ?? [],
        ];
    }
}
