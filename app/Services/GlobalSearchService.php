<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Site;
use Illuminate\Support\Collection;

/**
 * Cross-entity search within the active tenant (global scopes apply):
 * equipment by name/serial/asset tag, interfaces by name/IP/MAC, sites by
 * name, cables by label. Shared by the top-bar search and the MCP server.
 */
class GlobalSearchService
{
    public const MIN_CHARS = 2;

    /**
     * @return array{equipment: Collection<int, array<string, mixed>>, interfaces: Collection<int, array<string, mixed>>, sites: Collection<int, array<string, mixed>>, connections: Collection<int, array<string, mixed>>}
     */
    public function search(string $query, int $perGroup = 5): array
    {
        $q = trim($query);

        $groups = [
            'equipment' => collect(),
            'interfaces' => collect(),
            'sites' => collect(),
            'connections' => collect(),
        ];

        if (mb_strlen($q) < self::MIN_CHARS) {
            return $groups;
        }

        $needle = '%'.$q.'%';

        $groups['equipment'] = Equipment::query()
            ->where(function ($qq) use ($needle): void {
                $qq->where('name', 'ilike', $needle)
                    ->orWhere('serial', 'ilike', $needle)
                    ->orWhere('asset_tag', 'ilike', $needle);
            })
            ->orderBy('name')
            ->limit($perGroup)
            ->get(['id', 'name', 'type', 'vendor', 'model', 'serial'])
            ->map(fn ($e): array => [
                'id' => $e->id,
                'label' => $e->name,
                'type' => $e->type?->value,
                'meta' => trim(($e->vendor ?? '').' '.($e->model ?? '').($e->serial ? ' · '.$e->serial : '')),
                'url' => route('equipment.show', $e),
            ]);

        $groups['interfaces'] = NetworkInterface::query()
            ->with('equipment:id,name')
            ->where(function ($qq) use ($needle): void {
                $qq->where('name', 'ilike', $needle)
                    ->orWhere('ip_address', 'ilike', $needle)
                    ->orWhere('mac_address', 'ilike', $needle);
            })
            ->orderBy('name')
            ->limit($perGroup)
            ->get(['id', 'name', 'equipment_id', 'ip_address', 'mac_address'])
            ->map(fn ($i): array => [
                'id' => $i->id,
                'label' => $i->name,
                'equipment_id' => $i->equipment_id,
                'equipment' => $i->equipment?->name,
                'ip_address' => $i->ip_address,
                'mac_address' => $i->mac_address,
                'meta' => ($i->equipment?->name ?: '?').($i->ip_address ? ' · '.$i->ip_address : ''),
                'url' => $i->equipment ? route('equipment.show', $i->equipment) : '#',
            ]);

        $groups['sites'] = Site::query()
            ->where('name', 'ilike', $needle)
            ->orderBy('name')
            ->limit($perGroup)
            ->get(['id', 'name', 'address'])
            ->map(fn ($s): array => [
                'id' => $s->id,
                'label' => $s->name,
                'meta' => $s->address ?? '',
                'url' => route('sites.show', $s),
            ]);

        $groups['connections'] = Connection::query()
            ->where('cable_label', 'ilike', $needle)
            ->orderBy('cable_label')
            ->limit($perGroup)
            ->get(['id', 'cable_label', 'cable_type', 'from_interface_id', 'to_interface_id'])
            ->map(fn ($c): array => [
                'id' => $c->id,
                'label' => $c->cable_label ?? __('search.unlabeled'),
                'from_interface_id' => $c->from_interface_id,
                'to_interface_id' => $c->to_interface_id,
                'meta' => $c->cable_type,
                'url' => route('connections.index'),
            ]);

        return $groups;
    }
}
