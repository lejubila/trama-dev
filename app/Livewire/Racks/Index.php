<?php

declare(strict_types=1);

namespace App\Livewire\Racks;

use App\Enums\RackNumbering;
use App\Models\Rack;
use App\Models\Room;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 0)]
    public int $roomFilter = 0;

    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|exists:rooms,id')]
    public ?int $roomId = null;

    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('required|integer|min:1|max:60')]
    public int $heightUnits = 42;

    #[Validate('nullable|integer|min:100|max:1500')]
    public ?int $widthMm = 600;

    #[Validate('nullable|integer|min:100|max:2000')]
    public ?int $depthMm = 1000;

    #[Validate('required|in:bottom_up,top_down')]
    public string $numbering = 'bottom_up';

    #[Validate('nullable|string|max:2000')]
    public string $notes = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoomFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Rack::class);
        $this->reset(['editingId', 'name', 'heightUnits', 'widthMm', 'depthMm', 'numbering', 'notes', 'roomId']);
        $this->heightUnits = 42;
        $this->widthMm = 600;
        $this->depthMm = 1000;
        $this->numbering = 'bottom_up';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $rack = Rack::query()->findOrFail($id);
        $this->authorize('update', $rack);

        $this->editingId = $rack->getKey();
        $this->roomId = $rack->room_id;
        $this->name = $rack->name;
        $this->heightUnits = $rack->height_units;
        $this->widthMm = $rack->width_mm;
        $this->depthMm = $rack->depth_mm;
        $this->numbering = $rack->numbering->value;
        $this->notes = (string) ($rack->notes ?? '');
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'room_id' => $this->roomId,
            'name' => $this->name,
            'height_units' => $this->heightUnits,
            'width_mm' => $this->widthMm,
            'depth_mm' => $this->depthMm,
            'numbering' => RackNumbering::from($this->numbering),
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];

        if ($this->editingId !== null) {
            $rack = Rack::query()->findOrFail($this->editingId);
            $this->authorize('update', $rack);
            $rack->update($payload);
            $message = 'Rack aggiornato.';
        } else {
            $this->authorize('create', Rack::class);
            Rack::create($payload);
            $message = 'Rack creato.';
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function delete(int $id): void
    {
        $rack = Rack::query()->findOrFail($id);
        $this->authorize('delete', $rack);
        $rack->delete();
        $this->dispatch('toast', type: 'success', message: 'Rack rimosso.');
    }

    public function render(): View
    {
        $racks = Rack::query()
            ->with('room.site')
            ->withCount('equipment')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'ilike', "%{$this->search}%"))
            ->when($this->roomFilter > 0, fn ($q) => $q->where('room_id', $this->roomFilter))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.racks.index', [
            'racks' => $racks,
            'rooms' => Room::query()->with('site')->orderBy('name')->get(),
        ]);
    }
}
