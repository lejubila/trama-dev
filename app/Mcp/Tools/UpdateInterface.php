<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\InterfaceMedia;
use App\Enums\InterfacePoe;
use App\Enums\InterfaceStatus;
use App\Enums\InterfaceType;
use App\Enums\InterfaceVlanMode;
use App\Mcp\Payloads;
use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateInterface extends TramaTool
{
    protected string $name = 'update_interface';

    protected string $description = 'Change an interface (port). Only the fields you pass are modified.';

    protected string $ability = 'interfaces:update';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'interface_id' => $schema->integer()->required(),
            'name' => $schema->string()->max(80)->description('Port name, e.g. "Gi1/0/1", "eth0", "wlan0".'),
            'type' => $schema->string()->enum(InterfaceType::class),
            'media' => $schema->string()->enum(InterfaceMedia::class)->description('Use wireless for Wi-Fi radios, virtual for VPN/tunnel interfaces.'),
            'status' => $schema->string()->enum(InterfaceStatus::class)->description('Default up.'),
            'poe' => $schema->string()->enum(InterfacePoe::class)->description('Default none.'),
            'speed_mbps' => $schema->integer(),
            'connector' => $schema->string()->description('e.g. RJ45, SFP+, LC.'),
            'vlan_mode' => $schema->string()->enum(InterfaceVlanMode::class),
            'vlan_default' => $schema->integer()->min(1)->max(4094)->description('Access / native VLAN.'),
            'vlans_allowed' => $schema->array()->items($schema->integer())->description('Tagged VLANs on a trunk.'),
            'ip_address' => $schema->string()->description('IP, optionally in CIDR, e.g. 10.0.0.1/24.'),
            'mac_address' => $schema->string(),
            'description' => $schema->string(),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        $request->validate(['interface_id' => 'required|integer']);

        return $this->api('PATCH', 'interfaces/'.$request->get('interface_id'), $tenant, $this->only($request, Payloads::IFACE_KEYS));
    }
}
