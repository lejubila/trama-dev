<?php

declare(strict_types=1);

namespace App\Livewire\Racks;

use App\Models\Rack;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Rack $rack;

    public function mount(Rack $rack): void
    {
        $this->authorize('view', $rack);
        $this->rack = $rack->load('room.site');
    }

    public function render(): View
    {
        return view('livewire.racks.show', [
            'mountedEquipment' => $this->rack
                ->equipment()
                ->where('mounted', true)
                ->orderByDesc('position_u_start')
                ->get(),
        ]);
    }
}
