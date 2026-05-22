<?php

declare(strict_types=1);

namespace App\Livewire\Topology;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\DeviceIcon;
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
    public int $vlanFilter = 0;

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

    #[Url(except: 0)]
    public int $roomFilter = 0;

    public function toggleType(string $type): void
    {
        if (in_array($type, $this->filterTypes, true)) {
            $this->filterTypes = array_values(array_filter(
                $this->filterTypes,
                fn (string $t): bool => $t !== $type,
            ));
        } else {
            $this->filterTypes[] = $type;
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['siteId', 'statusFilter', 'vlanFilter', 'tagFilters', 'filterTypes', 'includeHidden', 'groupByRack', 'groupBySite', 'groupByRoom', 'roomFilter']);
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
                if (is_array($positions) && count($positions) > 0) {
                    $restore = [
                        // Cast to object so JSON-encodes as {} not [] when empty.
                        'nodePositions' => (object) $positions,
                        'zoom' => $state['zoom'] ?? null,
                        'pan' => $state['pan'] ?? null,
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
