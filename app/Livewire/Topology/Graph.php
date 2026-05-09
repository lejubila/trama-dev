<?php

declare(strict_types=1);

namespace App\Livewire\Topology;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\Site;
use App\Services\TopologyService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Graph extends Component
{
    #[Url(except: 0)]
    public int $siteId = 0;

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: 0)]
    public int $vlanFilter = 0;

    #[Url(except: 'cose-bilkent')]
    public string $layout = 'cose-bilkent';

    /**
     * Current type filter as a list of EquipmentType values.
     *
     * @var list<string>
     */
    public array $filterTypes = [];

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
        $this->reset(['siteId', 'statusFilter', 'vlanFilter', 'filterTypes']);
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
        );
    }

    public function render(TopologyService $svc): View
    {
        return view('livewire.topology.graph', [
            'sites' => Site::query()->orderBy('name')->get(),
            'types' => EquipmentType::cases(),
            'statuses' => EquipmentStatus::cases(),
            'graph' => $this->graphData($svc),
        ]);
    }
}
