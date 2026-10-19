<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Mcp\Payloads;
use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateEquipment extends TramaTool
{
    protected string $name = 'update_equipment';

    protected string $description = 'Change a device. Only the fields you pass are modified; pass rack_id/u_start/u_height to move it into a rack, or room_id to move it loose into a room.';

    protected string $ability = 'equipment:update';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'equipment_id' => $schema->integer()->required(),
            'name' => $schema->string()->max(150),
            'type' => $schema->string()->enum(EquipmentType::class),
            'vendor' => $schema->string(),
            'model' => $schema->string(),
            'serial' => $schema->string(),
            'firmware' => $schema->string(),
            'asset_tag' => $schema->string(),
            'status' => $schema->string()->enum(EquipmentStatus::class),
            'management_ip' => $schema->string()->description('Management IP address.'),
            'description' => $schema->string(),
            'rack_id' => $schema->integer()->description('Rack to mount the device in (see get_rack for free units).'),
            'u_start' => $schema->integer()->min(1)->description('Lowest rack unit occupied. Requires rack_id and u_height.'),
            'u_height' => $schema->integer()->min(1)->description('Number of rack units (1 for most switches).'),
            'side' => $schema->string()->enum(['front', 'rear']),
            'room_id' => $schema->integer()->description('Room (list_rooms) to place the device in WITHOUT a rack, e.g. a wall-mounted switch or a desktop firewall. Moves it out of any rack. Leave it out when mounting in a rack: the rack decides the room.'),
            'host_equipment_id' => $schema->integer()->description('Only for type virtual_machine (required there): the hypervisor the VM runs on. VMs are never rack-mounted.'),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        $request->validate(['equipment_id' => 'required|integer']);

        return $this->api('PATCH', 'equipment/'.$request->get('equipment_id'), $tenant, Payloads::equipment(array_diff_key($request->all(), ['equipment_id' => 1])));
    }
}
