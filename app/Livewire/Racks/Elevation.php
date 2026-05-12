<?php

declare(strict_types=1);

namespace App\Livewire\Racks;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\Rack;
use App\Services\RackPlacementService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Elevation extends Component
{
    public Rack $rack;

    public string $orient = 'front';

    // ── slot-click create form state ──────────────────────────────────────
    public bool $showForm = false;

    public ?int $selectedU = null;

    public int $positionUHeight = 1;

    public string $name = '';

    public string $type = 'switch';

    public string $vendor = '';

    public string $modelName = '';

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
            $this->dispatch('toast', type: 'error', message: 'Il dispositivo non appartiene a questo rack.');

            return;
        }

        if ($eq->locked) {
            $this->dispatch('toast', type: 'error', message: 'Dispositivo bloccato: sblocca per riposizionarlo.');

            return;
        }

        if (! $placement->canPlace($this->rack, $newStartU, (int) $eq->position_u_height, $eq)) {
            $this->dispatch('toast', type: 'error', message: 'Posizione occupata o fuori dal rack.');

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
    public function slotClicked(int $u): void
    {
        $this->authorize('create', Equipment::class);

        $this->reset(['name', 'vendor', 'modelName']);
        $this->selectedU = $u;
        $this->positionUHeight = 1;
        $this->type = 'switch';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->selectedU = null;
    }

    public function saveEquipment(RackPlacementService $placement): void
    {
        $this->authorize('create', Equipment::class);

        if ($this->selectedU === null) {
            return;
        }

        $this->validate([
            'name' => 'required|string|max:150',
            'type' => ['required', Rule::in(array_column(EquipmentType::cases(), 'value'))],
            'positionUHeight' => 'required|integer|min:1|max:60',
            'vendor' => 'nullable|string|max:80',
            'modelName' => 'nullable|string|max:120',
        ]);

        if (! $placement->canPlace($this->rack, $this->selectedU, $this->positionUHeight)) {
            $this->addError('positionUHeight', 'Posizione occupata o fuori dal rack.');

            return;
        }

        Equipment::create([
            'rack_id' => $this->rack->getKey(),
            'name' => $this->name,
            'type' => EquipmentType::from($this->type),
            'vendor' => $this->vendor !== '' ? $this->vendor : null,
            'model' => $this->modelName !== '' ? $this->modelName : null,
            'mounted' => true,
            'locked' => false,
            'position_u_start' => $this->selectedU,
            'position_u_height' => $this->positionUHeight,
            'status' => EquipmentStatus::Active,
        ]);

        $this->dispatch('toast', type: 'success', message: "Dispositivo \"{$this->name}\" creato in U{$this->selectedU}.");
        $this->closeForm();
    }

    public function render(): View
    {
        return view('livewire.racks.elevation', [
            'types' => EquipmentType::cases(),
            'canEdit' => auth()->user()?->can('create', Equipment::class) ?? false,
        ]);
    }
}
