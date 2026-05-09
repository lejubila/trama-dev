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
     * Optionally narrowed to a single site (Equipment is filtered to those
     * mounted in racks → rooms → site_id). Returns active connections only.
     *
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function buildGraph(?int $siteId = null): array
    {
        $equipmentQuery = Equipment::query();

        if ($siteId !== null) {
            $equipmentQuery->whereHas('rack.room', function ($q) use ($siteId): void {
                $q->where('site_id', $siteId);
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
                    'rack_id' => $eq->rack_id,
                    'vendor' => $eq->vendor,
                    'model' => $eq->model,
                ],
            ];
        }

        $edges = [];
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
                    'cable_type' => $c->cable_type,
                    'label' => $c->cable_label,
                ],
            ];
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
