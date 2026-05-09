<?php

declare(strict_types=1);

namespace App\Livewire\Racks;

use App\Models\Equipment;
use App\Models\Rack;
use App\Services\RackPlacementService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Elevation extends Component
{
    public Rack $rack;

    public string $orient = 'front';

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

    public function render(): View
    {
        return view('livewire.racks.elevation');
    }
}
