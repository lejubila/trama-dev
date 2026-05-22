<?php

declare(strict_types=1);

namespace App\Livewire\Racks;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Tag;
use App\Services\RackPlacementService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Elevation extends Component
{
    use WithFileUploads;

    public Rack $rack;

    public string $orient = 'front';

    // ── slot-click create form state ──────────────────────────────────────
    public bool $showForm = false;

    public ?int $selectedU = null;

    public bool $onTop = false;

    public int $positionUHeight = 1;

    public string $name = '';

    public string $type = 'switch';

    public string $vendor = '';

    public string $modelName = '';

    public string $serial = '';

    public string $firmware = '';

    public string $assetTag = '';

    public string $managementIp = '';

    public string $status = 'active';

    public bool $locked = false;

    public bool $hiddenInTopology = false;

    public string $description = '';

    public $iconUpload = null;

    /** @var array<int, int|string> */
    public array $selectedTagIds = [];

    public function mount(Rack $rack): void
    {
        $this->rack = $rack;
    }

    public function setOrient(string $orient): void
    {
        $this->orient = in_array($orient, ['front', 'rear'], true) ? $orient : 'front';
    }

    /**
     * Move a mounted equipment to a new starting U inside the same rack.
     * Validates via RackPlacementService and surfaces errors as toasts.
     */
    #[On('moveEquipment')]
    public function moveEquipment(int $id, int $newStartU, RackPlacementService $placement): void
    {
        $eq = Equipment::query()->findOrFail($id);
        $this->authorize('update', $eq);

        if ($eq->rack_id !== $this->rack->getKey()) {
            $this->dispatch('toast', type: 'error', message: __('racks.toast_not_in_rack'));

            return;
        }

        if ($eq->locked) {
            $this->dispatch('toast', type: 'error', message: __('racks.toast_locked'));

            return;
        }

        $eqOrient = $eq->position_orient ?: 'front';
        if (! $placement->canPlace($this->rack, $newStartU, (int) $eq->position_u_height, $eq, $eqOrient)) {
            $this->dispatch('toast', type: 'error', message: 'Posizione occupata o fuori dal rack su questo lato.');

            return;
        }

        $eq->update(['position_u_start' => $newStartU]);

        $this->dispatch('toast', type: 'success', message: "Spostato in U{$newStartU}.");
    }

    /**
     * Empty-slot click → open the prefilled create form.
     * The event is dispatched from <x-rack-elevation /> when interactive=true
     * and the slot is unoccupied.
     */
    #[On('slot-clicked')]
    public function slotClicked(int $u, ?string $orient = null): void
    {
        $this->authorize('create', Equipment::class);

        $this->reset(['name', 'vendor', 'modelName', 'serial', 'firmware', 'assetTag', 'managementIp', 'locked', 'hiddenInTopology', 'description', 'iconUpload', 'selectedTagIds']);
        $this->status = 'active';
        $this->selectedU = $u;
        // The slot belongs to the side the user is currently viewing.
        // Falling back to component state keeps backwards-compat with
        // old payloads that didn't include the orient.
        if ($orient !== null && in_array($orient, ['front', 'rear'], true)) {
            $this->orient = $orient;
        }
        $this->onTop = false;
        $this->positionUHeight = 1;
        $this->type = 'switch';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    /**
     * "+" button on the on-top strip → open the prefilled create form for a
     * device sitting on top of the rack (no U slot).
     */
    #[On('on-top-clicked')]
    public function onTopClicked(): void
    {
        $this->authorize('create', Equipment::class);

        $this->reset(['name', 'vendor', 'modelName', 'serial', 'firmware', 'assetTag', 'managementIp', 'locked', 'hiddenInTopology', 'description', 'iconUpload', 'selectedTagIds']);
        $this->status = 'active';
        $this->selectedU = null;
        $this->onTop = true;
        $this->positionUHeight = 1;
        $this->type = 'switch';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->selectedU = null;
        $this->onTop = false;
    }

    public function saveEquipment(RackPlacementService $placement): void
    {
        $this->authorize('create', Equipment::class);

        if (! $this->onTop && $this->selectedU === null) {
            return;
        }

        $rules = [
            'name' => 'required|string|max:150',
            'type' => ['required', Rule::in(array_column(EquipmentType::cases(), 'value'))],
            'vendor' => 'nullable|string|max:80',
            'modelName' => 'nullable|string|max:120',
            'serial' => 'nullable|string|max:120',
            'firmware' => 'nullable|string|max:80',
            'assetTag' => 'nullable|string|max:80',
            'managementIp' => 'nullable|ip',
            'status' => ['required', Rule::in(array_column(EquipmentStatus::cases(), 'value'))],
            'locked' => 'boolean',
            'hiddenInTopology' => 'boolean',
            'description' => 'nullable|string|max:5000',
            'iconUpload' => 'nullable|image|max:5120',
            'selectedTagIds' => 'array',
            'selectedTagIds.*' => 'integer|exists:tags,id',
        ];
        if (! $this->onTop) {
            $rules['positionUHeight'] = 'required|integer|min:1|max:60';
        }
        $this->validate($rules);

        if (! $this->onTop && ! $placement->canPlace($this->rack, $this->selectedU, $this->positionUHeight, null, $this->orient)) {
            $this->addError('positionUHeight', 'Posizione occupata sul lato '.($this->orient === 'rear' ? 'posteriore' : 'anteriore').' o fuori dal rack.');

            return;
        }

        $equipment = Equipment::create([
            'rack_id' => $this->rack->getKey(),
            'name' => $this->name,
            'type' => EquipmentType::from($this->type),
            'vendor' => $this->vendor !== '' ? $this->vendor : null,
            'model' => $this->modelName !== '' ? $this->modelName : null,
            'serial' => $this->serial !== '' ? $this->serial : null,
            'firmware' => $this->firmware !== '' ? $this->firmware : null,
            'asset_tag' => $this->assetTag !== '' ? $this->assetTag : null,
            'management_ip' => $this->managementIp !== '' ? $this->managementIp : null,
            'mounted' => true,
            'locked' => $this->locked,
            'hidden_in_topology' => $this->hiddenInTopology,
            'on_top' => $this->onTop,
            'position_u_start' => $this->onTop ? null : $this->selectedU,
            'position_u_height' => $this->onTop ? null : $this->positionUHeight,
            'position_orient' => $this->onTop ? null : $this->orient,
            'status' => EquipmentStatus::from($this->status),
            'description' => $this->description !== '' ? $this->description : null,
        ]);

        if ($this->iconUpload instanceof TemporaryUploadedFile) {
            $equipment->update([
                'icon_path' => $this->iconUpload->store("icons/{$equipment->tenant_id}/equipment", 'public'),
            ]);
        }

        $equipment->tags()->sync($this->selectedTagIds);

        $sideLabel = $this->orient === 'rear' ? ' (posteriore)' : ' (anteriore)';
        $location = $this->onTop ? 'sopra il rack' : "in U{$this->selectedU}{$sideLabel}";
        $this->dispatch('toast', type: 'success', message: "Dispositivo \"{$this->name}\" creato {$location}.");
        $this->closeForm();
    }

    public function render(): View
    {
        return view('livewire.racks.elevation', [
            'types' => EquipmentType::cases(),
            'statuses' => EquipmentStatus::cases(),
            'allTags' => Tag::query()->orderBy('name')->get(),
            'canEdit' => auth()->user()?->can('create', Equipment::class) ?? false,
        ]);
    }
}
