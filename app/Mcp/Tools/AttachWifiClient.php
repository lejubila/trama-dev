<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

class AttachWifiClient extends TramaTool
{
    protected string $name = 'attach_wifi_client';

    protected string $description = 'Associate a client device (laptop, phone, printer…) with a Wi-Fi network. Without interface_id a wireless interface (wlanN) is created on the device.';

    protected string $ability = 'wifi:update';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'wifi_network_id' => $schema->integer()->required(),
            'equipment_id' => $schema->integer()->required(),
            'interface_id' => $schema->integer()->description('Existing wireless interface of the client.'),
            'preferred_broadcaster_interface_id' => $schema->integer()->description('Access-point radio the client prefers, among the network broadcasters.'),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        $request->validate(['wifi_network_id' => 'required|integer']);

        return $this->api('POST', 'wifi-networks/'.$request->get('wifi_network_id').'/clients', $tenant, $this->only($request, ['equipment_id', 'interface_id', 'preferred_broadcaster_interface_id']));
    }
}
