<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\TramaTool;
use App\Models\Connection;
use App\Models\NetworkInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetEquipment extends TramaTool
{
    protected string $name = 'get_equipment';

    protected string $description = 'Full details of one device: attributes, rack position and every interface (port). When the token can read connections, each interface also shows what it is cabled to, so free ports have "connected_to": null.';

    protected string $ability = 'equipment:read';

    protected function arguments(JsonSchema $schema): array
    {
        return [
            'equipment_id' => $schema->integer()->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $data = $request->validate(['equipment_id' => 'required|integer']);
        $equipment = $this->api('GET', "equipment/{$data['equipment_id']}", $this->tenant($request))['data'];

        if (! auth()->user()?->tokenCan('connections:read')) {
            return $equipment;
        }

        $ids = array_column($equipment['interfaces'] ?? [], 'id');
        $links = [];
        Connection::query()
            ->with(['fromInterface.equipment:id,name', 'toInterface.equipment:id,name'])
            ->where(fn ($q) => $q->whereIn('from_interface_id', $ids)->orWhereIn('to_interface_id', $ids))
            ->get()
            ->each(function (Connection $c) use (&$links): void {
                $describe = fn (?NetworkInterface $i): ?array => $i === null ? null : [
                    'interface_id' => $i->id,
                    'interface' => $i->name,
                    'equipment_id' => $i->equipment_id,
                    'equipment' => $i->equipment?->name,
                ];
                $meta = ['connection_id' => $c->id, 'cable_type' => $c->cable_type, 'cable_label' => $c->cable_label, 'status' => $c->status?->value];
                $links[$c->from_interface_id] = [...$meta, ...(array) $describe($c->toInterface)];
                $links[$c->to_interface_id] = [...$meta, ...(array) $describe($c->fromInterface)];
            });

        foreach ($equipment['interfaces'] ?? [] as $k => $iface) {
            $equipment['interfaces'][$k]['connected_to'] = $links[$iface['id']] ?? null;
        }

        return $equipment;
    }
}
