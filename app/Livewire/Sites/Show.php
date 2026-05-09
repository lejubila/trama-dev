<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Models\Room;
use App\Models\Site;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Site $site;

    public bool $showRoomForm = false;

    public ?int $editingRoomId = null;

    #[Validate('required|string|max:150')]
    public string $roomName = '';

    #[Validate('nullable|string|max:50')]
    public string $roomFloor = '';

    #[Validate('nullable|string|max:2000')]
    public string $roomNotes = '';

    public function mount(Site $site): void
    {
        $this->authorize('view', $site);
        $this->site = $site;
    }

    public function openRoomCreate(): void
    {
        $this->authorize('create', Room::class);
        $this->reset(['editingRoomId', 'roomName', 'roomFloor', 'roomNotes']);
        $this->resetErrorBag();
        $this->showRoomForm = true;
    }

    public function openRoomEdit(int $id): void
    {
        $room = Room::query()->findOrFail($id);
        $this->authorize('update', $room);

        $this->editingRoomId = $room->getKey();
        $this->roomName = $room->name;
        $this->roomFloor = (string) ($room->floor ?? '');
        $this->roomNotes = (string) ($room->notes ?? '');
        $this->resetErrorBag();
        $this->showRoomForm = true;
    }

    public function saveRoom(): void
    {
        $this->validate();

        $payload = [
            'site_id' => $this->site->getKey(),
            'name' => $this->roomName,
            'floor' => $this->roomFloor !== '' ? $this->roomFloor : null,
            'notes' => $this->roomNotes !== '' ? $this->roomNotes : null,
        ];

        if ($this->editingRoomId !== null) {
            $room = Room::query()->findOrFail($this->editingRoomId);
            $this->authorize('update', $room);
            $room->update($payload);
            $this->dispatch('toast', type: 'success', message: 'Locale aggiornato.');
        } else {
            $this->authorize('create', Room::class);
            Room::create($payload);
            $this->dispatch('toast', type: 'success', message: 'Locale creato.');
        }

        $this->showRoomForm = false;
    }

    public function deleteRoom(int $id): void
    {
        $room = Room::query()->findOrFail($id);
        $this->authorize('delete', $room);
        $room->delete();
        $this->dispatch('toast', type: 'success', message: 'Locale rimosso.');
    }

    public function render(): View
    {
        return view('livewire.sites.show', [
            'rooms' => $this->site->rooms()->withCount('racks')->orderBy('name')->get(),
        ]);
    }
}
