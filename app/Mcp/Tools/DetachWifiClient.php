<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DetachWifiClient extends TramaTool
{
    protected string $name = 'detach_wifi_client';

    protected string $description = 'Remove a client from a Wi-Fi network.';

    protected string $ability = 'wifi:update';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'wifi_network_id' => $schema->integer()->required(),
            'client_id' => $schema->integer()->description('Association id from get_wifi_network clients.')->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        $data = $request->validate(['wifi_network_id' => 'required|integer', 'client_id' => 'required|integer']);
        $this->api('DELETE', "wifi-networks/{$data['wifi_network_id']}/clients/{$data['client_id']}", $tenant);

        return ['detached' => true];
    }
}
