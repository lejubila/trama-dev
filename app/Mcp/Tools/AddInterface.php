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

class AddInterface extends TramaTool
{
    protected string $name = 'add_interface';

    protected string $description = 'Add an interface (port) to a device.';

    protected string $ability = 'interfaces:create';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'equipment_id' => $schema->integer()->required(),
            'name' => $schema->string()->max(80)->description('Port name, e.g. "Gi1/0/1", "eth0", "wlan0".')->required(),
            'type' => $schema->string()->enum(InterfaceType::class)->required(),
            'media' => $schema->string()->enum(InterfaceMedia::class)->description('Use wireless for Wi-Fi radios, virtual for VPN/tunnel interfaces.')->required(),
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
            'backed_by_interface_id' => $schema->integer()->description('Only for type virtual: the physical port that carries this interface. For a VM vNIC, a NIC of the VM\'s hypervisor host; for a VLAN sub-interface, a port of the same device. Pass null to clear.'),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        $request->validate(['equipment_id' => 'required|integer']);
        $data = ['status' => 'up', 'poe' => 'none', ...$this->only($request, Payloads::IFACE_KEYS), 'equipment_id' => (int) $request->get('equipment_id')];

        return $this->api('POST', "equipment/{$data['equipment_id']}/interfaces", $tenant, $data);
    }
}
