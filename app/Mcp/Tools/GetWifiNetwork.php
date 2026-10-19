<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetWifiNetwork extends TramaTool
{
    protected string $name = 'get_wifi_network';

    protected string $description = 'Details of a Wi-Fi network: broadcasting access-point radios and associated client devices.';

    protected string $ability = 'wifi:read';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'wifi_network_id' => $schema->integer()->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $data = $request->validate(['wifi_network_id' => 'required|integer']);

        return $this->api('GET', "wifi-networks/{$data['wifi_network_id']}", $this->tenant($request));
    }
}
