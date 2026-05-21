<?php

declare(strict_types=1);

namespace App\Livewire\Rooms;

use App\Models\Room;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Room $room;

    public function mount(Room $room): void
    {
        $this->authorize('view', $room);
        $this->room = $room->loadMissing('site');
    }

    public function render(): View
    {
        return view('livewire.rooms.show');
    }
}
