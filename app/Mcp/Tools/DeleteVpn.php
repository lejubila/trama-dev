<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteVpn extends TramaTool
{
    protected string $name = 'delete_vpn';

    protected string $description = 'Delete a VPN. Ask the user for confirmation first.';

    protected string $ability = 'vpn:delete';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(['site_to_site', 'remote_access'])->required(),
            'vpn_id' => $schema->integer()->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        $data = $request->validate(['kind' => 'required|in:site_to_site,remote_access', 'vpn_id' => 'required|integer']);
        $path = $data['kind'] === 'site_to_site' ? 'vpn/site-to-site' : 'vpn/remote-access';
        $this->api('DELETE', "{$path}/{$data['vpn_id']}", $tenant);

        return ['deleted' => true];
    }
}
