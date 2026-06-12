<?php

declare(strict_types=1);

namespace App\Livewire\Topology;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\DeviceIcon;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TopologySnapshot;
use App\Services\TopologyService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Graph extends Component
{
    #[Url(except: 0)]
    public int $siteId = 0;

    #[Url(except: 0)]
    public int $snapshotPreset = 0;

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: 0)]
    public ?int $vlanFilter = 0;

    /**
     * Selected tag ids; a device shows if it has at least one (OR).
     *
     * @var list<int|string>
     */
    #[Url(except: [])]
    public array $tagFilters = [];

    #[Url(except: 'cose-bilkent')]
    public string $layout = 'cose-bilkent';

    /**
     * Current type filter as a list of EquipmentType values.
     *
     * @var list<string>
     */
    #[Url(except: [])]
    public array $filterTypes = [];

    #[Url(except: false)]
    public bool $includeHidden = false;

    #[Url(except: false)]
    public bool $groupByRack = false;

    #[Url(except: false)]
    public bool $groupBySite = false;

    #[Url(except: false)]
    public bool $groupByRoom = false;

    #[Url(except: false)]
    public bool $groupByHypervisor = false;

    #[Url(except: 0)]
    public int $roomFilter = 0;

    #[Url(except: false)]
    public bool $hidePatchPanels = false;

    #[Url(except: false)]
    public bool $hideWifi = false;

    #[Url(except: false)]
    public bool $hideVpn = false;

    public function toggleType(string $type): void
    {
        $allTypes = array_map(
            fn (EquipmentType $t) => $t->value,
            EquipmentType::cases(),
        );

        // Empty filterTypes is the "all selected" default. Clicking a type
        // in that state means "deselect just this one" → populate the array
        // with every other type.
        if ($this->filterTypes === []) {
            $this->filterTypes = array_values(array_filter(
                $allTypes,
                fn (string $t): bool => $t !== $type,
            ));

            return;
        }

        if (in_array($type, $this->filterTypes, true)) {
            $this->filterTypes = array_values(array_filter(
                $this->filterTypes,
                fn (string $t): bool => $t !== $type,
            ));
        } else {
            $this->filterTypes[] = $type;
        }

        // Re-checking the last missing type makes the selection cover the
        // full enum: normalize back to [] so the URL stays clean and the
        // dropdown trigger badge disappears.
        if (count(array_unique($this->filterTypes)) === count($allTypes)) {
            $this->filterTypes = [];
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['siteId', 'statusFilter', 'vlanFilter', 'tagFilters', 'filterTypes', 'includeHidden', 'groupByRack', 'groupBySite', 'groupByRoom', 'groupByHypervisor', 'roomFilter', 'hidePatchPanels', 'hideWifi', 'hideVpn']);
    }

    /**
     * Coerce an empty / null VLAN input into 0 ("no filter") so clearing
     * the number field via Backspace doesn't trip the property's type
     * (number inputs send "" when emptied, which crashes a strict `int`
     * property — see PropertyNotFoundException reported in topology.graph).
     */
    public function updatingVlanFilter(mixed &$value): void
    {
        $value = ($value === '' || $value === null) ? 0 : (int) $value;
    }

    /**
     * If the user picks a Sede that doesn't contain the currently-selected
     * Locale, drop the Locale filter so the dropdown never shows a stale
     * "ghost" option.
     */
    public function updatedSiteId(): void
    {
        if ($this->roomFilter > 0 && $this->siteId > 0) {
            $valid = Room::query()
                ->where('id', $this->roomFilter)
                ->where('site_id', $this->siteId)
                ->exists();
            if (! $valid) {
                $this->roomFilter = 0;
            }
        }
    }

    /**
     * Mark an equipment as hidden in the topology view.
     *
     * Mirror of the toggle in Equipment\Index but exposed here so the
     * topology context menu can "Nascondi → Sempre" without leaving the
     * page. The DB flag is the same `hidden_in_topology` already filtered
     * by TopologyService::buildGraph().
     */
    public function hideAlways(int $equipmentId): void
    {
        $eq = Equipment::query()->findOrFail($equipmentId);
        $this->authorize('update', $eq);
        $eq->update(['hidden_in_topology' => true]);
        $this->dispatch('toast', type: 'success', message: 'Dispositivo nascosto nella topologia.');
    }

    /** Counterpart of hideAlways: drops the persistent hidden flag. */
    public function showAlways(int $equipmentId): void
    {
        $eq = Equipment::query()->findOrFail($equipmentId);
        $this->authorize('update', $eq);
        $eq->update(['hidden_in_topology' => false]);
        $this->dispatch('toast', type: 'success', message: 'Dispositivo visibile nella topologia.');
    }

    /**
     * Lazy-fetch the interfaces of one equipment, used by the topology
     * context menu to populate the "Porte" submenu with the attributes
     * the user may toggle on the cable junctions.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchInterfaces(int $equipmentId): array
    {
        /** @var Equipment $eq */
        $eq = Equipment::query()->findOrFail($equipmentId);
        $this->authorize('view', $eq);

        return NetworkInterface::query()
            ->where('equipment_id', $eq->getKey())
            ->orderBy('index')
            ->orderBy('name')
            ->get(['id', 'name', 'ip_address', 'mac_address', 'vlan_mode', 'vlan_default', 'vlans_allowed', 'description'])
            ->map(fn (NetworkInterface $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'ip_address' => $i->ip_address,
                'mac_address' => $i->mac_address,
                'vlan_mode' => $i->vlan_mode?->value,
                'vlan_default' => $i->vlan_default,
                'vlans_allowed' => is_array($i->vlans_allowed) ? array_values($i->vlans_allowed) : [],
                'description' => $i->description,
            ])
            ->all();
    }

    public function setTopologyIconSize(int $sizePx): void
    {
        Gate::authorize('manage', DeviceIcon::class);

        $clamped = max(TopologyService::MIN_ICON_SIZE_PX, min(TopologyService::MAX_ICON_SIZE_PX, $sizePx));

        $tenantId = TenantContext::id();
        abort_if($tenantId === null, 403);

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->findOrFail($tenantId);
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['topology_icon_size_px'] = $clamped;
        $tenant->update(['settings' => $settings]);
    }

    /**
     * Returns the Cytoscape graph payload for the current filters.
     *
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function graphData(TopologyService $svc): array
    {
        return $svc->buildGraph(
            siteId: $this->siteId > 0 ? $this->siteId : null,
            types: $this->filterTypes !== [] ? $this->filterTypes : null,
            vlan: $this->vlanFilter > 0 ? $this->vlanFilter : null,
            status: $this->statusFilter !== '' ? $this->statusFilter : null,
            includeHidden: $this->includeHidden,
            groupByRack: $this->groupByRack,
            roomId: $this->roomFilter > 0 ? $this->roomFilter : null,
            groupBySite: $this->groupBySite,
            groupByRoom: $this->groupByRoom,
            tagIds: $this->tagFilters !== [] ? array_map('intval', $this->tagFilters) : null,
            hidePatchPanels: $this->hidePatchPanels,
            hideWifi: $this->hideWifi,
            hideVpn: $this->hideVpn,
            groupByHypervisor: $this->groupByHypervisor,
        );
    }

    public function render(TopologyService $svc): View
    {
        $user = auth()->user();
        $canEdit = $user !== null && $user->canManageData();

        $restore = null;
        if ($this->snapshotPreset > 0) {
            // BelongsToTenant global scope guarantees cross-tenant returns null.
            $snap = TopologySnapshot::query()->find($this->snapshotPreset);
            if ($snap !== null) {
                $state = is_array($snap->view_state) ? $snap->view_state : [];
                $positions = $state['nodePositions'] ?? null;
                $portSettings = is_array($state['portSettings'] ?? null) ? $state['portSettings'] : null;
                $nodeLabelPositions = is_array($state['nodeLabelPositions'] ?? null) ? $state['nodeLabelPositions'] : null;
                $sessionHiddenIds = is_array($state['sessionHiddenIds'] ?? null)
                    ? array_values(array_map('intval', $state['sessionHiddenIds']))
                    : null;
                $vpnNodeDetails = is_array($state['vpnNodeDetails'] ?? null) ? $state['vpnNodeDetails'] : null;
                $anyDisplayPref = $portSettings !== null || $nodeLabelPositions !== null || $sessionHiddenIds !== null || $vpnNodeDetails !== null;
                if (is_array($positions) && count($positions) > 0) {
                    $restore = [
                        // Cast to object so JSON-encodes as {} not [] when empty.
                        'nodePositions' => (object) $positions,
                        'zoom' => $state['zoom'] ?? null,
                        'pan' => $state['pan'] ?? null,
                        'portSettings' => $portSettings !== null ? (object) $portSettings : null,
                        'nodeLabelPositions' => $nodeLabelPositions !== null ? (object) $nodeLabelPositions : null,
                        'sessionHiddenIds' => $sessionHiddenIds,
                        'vpnNodeDetails' => $vpnNodeDetails !== null ? (object) $vpnNodeDetails : null,
                    ];
                } elseif ($anyDisplayPref) {
                    $restore = [
                        'portSettings' => $portSettings !== null ? (object) $portSettings : null,
                        'nodeLabelPositions' => $nodeLabelPositions !== null ? (object) $nodeLabelPositions : null,
                        'sessionHiddenIds' => $sessionHiddenIds,
                        'vpnNodeDetails' => $vpnNodeDetails !== null ? (object) $vpnNodeDetails : null,
                    ];
                }
            }
        }

        $rooms = Room::query()
            ->when($this->siteId > 0, fn ($q) => $q->where('site_id', $this->siteId))
            ->with('site')
            ->orderBy('name')
            ->get();

        return view('livewire.topology.graph', [
            'sites' => Site::query()->orderBy('name')->get(),
            'rooms' => $rooms,
            'types' => EquipmentType::cases(),
            'statuses' => EquipmentStatus::cases(),
            'allTags' => Tag::query()->orderBy('name')->get(),
            'graph' => $this->graphData($svc),
            'canEdit' => $canEdit,
            'minIconPx' => TopologyService::MIN_ICON_SIZE_PX,
            'maxIconPx' => TopologyService::MAX_ICON_SIZE_PX,
            'topologyIconSize' => $svc->tenantIconSizePx(TenantContext::id()),
            'restore' => $restore,
        ]);
    }
}
