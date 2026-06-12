<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EquipmentType;
use App\Enums\InterfaceType;
use App\Enums\InterfaceVlanMode;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Tenant;
use App\Models\VpnRemoteAccess;
use App\Models\VpnSiteToSite;
use App\Models\WifiNetwork;
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
        bool $hideWifi = false,
        bool $hideVpn = false,
        bool $groupByHypervisor = false,
    ): array {
        $equipmentQuery = Equipment::query()->with(['rack.room.site', 'room.site', 'host']);

        if (! $includeHidden) {
            $equipmentQuery->where('hidden_in_topology', false);
        }

        if ($hidePatchPanels) {
            // Patch panels and wall outlets become transit hops to be stitched
            // into synthetic edges below; both are physical pass-throughs.
            $equipmentQuery->whereNotIn('type', [
                EquipmentType::PatchPanel->value,
                EquipmentType::WallOutlet->value,
            ]);
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
            // An equipment "speaks" the VLAN when at least one of its
            // interfaces matches via explicit default, allowed list, or
            // transparent passthrough — OR when it's a patch panel / wall
            // outlet (their keystone ports are physical pass-throughs that
            // carry any tag).
            $equipmentQuery->where(function ($q) use ($vlan): void {
                $q->whereIn('type', [
                    EquipmentType::PatchPanel->value,
                    EquipmentType::WallOutlet->value,
                ])
                    ->orWhereHas('interfaces', function ($qq) use ($vlan): void {
                        $qq->where('vlan_default', $vlan)
                            ->orWhereJsonContains('vlans_allowed', $vlan)
                            ->orWhere('vlan_mode', InterfaceVlanMode::Transparent->value);
                    });
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
                'hostEquipmentId' => $eq->host_equipment_id,
                'siteId' => $eq->rack?->room?->site_id,
                'vendor' => $eq->vendor,
                'model' => $eq->model,
                'status' => $eq->status?->value,
                // True when the device carries hidden_in_topology=true and
                // is included only because includeHidden is on. The client
                // uses this to swap the context menu between "hide" and
                // "show" actions.
                'hidden' => (bool) $eq->hidden_in_topology,
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
                        $label = $eqSite->name;
                        if (! empty($eqSite->address)) {
                            $label .= "\n".$eqSite->address;
                        }
                        $siteParents[$siteKey] = [
                            'id' => $siteKey,
                            'label' => $label,
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

        // Hypervisor grouping: wrap each hypervisor + its VMs in a synthetic
        // `host-<eq_id>` compound. The compound inherits the hypervisor's
        // existing parent (rack/room/site) so it can nest correctly. VMs whose
        // host is out of scope still get a compound (labeled with the host's
        // name) but without a chain parent.
        if ($groupByHypervisor) {
            /** @var array<string, array<string, mixed>> $hostParents */
            $hostParents = [];
            $hypervisorById = [];
            foreach ($equipment as $eq) {
                if ($eq->type === EquipmentType::Hypervisor) {
                    $hypervisorById[$eq->getKey()] = $eq;
                }
            }
            foreach ($nodes as $idx => $node) {
                $data = $node['data'];
                if (! isset($data['type'])) {
                    continue;
                }
                if ($data['type'] === EquipmentType::Hypervisor->value) {
                    $hostKey = 'host-'.substr((string) $data['id'], 3);
                    // Always re-seed from the hypervisor pass: the hypervisor
                    // is the source of truth for both label and chain parent,
                    // and we may have created a placeholder earlier when a VM
                    // referencing this host was processed first.
                    $hostParents[$hostKey] = [
                        'id' => $hostKey,
                        'label' => $data['label'],
                        'kind' => 'host',
                    ];
                    if (isset($data['parent'])) {
                        $hostParents[$hostKey]['parent'] = $data['parent'];
                    }
                    $nodes[$idx]['data']['parent'] = $hostKey;
                } elseif ($data['type'] === EquipmentType::VirtualMachine->value && ! empty($data['hostEquipmentId'])) {
                    $hostId = (int) $data['hostEquipmentId'];
                    $hostKey = 'host-'.$hostId;
                    if (! isset($hostParents[$hostKey])) {
                        $host = $hypervisorById[$hostId] ?? Equipment::query()->find($hostId);
                        $hostParents[$hostKey] = [
                            'id' => $hostKey,
                            'label' => $host?->name ?? 'Hypervisor',
                            'kind' => 'host',
                        ];
                    }
                    $nodes[$idx]['data']['parent'] = $hostKey;
                }
            }
            foreach ($hostParents as $hp) {
                $nodes[] = ['data' => $hp];
            }
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
            $edges = $this->buildPassthroughEdges($equipmentIds, $vlan);
        } elseif ($equipmentIds !== []) {
            $connections = Connection::query()
                ->with(['fromInterface.equipment', 'toInterface.equipment'])
                ->where('status', 'active')
                ->whereHas('fromInterface', function ($q) use ($equipmentIds): void {
                    $q->whereIn('equipment_id', $equipmentIds);
                })
                ->whereHas('toInterface', function ($q) use ($equipmentIds): void {
                    $q->whereIn('equipment_id', $equipmentIds);
                })
                ->get();

            foreach ($connections as $c) {
                // Strict per-cable VLAN filter: emit only when BOTH
                // endpoints handle the requested VLAN. Without this guard,
                // the equipment-level filter above would still let a cable
                // through whose two physical ports don't carry the tag.
                if ($vlan !== null
                    && (! $this->interfaceHandlesVlan($c->fromInterface, $vlan)
                        || ! $this->interfaceHandlesVlan($c->toInterface, $vlan))) {
                    continue;
                }
                $edges[] = ['data' => $this->edgeData($c)];
            }
        }

        // Wi-Fi layer: synthetic SSID nodes + wireless edges to broadcasters
        // and associated clients. Lives alongside cable edges; never collapsed
        // by the passthrough logic.
        if (! $hideWifi) {
            $this->emitWifiLayer($nodes, $edges, $equipmentIds, $vlan, $siteId, $iconSize, $groupBySite, $siteParents);
        }

        // VPN layer: synthetic nodes (cloud+lock) for remote-access and
        // site-to-site tunnels, edges to the firewall(s) and clients.
        if (! $hideVpn) {
            $this->emitVpnLayer($nodes, $edges, $equipmentIds, $vlan, $iconSize);
        }

        // vNIC backing layer: synthetic dashed edges between each VM and its
        // hypervisor for every vNIC whose interfaces.backed_by_interface_id
        // points at a host pNIC. Multiple vNICs on the same pNIC produce
        // multiple edges with different labels — useful as documentation.
        $this->emitVirtualBackingEdges($edges, $equipmentIds);

        // Final pass: when a VLAN filter is active, drop nodes that
        // survived the equipment filter but ended up without any incident
        // edge (i.e. they "speak" the VLAN only via virtual sub-interfaces
        // that aren't actually cabled with a VLAN-compatible link).
        if ($vlan !== null) {
            $nodes = $this->pruneVlanOrphans($nodes, $edges);
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * Drop equipment nodes that have no incident edge in the filtered set,
     * then drop compound parents that no longer contain any visible child.
     * Compound nesting (site → room → rack) is pruned iteratively so empty
     * parents cascade upward.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     * @return list<array<string, mixed>>
     */
    private function pruneVlanOrphans(array $nodes, array $edges): array
    {
        $endpoints = [];
        foreach ($edges as $e) {
            $endpoints[$e['data']['source']] = true;
            $endpoints[$e['data']['target']] = true;
        }

        // 1) Keep only equipment nodes with at least one incident edge.
        $survivors = [];
        foreach ($nodes as $n) {
            $kind = $n['data']['kind'] ?? null;
            if (in_array($kind, ['rack', 'room', 'site'], true)) {
                // Compound parents handled in step 2.
                $survivors[] = $n;

                continue;
            }
            if (isset($endpoints[$n['data']['id']])) {
                $survivors[] = $n;
            }
        }

        // 2) Iteratively prune compound parents that no longer contain any
        //    child (equipment or nested compound).
        $changed = true;
        while ($changed) {
            $changed = false;
            $usedParents = [];
            foreach ($survivors as $n) {
                $p = $n['data']['parent'] ?? null;
                if ($p !== null) {
                    $usedParents[$p] = true;
                }
            }
            $survivors = array_values(array_filter($survivors, function ($n) use ($usedParents, &$changed) {
                $kind = $n['data']['kind'] ?? null;
                if (! in_array($kind, ['rack', 'room', 'site'], true)) {
                    return true;
                }
                $keep = isset($usedParents[$n['data']['id']]);
                if (! $keep) {
                    $changed = true;
                }

                return $keep;
            }));
        }

        return $survivors;
    }

    /**
     * Emit synthetic SSID nodes and wireless edges into $nodes / $edges by
     * reference. A Wi-Fi network appears only if at least one of its
     * broadcasters or clients survived the equipment-level filter; a VLAN
     * filter drops networks whose `vlan_id` does not match (NULL vlan
     * tolerates any filter, since the user simply didn't tag the network).
     *
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     * @param  list<int>  $equipmentIds
     */
    private function emitWifiLayer(array &$nodes, array &$edges, array $equipmentIds, ?int $vlan, ?int $siteId, int $iconSize, bool $groupBySite = false, array &$siteParents = []): void
    {
        $equipmentSet = array_flip($equipmentIds);
        $tenantId = TenantContext::id();

        $networks = WifiNetwork::query()
            ->with([
                'site',
                'broadcasters.equipment',
                'associations.clientInterface.equipment',
            ])
            ->get();

        foreach ($networks as $net) {
            if ($vlan !== null && $net->vlan_id !== null && (int) $net->vlan_id !== $vlan) {
                continue;
            }
            // Direct site association: if a wifi network declares its site
            // and the topology filter is on a different one, drop it. When
            // site_id is NULL we keep the legacy indirect filtering through
            // broadcaster equipment.
            if ($siteId !== null && $net->site_id !== null && (int) $net->site_id !== $siteId) {
                continue;
            }

            $broadcasterEdges = [];
            foreach ($net->broadcasters as $iface) {
                $eqId = (int) $iface->equipment_id;
                if (! isset($equipmentSet[$eqId])) {
                    continue;
                }
                $broadcasterEdges[] = [
                    'data' => [
                        'id' => 'wifi-bc-'.$net->id.'-'.$iface->id,
                        'source' => 'eq-'.$eqId,
                        'target' => 'wifi-'.$net->id,
                        'media' => 'wireless',
                        'cableType' => 'wireless',
                        'idealLength' => 120,
                        'fromIfaceId' => $iface->id,
                        'toIfaceId' => null,
                        'fromIface' => $iface->name,
                    ],
                ];
            }

            $associationEdges = [];
            foreach ($net->associations as $assoc) {
                $iface = $assoc->clientInterface;
                if ($iface === null) {
                    continue;
                }
                $eqId = (int) $iface->equipment_id;
                if (! isset($equipmentSet[$eqId])) {
                    continue;
                }
                $associationEdges[] = [
                    'data' => [
                        'id' => 'wifi-as-'.$assoc->id,
                        'source' => 'wifi-'.$net->id,
                        'target' => 'eq-'.$eqId,
                        'media' => 'wireless',
                        'cableType' => 'wireless',
                        'idealLength' => 120,
                        'fromIfaceId' => null,
                        'toIfaceId' => $iface->id,
                        'toIface' => $iface->name,
                    ],
                ];
            }

            if ($broadcasterEdges === [] && $associationEdges === []) {
                continue;
            }

            $wifiData = [
                'id' => 'wifi-'.$net->id,
                'label' => $net->ssid,
                'kind' => 'wifi',
                'type' => 'wifi_network',
                'wifiNetworkId' => $net->id,
                'siteId' => $net->site_id,
                'vlanId' => $net->vlan_id,
                'security' => $net->security_type,
                'icon' => $this->iconResolver->urlForKind('wifi_network', $tenantId),
                'iconSize' => $iconSize,
            ];

            // When the user groups by site and the Wi-Fi network declares
            // its site, nest the synthetic SSID node inside the matching
            // site compound. Lazily create the compound if no in-site
            // equipment had triggered it earlier in the equipment loop.
            if ($groupBySite && $net->site_id !== null) {
                $siteKey = 'site-'.$net->site_id;
                if (! isset($siteParents[$siteKey]) && $net->site !== null) {
                    $label = $net->site->name;
                    if (! empty($net->site->address)) {
                        $label .= "\n".$net->site->address;
                    }
                    $siteParents[$siteKey] = [
                        'id' => $siteKey,
                        'label' => $label,
                        'kind' => 'site',
                        'siteId' => (int) $net->site_id,
                    ];
                    $nodes[] = ['data' => $siteParents[$siteKey]];
                }
                if (isset($siteParents[$siteKey])) {
                    $wifiData['parent'] = $siteKey;
                }
            }

            $nodes[] = ['data' => $wifiData];
            foreach ($broadcasterEdges as $e) {
                $edges[] = $e;
            }
            foreach ($associationEdges as $e) {
                $edges[] = $e;
            }
        }
    }

    /**
     * Emit synthetic VPN nodes (remote-access + site-to-site) into $nodes /
     * $edges by reference. Each VPN node is a `kind='vpn'` cloud-with-lock
     * tile rendered between its firewall(s) and clients. The VLAN filter
     * drops VPNs whose `routed_vlans` (or sides A/B for site-to-site) do
     * not intersect the filter — but null/empty arrays mean "unspecified"
     * and tolerate any filter, like the Wi-Fi behaviour.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     * @param  list<int>  $equipmentIds
     */
    private function emitVpnLayer(array &$nodes, array &$edges, array $equipmentIds, ?int $vlan, int $iconSize): void
    {
        $equipmentSet = array_flip($equipmentIds);
        $tenantId = TenantContext::id();

        // -- Remote access (client-to-LAN) --------------------------------
        $remotes = VpnRemoteAccess::query()
            ->with([
                'firewallInterface.equipment',
                'clients.clientInterface.equipment',
            ])
            ->get();

        foreach ($remotes as $vpn) {
            if ($vlan !== null && is_array($vpn->routed_vlans) && $vpn->routed_vlans !== []
                && ! in_array($vlan, array_map('intval', $vpn->routed_vlans), true)) {
                continue;
            }

            $fwIface = $vpn->firewallInterface;
            $fwEqId = (int) ($fwIface?->equipment_id ?? 0);
            $hasFw = $fwEqId > 0 && isset($equipmentSet[$fwEqId]);

            $clientEdges = [];
            foreach ($vpn->clients as $c) {
                $iface = $c->clientInterface;
                if ($iface === null) {
                    continue;
                }
                $eqId = (int) $iface->equipment_id;
                if (! isset($equipmentSet[$eqId])) {
                    continue;
                }
                $clientEdges[] = [
                    'data' => [
                        'id' => 'vpn-ra-cli-'.$c->id,
                        'source' => 'vpn-ra-'.$vpn->id,
                        'target' => 'eq-'.$eqId,
                        'media' => 'virtual',
                        'cableType' => 'vpn',
                        'idealLength' => 140,
                        'fromIfaceId' => null,
                        'toIfaceId' => $iface->id,
                        'toIface' => $iface->name,
                    ],
                ];
            }

            if (! $hasFw && $clientEdges === []) {
                continue;
            }

            $nodes[] = [
                'data' => [
                    'id' => 'vpn-ra-'.$vpn->id,
                    'label' => $vpn->name,
                    'name' => $vpn->name,
                    'kind' => 'vpn',
                    'vpnKind' => 'remote',
                    'type' => 'vpn_remote_access',
                    'vpnId' => $vpn->id,
                    'protocol' => $vpn->protocol?->value,
                    'routingMode' => $vpn->routing_mode?->value,
                    'networkCidr' => $vpn->client_network_cidr,
                    'routedVlans' => is_array($vpn->routed_vlans) ? array_values($vpn->routed_vlans) : [],
                    'icon' => $this->iconResolver->urlForKind('vpn_remote_access', $tenantId),
                    'iconSize' => $iconSize,
                ],
            ];

            if ($hasFw) {
                $edges[] = [
                    'data' => [
                        'id' => 'vpn-ra-fw-'.$vpn->id,
                        'source' => 'eq-'.$fwEqId,
                        'target' => 'vpn-ra-'.$vpn->id,
                        'media' => 'virtual',
                        'cableType' => 'vpn',
                        'idealLength' => 140,
                        'fromIfaceId' => $fwIface->id,
                        'toIfaceId' => null,
                        'fromIface' => $fwIface->name,
                    ],
                ];
            }
            foreach ($clientEdges as $e) {
                $edges[] = $e;
            }
        }

        // -- Site-to-site -------------------------------------------------
        $tunnels = VpnSiteToSite::query()
            ->with([
                'endpointAInterface.equipment',
                'endpointBInterface.equipment',
            ])
            ->get();

        foreach ($tunnels as $vpn) {
            if ($vlan !== null) {
                $a = is_array($vpn->routed_vlans_a) ? array_map('intval', $vpn->routed_vlans_a) : [];
                $b = is_array($vpn->routed_vlans_b) ? array_map('intval', $vpn->routed_vlans_b) : [];
                if (($a !== [] || $b !== []) && ! in_array($vlan, $a, true) && ! in_array($vlan, $b, true)) {
                    continue;
                }
            }

            $ifaceA = $vpn->endpointAInterface;
            $ifaceB = $vpn->endpointBInterface;
            $eqAId = (int) ($ifaceA?->equipment_id ?? 0);
            $eqBId = (int) ($ifaceB?->equipment_id ?? 0);
            if (! isset($equipmentSet[$eqAId]) || ! isset($equipmentSet[$eqBId])) {
                continue;
            }

            $nodes[] = [
                'data' => [
                    'id' => 'vpn-stos-'.$vpn->id,
                    'label' => $vpn->name,
                    'name' => $vpn->name,
                    'kind' => 'vpn',
                    'vpnKind' => 'site',
                    'type' => 'vpn_site_to_site',
                    'vpnId' => $vpn->id,
                    'protocol' => $vpn->protocol?->value,
                    'routedVlansA' => $vpn->routed_vlans_a ?: [],
                    'routedVlansB' => $vpn->routed_vlans_b ?: [],
                    'routedNetworksA' => is_array($vpn->routed_networks_a) ? array_values($vpn->routed_networks_a) : [],
                    'routedNetworksB' => is_array($vpn->routed_networks_b) ? array_values($vpn->routed_networks_b) : [],
                    'icon' => $this->iconResolver->urlForKind('vpn_site_to_site', $tenantId),
                    'iconSize' => $iconSize,
                ],
            ];

            $edges[] = [
                'data' => [
                    'id' => 'vpn-stos-a-'.$vpn->id,
                    'source' => 'eq-'.$eqAId,
                    'target' => 'vpn-stos-'.$vpn->id,
                    'media' => 'virtual',
                    'cableType' => 'vpn',
                    'idealLength' => 140,
                    'fromIfaceId' => $ifaceA->id,
                    'toIfaceId' => null,
                    'fromIface' => $ifaceA->name,
                    // "A" / "B" sit at the vpn-node end of each edge so
                    // the lettering identifies which firewall is which
                    // *as you read the tunnel* rather than crowding the
                    // firewall icon itself.
                    'toIface' => 'A',
                ],
            ];
            $edges[] = [
                'data' => [
                    'id' => 'vpn-stos-b-'.$vpn->id,
                    'source' => 'vpn-stos-'.$vpn->id,
                    'target' => 'eq-'.$eqBId,
                    'media' => 'virtual',
                    'cableType' => 'vpn',
                    'idealLength' => 140,
                    'fromIfaceId' => null,
                    'toIfaceId' => $ifaceB->id,
                    'fromIface' => 'B',
                    'toIface' => $ifaceB->name,
                ],
            ];
        }
    }

    /**
     * Build the edge payload for a single concrete Connection (no collapse).
     *
     * @return array<string, mixed>
     */
    /**
     * Emit dashed "vNIC backing" edges between each VM and its hypervisor:
     * for every interface with backed_by_interface_id valued, where both the
     * VM and the host hypervisor are part of the currently visible equipment
     * set. The edge carries the pNIC name as label so the user can read at a
     * glance which physical NIC carries the vNIC's traffic.
     *
     * @param  list<array<string, mixed>>  $edges
     * @param  list<int>  $equipmentIds
     */
    private function emitVirtualBackingEdges(array &$edges, array $equipmentIds): void
    {
        if ($equipmentIds === []) {
            return;
        }

        $visible = array_flip($equipmentIds);

        $vnics = NetworkInterface::query()
            ->with(['equipment', 'backedBy.equipment'])
            ->whereNotNull('backed_by_interface_id')
            ->whereIn('equipment_id', $equipmentIds)
            ->get();

        foreach ($vnics as $vnic) {
            $vmEqId = $vnic->equipment_id;
            $pnic = $vnic->backedBy;
            if ($pnic === null) {
                continue;
            }
            $hostEqId = $pnic->equipment_id;
            if (! isset($visible[$hostEqId])) {
                continue;
            }
            $edges[] = ['data' => [
                'id' => 'vnic-'.$vnic->id,
                'source' => 'eq-'.$vmEqId,
                'target' => 'eq-'.$hostEqId,
                'kind' => 'vnic',
                'label' => $pnic->name,
                'vnicName' => $vnic->name,
                'pnicName' => $pnic->name,
            ]];
        }
    }

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
            'fromIfaceId' => $c->from_interface_id,
            'toIfaceId' => $c->to_interface_id,
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
     * @param  int|null  $vlan  if set, only collapse chains whose two outer terminals handle this VLAN
     * @return list<array<string, mixed>>
     */
    private function buildPassthroughEdges(array $visibleEquipmentIds, ?int $vlan = null): array
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

            // Strict per-cable VLAN filter on the resolved outer terminals
            // (the transit hops inside patch panels always handle every
            // VLAN by definition, so we only check the two endpoints).
            if ($vlan !== null
                && (! $this->interfaceHandlesVlan($termA, $vlan)
                    || ! $this->interfaceHandlesVlan($termB, $vlan))) {
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
                'fromIfaceId' => $termA?->id,
                'toIfaceId' => $termB?->id,
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
        if ($eq === null || ! in_array($eq->type, [EquipmentType::PatchPanel, EquipmentType::WallOutlet], true)) {
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

    /**
     * True when the given interface "handles" the requested VLAN. Used by
     * the strict per-cable VLAN filter. The rules, in order:
     *  - keystone interfaces on patch panel / wall outlet equipment are
     *    physical pass-throughs ⇒ any VLAN passes;
     *  - vlan_mode = transparent (unmanaged ports) ⇒ any tag passes;
     *  - vlan_default equals the requested VLAN;
     *  - vlans_allowed contains the requested VLAN.
     */
    private function interfaceHandlesVlan(?NetworkInterface $if, int $vlan): bool
    {
        if ($if === null) {
            return false;
        }

        $eqType = $if->equipment?->type;
        if ($if->type === InterfaceType::Keystone
            && in_array($eqType, [EquipmentType::PatchPanel, EquipmentType::WallOutlet], true)) {
            return true;
        }

        if ($if->vlan_mode === InterfaceVlanMode::Transparent) {
            return true;
        }

        if ($if->vlan_default === $vlan) {
            return true;
        }

        $allowed = $if->vlans_allowed ?? [];
        if (is_array($allowed) && in_array($vlan, $allowed, true)) {
            return true;
        }

        return false;
    }
}
