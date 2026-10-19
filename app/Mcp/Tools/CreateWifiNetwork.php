<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Payloads;
use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

class CreateWifiNetwork extends TramaTool
{
    protected string $name = 'create_wifi_network';

    protected string $description = 'Create a Wi-Fi network (SSID). broadcaster_interface_ids must be wireless interfaces of access points or controllers.';

    protected string $ability = 'wifi:create';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'ssid' => $schema->string()->max(64)->required(),
            'site_id' => $schema->integer(),
            'security_type' => $schema->string()->description('e.g. wpa2, wpa3, wpa2_ent, open.'),
            'vlan_id' => $schema->integer()->min(1)->max(4094),
            'hidden_ssid' => $schema->boolean(),
            'notes' => $schema->string(),
            'broadcaster_interface_ids' => $schema->array()->items($schema->integer())->description('Wireless interfaces of access points/controllers broadcasting this SSID. Replaces the current list.'),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        return $this->api('POST', 'wifi-networks', $tenant, $this->only($request, Payloads::WIFI_KEYS));
    }
}
