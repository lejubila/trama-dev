<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EquipmentType;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
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
        ?array $tagIds = null,
        bool $hidePatchPanels = false,
    ): array {
        $equipmentQuery = Equipment::query()->with(['rack.room.site', 'room.site']);

        if (! $includeHidden) {
            $equipmentQuery->where('hidden_in_topology', false);
        }

        if ($hidePatchPanels) {
            // Patch panels become transit hops to be stitched into synthetic
            // edges below; wall outlets stay because they're terminals.
            $equipmentQuery->where('type', '!=', EquipmentType::PatchPanel->value);
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

        if ($tagIds !== null && $tagIds !== []) {
            // whereIn within a single whereHas ⇒ OR semantics across tags:
            // a device matches if it carries at least one of the selected tags.
            $equipmentQuery->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
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
        if ($hidePatchPanels) {
            $edges = $this->buildPassthroughEdges($equipmentIds);
        } elseif ($equipmentIds !== []) {
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
                $edges[] = ['data' => $this->edgeData($c)];
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * Build the edge payload for a single concrete Connection (no collapse).
     *
     * @return array<string, mixed>
     */
    private function edgeData(Connection $c): array
    {
        $fromName = (string) ($c->fromInterface?->name ?? '');
        $toName = (string) ($c->toInterface?->name ?? '');
        $label = (string) ($c->cable_label ?? '');

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
            'color' => $c->color,
            'status' => $c->status?->value,
            'idealLength' => $idealLength,
        ];
        if ($label !== '') {
            $data['label'] = $label;
        }
        if ($fromName !== '') {
            $data['fromIface'] = $fromName;
        }
        if ($toName !== '') {
            $data['toIface'] = $toName;
        }

        return $data;
    }

    /**
     * Compute the collapsed edge list when patch panels are hidden.
     *
     * For every active connection we resolve each endpoint through chains of
     * keystone pairs on patch panels until we hit a non-patch-panel terminal.
     * A synthetic edge between the two terminals is emitted at most once per
     * resolved pair, labelled with the transit ports (e.g. "via PP1.P3").
     *
     * @param  list<int>  $visibleEquipmentIds  ids of equipment still in the node payload
     * @return list<array<string, mixed>>
     */
    private function buildPassthroughEdges(array $visibleEquipmentIds): array
    {
        $visibleSet = array_flip($visibleEquipmentIds);

        $connections = Connection::query()
            ->with(['fromInterface.equipment', 'toInterface.equipment', 'fromInterface.paired', 'toInterface.paired'])
            ->where('status', 'active')
            ->get();

        /** @var array<int, Connection> $byInterface */
        $byInterface = [];
        foreach ($connections as $c) {
            $byInterface[$c->from_interface_id] = $c;
            $byInterface[$c->to_interface_id] = $c;
        }

        /** @var array<int, true> $consumed connection ids already emitted */
        $consumed = [];
        /** @var array<string, true> $emittedPairs key "min-max" of resolved equipment ids */
        $emittedPairs = [];

        $edges = [];

        foreach ($connections as $c) {
            if (isset($consumed[$c->id])) {
                continue;
            }

            $visited = [];
            $transitA = [];
            $transitB = [];

            $termA = $this->resolveTerminal($c->fromInterface, $byInterface, $visited, $transitA);
            $termB = $this->resolveTerminal($c->toInterface, $byInterface, $visited, $transitB);

            // Consume every connection touched by this chain so we don't
            // re-emit the same passthrough from a different starting hop.
            foreach (array_keys($visited) as $ifId) {
                $touched = $byInterface[$ifId] ?? null;
                if ($touched !== null) {
                    $consumed[$touched->id] = true;
                }
            }
            $consumed[$c->id] = true;

            if ($termA === null || $termB === null) {
                continue;
            }

            $eqA = $termA->equipment_id;
            $eqB = $termB->equipment_id;
            if ($eqA === $eqB) {
                continue;
            }
            if (! isset($visibleSet[$eqA]) || ! isset($visibleSet[$eqB])) {
                continue;
            }

            $key = min($eqA, $eqB).'-'.max($eqA, $eqB);
            if (isset($emittedPairs[$key])) {
                continue;
            }
            $emittedPairs[$key] = true;

            $isPassthrough = $transitA !== [] || $transitB !== [];
            $transit = array_merge(array_reverse($transitA), $transitB);
            $viaLabel = $isPassthrough ? 'via '.implode(' · ', $transit) : (string) ($c->cable_label ?? '');

            $fromName = (string) ($termA->name ?? '');
            $toName = (string) ($termB->name ?? '');
            $idealLength = (int) max(
                80,
                6.5 * (strlen($fromName) + strlen($viaLabel) + strlen($toName)) + 60,
            );

            $data = [
                'id' => ($isPassthrough ? 'cn-pt-' : 'cn-').$c->id,
                'source' => 'eq-'.$eqA,
                'target' => 'eq-'.$eqB,
                'media' => $c->fromInterface?->media?->value,
                'speed' => $c->fromInterface?->speed_mbps,
                'cableType' => $c->cable_type,
                'color' => $c->color,
                'status' => $c->status?->value,
                'idealLength' => $idealLength,
            ];
            if ($viaLabel !== '') {
                $data['label'] = $viaLabel;
            }
            if ($fromName !== '') {
                $data['fromIface'] = $fromName;
            }
            if ($toName !== '') {
                $data['toIface'] = $toName;
            }
            if ($isPassthrough) {
                $data['passthrough'] = true;
                $data['transit'] = $transit;
            }

            $edges[] = ['data' => $data];
        }

        return $edges;
    }

    /**
     * Walk forward from an interface until a non-patch-panel terminal is
     * reached, recording each transit port along the way. Returns null if
     * the chain breaks (paired interface missing, no connection on the
     * paired side, or a loop is detected).
     *
     * @param  array<int, Connection>  $byInterface
     * @param  array<int, true>  $visited
     * @param  list<string>  $transit
     */
    private function resolveTerminal(
        ?NetworkInterface $iface,
        array $byInterface,
        array &$visited,
        array &$transit,
    ): ?NetworkInterface {
        if ($iface === null) {
            return null;
        }
        if (isset($visited[$iface->id])) {
            return null; // loop guard
        }
        $visited[$iface->id] = true;

        $eq = $iface->equipment;
        if ($eq === null || $eq->type !== EquipmentType::PatchPanel) {
            return $iface;
        }

        // Transit hop: record "PPname.portname" and keep walking through the
        // sibling (paired) interface on the opposite side.
        $transit[] = ($eq->name ?? '?').'.'.($iface->name ?? '?');

        $paired = $iface->paired;
        if ($paired === null || isset($visited[$paired->id])) {
            return null;
        }
        $visited[$paired->id] = true;

        $next = $byInterface[$paired->id] ?? null;
        if ($next === null) {
            return null;
        }

        $other = $next->from_interface_id === $paired->id
            ? $next->toInterface
            : $next->fromInterface;

        return $this->resolveTerminal($other, $byInterface, $visited, $transit);
    }
}
