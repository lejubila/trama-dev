<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteWifiNetwork extends TramaTool
{
    protected string $name = 'delete_wifi_network';

    protected string $description = 'Delete a Wi-Fi network. Ask the user for confirmation first.';

    protected string $ability = 'wifi:delete';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'wifi_network_id' => $schema->integer()->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        $request->validate(['wifi_network_id' => 'required|integer']);
        $this->api('DELETE', 'wifi-networks/'.$request->get('wifi_network_id'), $tenant);

        return ['deleted' => true];
    }
}
