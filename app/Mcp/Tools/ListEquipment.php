<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Mcp\TramaTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListEquipment extends TramaTool
{
    protected string $name = 'list_equipment';

    protected string $description = 'List devices (switches, routers, firewalls, access points, servers, patch panels…) of a customer, with optional filters. Paginated: 100 per page.';

    protected string $ability = 'equipment:read';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Name, serial or model contains.'),
            'type' => $schema->string()->enum(EquipmentType::class),
            'status' => $schema->string()->enum(EquipmentStatus::class),
            'site_id' => $schema->integer(),
            'rack_id' => $schema->integer(),
            'host_equipment_id' => $schema->integer()->description('Only the virtual machines running on this hypervisor.'),
            'page' => $schema->integer()->min(1),
        ];
    }

    protected function execute(Request $request): array
    {
        return $this->api('GET', 'equipment', $this->tenant($request), [
            'q' => $request->get('query'),
            'type' => $request->get('type'),
            'status' => $request->get('status'),
            'site_id' => $request->get('site_id'),
            'rack_id' => $request->get('rack_id'),
            'host_equipment_id' => $request->get('host_equipment_id'),
            'page' => $request->get('page'),
            'per_page' => 100,
        ]);
    }
}
