<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListWifiNetworks extends TramaTool
{
    protected string $name = 'list_wifi_networks';

    protected string $description = 'List Wi-Fi networks (SSIDs) of a customer with VLAN, security, broadcasting radios and number of clients.';

    protected string $ability = 'wifi:read';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('SSID contains.'),
            'site_id' => $schema->integer(),
        ];
    }

    protected function execute(Request $request): array
    {
        return $this->api('GET', 'wifi-networks', $this->tenant($request), [
            'q' => $request->get('query'),
            'site_id' => $request->get('site_id'),
            'per_page' => 200,
        ]);
    }
}
