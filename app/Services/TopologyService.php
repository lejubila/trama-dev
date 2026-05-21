<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Connection;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Services\Icons\IconResolver;
use App\Support\Tenancy\TenantContext;

class TopologyService
{
    public const DEFAULT_ICON_SIZE_PX = 44;

    public const MIN_ICON_SIZE_PX = 16;

    public const MAX_ICON_SIZE_PX = 200;

    public function __construct(private readonly IconResolver $iconResolver) {}

    /**
     * Resolves the per-tenant global icon size for the topology view,
     * stored in `tenants.settings['topology_icon_size_px']`.
     */
    public function tenantIconSizePx(?int $tenantId): int
    {
        if ($tenantId === null) {
            return self::DEFAULT_ICON_SIZE_PX;
        }

        $settings = Tenant::query()->where('id', $tenantId)->value('settings');
        $size = is_array($settings) ? ($settings['topology_icon_size_px'] ?? null) : null;

        return is_int($size) && $size > 0 ? $size : self::DEFAULT_ICON_SIZE_PX;
    }

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
        bool $includeHidden = false,
        bool $groupByRack = false,
        ?int $roomId = null,
        bool $groupBySite = false,
        bool $groupByRoom = false,
    ): array {
        $equipmentQuery = Equipment::query()->with(['rack.room.site', 'room.site']);

        if (! $includeHidden) {
            $equipmentQuery->where('hidden_in_topology', false);
        }

        if ($siteId !== null) {
            // Cover both racked (via rack.room.site_id) and unracked (via
            // direct equipment.room.site_id) equipment so unracked devices
            // tagged with a room of the selected site still appear.
            $equipmentQuery->where(function ($q) use ($siteId): void {
                $q->whereHas('rack.room', fn ($qq) => $qq->where('site_id', $siteId))
                    ->orWhereHas('room', fn ($qq) => $qq->where('site_id', $siteId));
            });
        }

        if ($roomId !== null) {
            // Matches racked equipment via rack.room_id AND unracked
            // equipment via equipment.room_id.
            $equipmentQuery->where(function ($q) use ($roomId): void {
                $q->where('room_id', $roomId)
                    ->orWhereHas('rack', fn ($qq) => $qq->where('room_id', $roomId));
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

        $tenantId = TenantContext::id();
        $iconSize = $this->tenantIconSizePx($tenantId);

        $nodes = [];
        /** @var array<string, array<string, mixed>> $rackParents */
        $rackParents = [];
        /** @var array<string, array<string, mixed>> $roomParents */
        $roomParents = [];
        /** @var array<string, array<string, mixed>> $siteParents */
        $siteParents = [];

        foreach ($equipment as $eq) {
            $type = $eq->type;
            $data = [
                'id' => 'eq-'.$eq->id,
                'label' => $eq->name,
                'type' => $type?->value,
                'color' => $type?->color(),
                'rackId' => $eq->rack_id,
                'siteId' => $eq->rack?->room?->site_id,
                'vendor' => $eq->vendor,
                'model' => $eq->model,
                'status' => $eq->status?->value,
            ];

            // Resolve the site this device belongs to (racked → via rack.room;
            // unracked → via equipment.room). May be null if the device has
            // neither rack nor room.
            $eqSite = $eq->rack?->room?->site ?? $eq->room?->site;
            $eqSiteId = $eqSite?->getKey();

            // Compound grouping. Build the nesting chain from outermost to
            // innermost among the active group-by flags that actually apply
            // to this device: site → room → rack → equipment. Each active
            // level becomes a compound parent; the device hangs off the
            // innermost one, and every compound nests inside the one above it.
            $rack = $eq->rack;
            // A device's room is its rack's room (racked) or its own room
            // (unracked); may be null when the device has neither.
            $room = $eq->rack?->room ?? $eq->room;
            $rackKey = $rack !== null ? 'rack-'.$eq->rack_id : null;
            $roomKey = $room !== null ? 'room-'.$room->getKey() : null;
            $siteKey = $eqSiteId !== null ? 'site-'.$eqSiteId : null;

            $useSite = $groupBySite && $siteKey !== null;
            $useRoom = $groupByRoom && $roomKey !== null;
            $useRack = $groupByRack && $rackKey !== null;

            // Preserve legacy behaviour: in group-by-rack mode an unracked
            // device with a known room still nests in its room compound even
            // when group-by-room is off.
            if ($groupByRack && ! $useRack && $roomKey !== null) {
                $useRoom = true;
            }

            $chain = [];
            if ($useSite) {
                $chain[] = ['key' => $siteKey, 'kind' => 'site'];
            }
            if ($useRoom) {
                $chain[] = ['key' => $roomKey, 'kind' => 'room'];
            }
            if ($useRack) {
                $chain[] = ['key' => $rackKey, 'kind' => 'rack'];
            }

            if ($chain !== []) {
                $data['parent'] = $chain[count($chain) - 1]['key'];
            }

            foreach ($chain as $i => $level) {
                $parentKey = $i > 0 ? $chain[$i - 1]['key'] : null;

                if ($level['kind'] === 'site') {
                    if (! isset($siteParents[$siteKey])) {
                        $siteParents[$siteKey] = [
                            'id' => $siteKey,
                            'label' => $eqSite->name,
                            'kind' => 'site',
                            'siteId' => $eqSiteId,
                        ];
                    }
                } elseif ($level['kind'] === 'room') {
                    if (! isset($roomParents[$roomKey])) {
                        $label = $room->name;
                        if (! $useSite && $eqSite !== null) {
                            $label .= ' / '.$eqSite->name;
                        }
                        // When grouping by rack but not by room, a room
                        // compound only ever collects the unracked devices.
                        if ($groupByRack && ! $groupByRoom) {
                            $label .= ' · non in rack';
                        }
                        $roomParents[$roomKey] = [
                            'id' => $roomKey,
                            'label' => $label,
                            'kind' => 'room',
                            'roomId' => $room->getKey(),
                        ];
                        if ($parentKey !== null) {
                            $roomParents[$roomKey]['parent'] = $parentKey;
                        }
                    }
                } elseif ($level['kind'] === 'rack') {
                    if (! isset($rackParents[$rackKey])) {
                        $label = $rack->name;
                        if (! $useRoom && $room !== null) {
                            $label .= ' — '.$room->name;
                        }
                        if (! $useSite && $eqSite !== null) {
                            $label .= ' / '.$eqSite->name;
                        }
                        $rackParents[$rackKey] = [
                            'id' => $rackKey,
                            'label' => $label,
                            'kind' => 'rack',
                            'rackId' => $eq->rack_id,
                        ];
                        if ($parentKey !== null) {
                            $rackParents[$rackKey]['parent'] = $parentKey;
                        }
                    }
                }
            }

            $iconUrl = $this->iconResolver->urlForEquipment($eq, $tenantId);
            if ($iconUrl !== null) {
                $data['icon'] = $iconUrl;
            }

            // Single global size per tenant; per-equipment icon_size_px is
            // no longer consulted here (the per-node resize slider is gone).
            $data['iconSize'] = $iconSize;

            $nodes[] = ['data' => $data];
        }

        // Emit compound parents (grandparent → parent order) only when at
        // least one child is visible (the maps are populated lazily above).
        foreach ($siteParents as $sp) {
            $nodes[] = ['data' => $sp];
        }
        foreach ($roomParents as $rp) {
            $nodes[] = ['data' => $rp];
        }
        foreach ($rackParents as $rp) {
            $nodes[] = ['data' => $rp];
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
                $fromName = (string) ($c->fromInterface?->name ?? '');
                $toName = (string) ($c->toInterface?->name ?? '');
                $label = (string) ($c->cable_label ?? '');

                // Make the edge long enough to display source port name +
                // center label + target port name without overlap. ~6.5 px
                // per character at the chosen font size, with margins.
                $idealLength = (int) max(
                    80,
                    6.5 * (strlen($fromName) + strlen($label) + strlen($toName)) + 60,
                );

                $data = [
                    'id' => 'cn-'.$c->id,
                    'source' => 'eq-'.$c->fromInterface?->equipment_id,
                    'target' => 'eq-'.$c->toInterface?->equipment_id,
                    'media' => $c->fromInterface?->media?->value,
                    'speed' => $c->fromInterface?->speed_mbps,
                    'cableType' => $c->cable_type,
                    'color' => $c->color, // hex or null; JS falls back to media color
                    'status' => $c->status?->value,
                    'idealLength' => $idealLength,
                ];
                // Omit label/fromIface/toIface when empty so the Cytoscape
                // attribute selectors (edge[label], edge[fromIface], edge[toIface])
                // don't match and we avoid "no mapping for property" warnings.
                if ($label !== '') {
                    $data['label'] = $label;
                }
                if ($fromName !== '') {
                    $data['fromIface'] = $fromName;
                }
                if ($toName !== '') {
                    $data['toIface'] = $toName;
                }

                $edges[] = ['data' => $data];
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
