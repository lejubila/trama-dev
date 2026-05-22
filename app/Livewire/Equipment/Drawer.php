<?php

declare(strict_types=1);

namespace App\Livewire\Equipment;

use App\Models\Connection;
use App\Models\Equipment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Slide-over drawer that surfaces an Equipment row when the user clicks one
 * inside the rack-elevation SVG. Listens for the `equipment-clicked` event
 * dispatched by `<x-rack-elevation />` (and re-usable from anywhere in the
 * Livewire tree).
 */
class Drawer extends Component
{
    public bool $open = false;

    public ?Equipment $equipment = null;

    public string $activeTab = 'general';

    #[On('equipment-clicked')]
    public function load(int $id): void
    {
        $eq = Equipment::query()
            ->with(['rack.room.site', 'interfaces', 'tags'])
            ->findOrFail($id);

        $this->authorize('view', $eq);

        $this->equipment = $eq;
        $this->activeTab = 'general';
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->equipment = null;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['general', 'interfaces', 'connections', 'audit'], true)
            ? $tab
            : 'general';
    }

    public function render(): View
    {
        $audits = $this->equipment !== null && $this->activeTab === 'audit'
            ? $this->equipment->audits()->latest()->limit(20)->get()
            : new Collection;

        $connections = $this->equipment !== null && $this->activeTab === 'connections'
            ? Connection::query()
                ->with(['fromInterface.equipment', 'toInterface.equipment'])
                ->where(function ($q): void {
                    $ifIds = $this->equipment->interfaces->pluck('id')->all();
                    $q->whereIn('from_interface_id', $ifIds)
                        ->orWhereIn('to_interface_id', $ifIds);
                })
                ->get()
            : new Collection;

        return view('livewire.equipment.drawer', [
            'audits' => $audits,
            'connections' => $connections,
        ]);
    }
}
