<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Payloads;
use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateWifiNetwork extends TramaTool
{
    protected string $name = 'update_wifi_network';

    protected string $description = 'Change a Wi-Fi network. Only the fields you pass are modified; broadcaster_interface_ids replaces the list.';

    protected string $ability = 'wifi:update';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'wifi_network_id' => $schema->integer()->required(),
            'ssid' => $schema->string()->max(64),
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

        $request->validate(['wifi_network_id' => 'required|integer']);

        return $this->api('PATCH', 'wifi-networks/'.$request->get('wifi_network_id'), $tenant, $this->only($request, Payloads::WIFI_KEYS));
    }
}
