<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\EquipmentType;
use App\Mcp\TramaTool;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetEquipment extends TramaTool
{
    protected string $name = 'get_equipment';

    protected string $description = 'Full details of one device: attributes, rack position and every interface (port). When the token can read connections, each interface also shows what it is cabled to, so free ports have "connected_to": null. Virtual interfaces show the physical port that carries them (backed_by); physical ports list the vNICs they carry; hypervisors list their virtual_machines.';

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
        $equipment = $this->withVirtualization($this->api('GET', "equipment/{$data['equipment_id']}", $this->tenant($request))['data']);

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

    /**
     * Resolves the vNIC ↔ pNIC backing into names, so the model can answer
     * "which host NIC carries this VM?" / "what runs over this port?" without
     * extra lookups. Hypervisors also list the VMs they host.
     *
     * @param  array<string, mixed>  $equipment
     * @return array<string, mixed>
     */
    private function withVirtualization(array $equipment): array
    {
        $describe = fn (NetworkInterface $i): array => [
            'interface_id' => $i->id,
            'interface' => $i->name,
            'equipment_id' => $i->equipment_id,
            'equipment' => $i->equipment?->name,
        ];

        $ids = array_column($equipment['interfaces'] ?? [], 'id');
        $backingIds = array_filter(array_column($equipment['interfaces'] ?? [], 'backed_by_interface_id'));

        $backings = NetworkInterface::query()->with('equipment:id,name')->whereIn('id', $backingIds)->get()->keyBy('id');
        $carried = NetworkInterface::query()->with('equipment:id,name')->whereIn('backed_by_interface_id', $ids)
            ->orderBy('name')->get()->groupBy('backed_by_interface_id');

        foreach ($equipment['interfaces'] ?? [] as $k => $iface) {
            $backing = $backings->get($iface['backed_by_interface_id'] ?? 0);
            $equipment['interfaces'][$k]['backed_by'] = $backing !== null ? $describe($backing) : null;
            unset($equipment['interfaces'][$k]['backed_by_interface_id']);

            $vnics = $carried->get($iface['id']);
            if ($vnics !== null) {
                $equipment['interfaces'][$k]['carries'] = $vnics->map($describe)->values()->all();
            }
        }

        if (($equipment['kind'] ?? null) === EquipmentType::Hypervisor->value) {
            $equipment['virtual_machines'] = Equipment::query()
                ->where('host_equipment_id', $equipment['id'])
                ->orderBy('name')
                ->get(['id', 'name', 'status'])
                ->map(fn (Equipment $vm): array => ['id' => $vm->id, 'name' => $vm->name, 'status' => $vm->status?->value])
                ->all();
        }

        return $equipment;
    }
}
