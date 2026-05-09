<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Connection;
use App\Models\Equipment;

class TopologyService
{
    /**
     * Builds a Cytoscape.js elements payload for the current tenant context.
     *
     * Optional narrowing:
     *  - $siteId: only equipment mounted in racks/rooms of that site
     *  - $types: restrict to these EquipmentType values
     *  - $vlan: keep only equipment that has at least one interface tagged
     *           with this VLAN (default or in vlans_allowed)
     *  - $status: restrict by EquipmentStatus value
     *
     * Active connections only; only edges whose BOTH endpoints survived the
     * node filters are emitted.
     *
     * @param  list<string>|null  $types
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function buildGraph(
        ?int $siteId = null,
        ?array $types = null,
        ?int $vlan = null,
        ?string $status = null,
    ): array {
        $equipmentQuery = Equipment::query()->with('rack.room');

        if ($siteId !== null) {
            $equipmentQuery->whereHas('rack.room', function ($q) use ($siteId): void {
                $q->where('site_id', $siteId);
            });
        }

        if ($types !== null && $types !== []) {
            $equipmentQuery->whereIn('type', $types);
        }

        if ($status !== null && $status !== '') {
            $equipmentQuery->where('status', $status);
        }

        if ($vlan !== null) {
            $equipmentQuery->whereHas('interfaces', function ($q) use ($vlan): void {
                $q->where('vlan_default', $vlan)
                    ->orWhereJsonContains('vlans_allowed', $vlan);
            });
        }

        $equipment = $equipmentQuery->get();
        $equipmentIds = $equipment->pluck('id')->all();

        $nodes = [];
        foreach ($equipment as $eq) {
            $type = $eq->type;
            $nodes[] = [
                'data' => [
                    'id' => 'eq-'.$eq->id,
                    'label' => $eq->name,
                    'type' => $type?->value,
                    'color' => $type?->color(),
                    'rackId' => $eq->rack_id,
                    'siteId' => $eq->rack?->room?->site_id,
                    'vendor' => $eq->vendor,
                    'model' => $eq->model,
                    'status' => $eq->status?->value,
                ],
            ];
        }

        $edges = [];
        if ($equipmentIds !== []) {
            $connections = Connection::query()
                ->with(['fromInterface', 'toInterface'])
                ->where('status', 'active')
                ->whereHas('fromInterface', function ($q) use ($equipmentIds): void {
                    $q->whereIn('equipment_id', $equipmentIds);
                })
                ->whereHas('toInterface', function ($q) use ($equipmentIds): void {
                    $q->whereIn('equipment_id', $equipmentIds);
                })
                ->get();

            foreach ($connections as $c) {
                $edges[] = [
                    'data' => [
                        'id' => 'cn-'.$c->id,
                        'source' => 'eq-'.$c->fromInterface?->equipment_id,
                        'target' => 'eq-'.$c->toInterface?->equipment_id,
                        'media' => $c->fromInterface?->media?->value,
                        'speed' => $c->fromInterface?->speed_mbps,
                        'cableType' => $c->cable_type,
                        'label' => $c->cable_label,
                        'status' => $c->status?->value,
                        'fromIface' => $c->fromInterface?->name,
                        'toIface' => $c->toInterface?->name,
                    ],
                ];
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
