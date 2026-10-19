<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Mcp\Payloads;
use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

class CreateEquipment extends TramaTool
{
    protected string $name = 'create_equipment';

    protected string $description = 'Create a device. To mount it in a rack pass rack_id, u_start and u_height (check free units with get_rack first); otherwise it is created unmounted (e.g. ceiling access points, desktops). A virtual machine needs host_equipment_id (its hypervisor).';

    protected string $ability = 'equipment:create';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(150)->required(),
            'type' => $schema->string()->enum(EquipmentType::class)->required(),
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
            'host_equipment_id' => $schema->integer()->description('Only for type virtual_machine (required there): the hypervisor the VM runs on. VMs are never rack-mounted.'),
        ];
    }

    protected function execute(Request $request): array
    {
        $tenant = $this->tenant($request);

        return $this->api('POST', 'equipment', $tenant, Payloads::equipment($request->all()));
    }
}
