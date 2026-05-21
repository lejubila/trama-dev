<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Models\Document;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\TopologySnapshot;
use App\Services\Export\DocumentPdfBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Editor extends Component
{
    public ?Document $document = null;

    // Header
    public string $title = '';

    public string $description = '';

    public string $documentDate = '';

    // Sections — sites
    public bool $sitesEnabled = false;

    public string $sitesDescription = '';

    /** @var array<int, int> */
    public array $sitesIds = [];

    // Sections — rooms
    public bool $roomsEnabled = false;

    public string $roomsDescription = '';

    /** @var array<int, int> */
    public array $roomsIds = [];

    // Sections — racks
    public bool $racksEnabled = false;

    public string $racksDescription = '';

    /** @var array<int, int> */
    public array $racksIds = [];

    // Sections — equipment
    public bool $equipmentEnabled = false;

    public string $equipmentDescription = '';

    /** @var array<int, int> */
    public array $equipmentIds = [];

    /**
     * Equipment ids whose interface/port table should NOT be reported.
     * Ports are reported by default, so we only persist the exceptions.
     *
     * @var array<int, int>
     */
    public array $equipmentPortsExcluded = [];

    /**
     * Snapshot of racksIds taken right before a wire:model update, so the
     * updated hook can tell which racks were newly selected.
     *
     * @var array<int, int>
     */
    protected array $racksBeforeUpdate = [];

    // Sections — topologies
    public bool $topologiesEnabled = false;

    public string $topologiesDescription = '';

    /**
     * Per-snapshot inclusion + orientation. Indexed by snapshot id:
     * `[id => 'portrait'|'landscape']` if included, missing if not.
     *
     * @var array<int, string>
     */
    public array $topologiesItems = [];

    public bool $includeCover = true;

    public bool $includeToc = true;

    public function mount(?Document $document = null): void
    {
        if ($document !== null && $document->exists) {
            $this->authorize('update', $document);
            $this->document = $document;
            $this->loadFromDocument($document);
        } else {
            $this->authorize('create', Document::class);
            $this->title = '';
            $this->description = '';
            $this->documentDate = now()->toDateString();
        }
    }

    private function loadFromDocument(Document $document): void
    {
        $this->title = (string) $document->title;
        $this->description = (string) ($document->description ?? '');
        $this->documentDate = $document->document_date->toDateString();

        $p = is_array($document->parameters) ? $document->parameters : [];
        $sec = is_array($p['sections'] ?? null) ? $p['sections'] : [];

        $this->sitesEnabled = (bool) ($sec['sites']['enabled'] ?? false);
        $this->sitesDescription = (string) ($sec['sites']['description'] ?? '');
        $this->sitesIds = array_values(array_map('intval', $sec['sites']['ids'] ?? []));

        $this->roomsEnabled = (bool) ($sec['rooms']['enabled'] ?? false);
        $this->roomsDescription = (string) ($sec['rooms']['description'] ?? '');
        $this->roomsIds = array_values(array_map('intval', $sec['rooms']['ids'] ?? []));

        $this->racksEnabled = (bool) ($sec['racks']['enabled'] ?? false);
        $this->racksDescription = (string) ($sec['racks']['description'] ?? '');
        $this->racksIds = array_values(array_map('intval', $sec['racks']['ids'] ?? []));

        $this->equipmentEnabled = (bool) ($sec['equipment']['enabled'] ?? false);
        $this->equipmentDescription = (string) ($sec['equipment']['description'] ?? '');
        $this->equipmentIds = array_values(array_map('intval', $sec['equipment']['ids'] ?? []));
        $this->equipmentPortsExcluded = array_values(array_map('intval', $sec['equipment']['ports_excluded'] ?? []));

        $this->topologiesEnabled = (bool) ($sec['topologies']['enabled'] ?? false);
        $this->topologiesDescription = (string) ($sec['topologies']['description'] ?? '');
        $this->topologiesItems = [];
        foreach ($sec['topologies']['items'] ?? [] as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id > 0) {
                $this->topologiesItems[$id] = ($item['orientation'] ?? 'portrait') === 'landscape'
                    ? 'landscape'
                    : 'portrait';
            }
        }

        $opt = is_array($p['options'] ?? null) ? $p['options'] : [];
        $this->includeCover = (bool) ($opt['include_cover'] ?? true);
        $this->includeToc = (bool) ($opt['include_toc'] ?? true);
    }

    public function selectAll(string $section): void
    {
        $map = [
            'sites' => fn () => Site::query()->pluck('id')->all(),
            'rooms' => fn () => Room::query()->pluck('id')->all(),
            'racks' => fn () => Rack::query()->pluck('id')->all(),
            'equipment' => fn () => Equipment::query()->pluck('id')->all(),
        ];
        if (! isset($map[$section])) {
            return;
        }
        $prop = $section.'Ids';
        $this->{$prop} = array_values(array_map('intval', $map[$section]()));
    }

    public function selectNone(string $section): void
    {
        $prop = $section.'Ids';
        if (property_exists($this, $prop)) {
            $this->{$prop} = [];
        }
    }

    public function moveSite(int $id, int $dir): void
    {
        $this->move($this->sitesIds, $id, $this->sitesIds, $dir);
    }

    public function moveRoom(int $id, int $dir): void
    {
        $room = Room::query()->find($id);
        if ($room === null) {
            return;
        }
        $siblings = Room::query()
            ->whereIn('id', $this->roomsIds)
            ->where('site_id', $room->site_id)
            ->pluck('id')->all();
        $this->move($this->roomsIds, $id, $siblings, $dir);
    }

    public function moveRack(int $id, int $dir): void
    {
        $rack = Rack::query()->find($id);
        if ($rack === null) {
            return;
        }
        $siblings = Rack::query()
            ->whereIn('id', $this->racksIds)
            ->where('room_id', $rack->room_id)
            ->pluck('id')->all();
        $this->move($this->racksIds, $id, $siblings, $dir);
    }

    public function moveEquipment(int $id, int $dir): void
    {
        $eq = Equipment::query()->find($id);
        if ($eq === null) {
            return;
        }
        if ($eq->rack_id !== null) {
            $siblings = Equipment::query()
                ->whereIn('id', $this->equipmentIds)
                ->where('rack_id', $eq->rack_id)
                ->pluck('id')->all();
        } else {
            $siblings = Equipment::query()
                ->whereIn('id', $this->equipmentIds)
                ->whereNull('rack_id')
                ->where('room_id', $eq->room_id)
                ->pluck('id')->all();
        }
        $this->move($this->equipmentIds, $id, $siblings, $dir);
    }

    public function updatingRacksIds(): void
    {
        $this->racksBeforeUpdate = array_map('intval', $this->racksIds);
    }

    /**
     * When a rack is newly selected, auto-select all the devices it contains
     * (in their physical rack order) and enable the Dispositivi section.
     */
    public function updatedRacksIds(): void
    {
        $new = array_map('intval', array_values($this->racksIds));
        $added = array_values(array_diff($new, $this->racksBeforeUpdate));
        if ($added === []) {
            return;
        }

        $equipIds = Equipment::query()
            ->whereIn('rack_id', $added)
            ->get(['id', 'rack_id', 'on_top', 'position_u_start'])
            ->sortBy([['rack_id', 'asc'], ['on_top', 'desc'], ['position_u_start', 'desc']])
            ->pluck('id')
            ->all();
        if ($equipIds === []) {
            return;
        }

        $current = array_map('intval', array_values($this->equipmentIds));
        foreach ($equipIds as $eid) {
            if (! in_array($eid, $current, true)) {
                $current[] = $eid;
            }
        }
        $this->equipmentIds = $current;
        $this->equipmentEnabled = true;
    }

    public function toggleEquipmentPorts(int $id): void
    {
        if (in_array($id, $this->equipmentPortsExcluded, true)) {
            $this->equipmentPortsExcluded = array_values(
                array_filter($this->equipmentPortsExcluded, fn ($x) => $x !== $id),
            );
        } else {
            $this->equipmentPortsExcluded[] = $id;
        }
    }

    public function moveTopology(int $id, int $dir): void
    {
        $keys = array_map('intval', array_keys($this->topologiesItems));
        $pos = array_search($id, $keys, true);
        if ($pos === false) {
            return;
        }
        $target = $pos + $dir;
        if ($target < 0 || $target >= count($keys)) {
            return;
        }
        [$keys[$pos], $keys[$target]] = [$keys[$target], $keys[$pos]];

        $reordered = [];
        foreach ($keys as $k) {
            $reordered[$k] = $this->topologiesItems[$k];
        }
        $this->topologiesItems = $reordered;
    }

    /**
     * Swap $id with its adjacent selected sibling inside the flat $ids array.
     * Sibling order is derived from $ids itself (filtered to $siblingIds), so
     * the move respects the print order already chosen for that level.
     *
     * @param  array<int, int>  $ids
     * @param  array<int, int>  $siblingIds
     */
    private function move(array &$ids, int $id, array $siblingIds, int $dir): void
    {
        // wire:model.live populates these arrays with string values from the
        // checkbox DOM, while a loaded document yields ints. Normalize so the
        // strict comparisons below behave the same in both cases.
        $ids = array_map('intval', array_values($ids));
        $siblingIds = array_map('intval', $siblingIds);

        $sibSet = array_flip($siblingIds);
        $order = array_values(array_filter($ids, fn ($x) => isset($sibSet[$x])));

        $pos = array_search($id, $order, true);
        if ($pos === false) {
            return;
        }
        $target = $pos + $dir;
        if ($target < 0 || $target >= count($order)) {
            return;
        }
        $neighbor = $order[$target];

        $i = array_search($id, $ids, true);
        $j = array_search($neighbor, $ids, true);
        if ($i === false || $j === false) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    }

    public function toggleSnapshot(int $id): void
    {
        if (isset($this->topologiesItems[$id])) {
            unset($this->topologiesItems[$id]);
        } else {
            $this->topologiesItems[$id] = 'portrait';
        }
    }

    public function setSnapshotOrientation(int $id, string $orientation): void
    {
        if (! isset($this->topologiesItems[$id])) {
            return;
        }
        $this->topologiesItems[$id] = $orientation === 'landscape' ? 'landscape' : 'portrait';
    }

    public function save(DocumentPdfBuilder $builder)
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'documentDate' => 'required|date',
            'sitesDescription' => 'nullable|string|max:5000',
            'roomsDescription' => 'nullable|string|max:5000',
            'racksDescription' => 'nullable|string|max:5000',
            'equipmentDescription' => 'nullable|string|max:5000',
            'topologiesDescription' => 'nullable|string|max:5000',
        ]);

        $parameters = [
            'sections' => [
                'sites' => [
                    'enabled' => $this->sitesEnabled,
                    'description' => $this->sitesDescription,
                    'ids' => array_values(array_map('intval', $this->sitesIds)),
                ],
                'rooms' => [
                    'enabled' => $this->roomsEnabled,
                    'description' => $this->roomsDescription,
                    'ids' => array_values(array_map('intval', $this->roomsIds)),
                ],
                'racks' => [
                    'enabled' => $this->racksEnabled,
                    'description' => $this->racksDescription,
                    'ids' => array_values(array_map('intval', $this->racksIds)),
                ],
                'equipment' => [
                    'enabled' => $this->equipmentEnabled,
                    'description' => $this->equipmentDescription,
                    'ids' => array_values(array_map('intval', $this->equipmentIds)),
                    'ports_excluded' => array_values(array_map('intval', $this->equipmentPortsExcluded)),
                ],
                'topologies' => [
                    'enabled' => $this->topologiesEnabled,
                    'description' => $this->topologiesDescription,
                    'items' => $this->topologiesItemsPayload(),
                ],
            ],
            'options' => [
                'include_cover' => $this->includeCover,
                'include_toc' => $this->includeToc,
            ],
        ];

        if ($this->document !== null && $this->document->exists) {
            $this->authorize('update', $this->document);
            $this->document->update([
                'title' => $this->title,
                'description' => $this->description !== '' ? $this->description : null,
                'document_date' => $this->documentDate,
                'parameters' => $parameters,
            ]);
            $doc = $this->document;
        } else {
            $this->authorize('create', Document::class);
            $doc = Document::create([
                'title' => $this->title,
                'description' => $this->description !== '' ? $this->description : null,
                'document_date' => $this->documentDate,
                'parameters' => $parameters,
                'created_by' => auth()->id(),
            ]);
        }

        try {
            $builder->build($doc);
            $this->dispatch('toast', type: 'success', message: 'Documento salvato e PDF generato.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'warning',
                message: 'Documento salvato, ma la generazione PDF è fallita: '.$e->getMessage());
        }

        return $this->redirectRoute('documents.index', navigate: true);
    }

    /**
     * @return array<int, array{id:int, orientation:string}>
     */
    private function topologiesItemsPayload(): array
    {
        $out = [];
        foreach ($this->topologiesItems as $id => $orient) {
            $out[] = [
                'id' => (int) $id,
                'orientation' => $orient === 'landscape' ? 'landscape' : 'portrait',
            ];
        }

        return $out;
    }

    /**
     * Build the Sede → Locale → (Rack → Dispositivo | Dispositivo) tree the
     * editor renders. Selected items come first in their saved order (so the
     * up/down arrows reflect the print order), unselected ones follow by name.
     *
     * @return Collection<int, object>
     */
    private function buildTree(): Collection
    {
        $rooms = Room::query()->orderBy('name')->get()->groupBy('site_id');
        $racks = Rack::query()->orderBy('name')->get()->groupBy('room_id');
        $equipment = Equipment::query()->orderBy('name')->get();
        $rackedByRack = $equipment->whereNotNull('rack_id')->groupBy('rack_id');
        $unrackedByRoom = $equipment->whereNull('rack_id')->whereNotNull('room_id')->groupBy('room_id');

        return $this->orderNodes(Site::query()->orderBy('name')->get(), $this->sitesIds)
            ->map(function (object $siteNode) use ($rooms, $racks, $rackedByRack, $unrackedByRoom) {
                $siteRooms = $rooms->get($siteNode->model->id, collect());
                $siteNode->rooms = $this->orderNodes($siteRooms, $this->roomsIds)
                    ->map(function (object $roomNode) use ($racks, $rackedByRack, $unrackedByRoom) {
                        $roomRacks = $racks->get($roomNode->model->id, collect());
                        $roomNode->racks = $this->orderNodes($roomRacks, $this->racksIds)
                            ->map(function (object $rackNode) use ($rackedByRack) {
                                // Show devices in their physical order inside the
                                // rack (top of rack first), matching the elevation.
                                $rackEq = $rackedByRack->get($rackNode->model->id, collect())
                                    ->sortBy([['on_top', 'desc'], ['position_u_start', 'desc']])
                                    ->values();
                                $rackNode->equipment = $this->orderNodes($rackEq, $this->equipmentIds);

                                return $rackNode;
                            });
                        $roomEq = $unrackedByRoom->get($roomNode->model->id, collect());
                        $roomNode->unracked = $this->orderNodes($roomEq, $this->equipmentIds);

                        return $roomNode;
                    });

                return $siteNode;
            });
    }

    /**
     * Wrap models in node objects ordered selected-first (by saved order),
     * then the rest in the order they arrive (callers pre-sort the input, e.g.
     * by name or by rack position). Each node exposes selected/canUp/canDown.
     *
     * @param  Collection<int, Model>  $models
     * @param  array<int, int>  $selectedIds
     * @return Collection<int, object>
     */
    private function orderNodes(Collection $models, array $selectedIds): Collection
    {
        // selectedIds may hold strings (wire:model.live) or ints (loaded doc).
        $position = array_flip(array_map('intval', array_values($selectedIds)));

        $selected = $models
            ->filter(fn ($m) => isset($position[$m->id]))
            ->sortBy(fn ($m) => $position[$m->id])
            ->values();
        $rest = $models
            ->filter(fn ($m) => ! isset($position[$m->id]))
            ->values();

        $count = $selected->count();
        $nodes = collect();
        foreach ($selected as $i => $m) {
            $nodes->push((object) [
                'model' => $m,
                'selected' => true,
                'canUp' => $i > 0,
                'canDown' => $i < $count - 1,
            ]);
        }
        foreach ($rest as $m) {
            $nodes->push((object) [
                'model' => $m,
                'selected' => false,
                'canUp' => false,
                'canDown' => false,
            ]);
        }

        return $nodes;
    }

    public function render(): View
    {
        $allSnapshots = TopologySnapshot::query()
            ->orderByDesc('snapshot_date')->orderByDesc('id')->get();
        $snapPos = array_flip(array_keys($this->topologiesItems));
        $selectedSnaps = $allSnapshots
            ->filter(fn ($s) => isset($snapPos[$s->id]))
            ->sortBy(fn ($s) => $snapPos[$s->id])
            ->values();
        $restSnaps = $allSnapshots
            ->filter(fn ($s) => ! isset($snapPos[$s->id]))
            ->values();

        return view('livewire.documents.editor', [
            'tree' => $this->buildTree(),
            'selectedSnaps' => $selectedSnaps,
            'restSnaps' => $restSnaps,
            'snapshotCount' => $allSnapshots->count(),
        ]);
    }
}
