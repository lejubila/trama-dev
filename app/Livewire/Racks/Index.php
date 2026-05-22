<?php

declare(strict_types=1);

namespace App\Livewire\Racks;

use App\Enums\RackNumbering;
use App\Livewire\Concerns\RemembersFilters;
use App\Models\Rack;
use App\Models\Room;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use RemembersFilters, WithFileUploads, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 0)]
    public int $roomFilter = 0;

    /** When ?edit=<rackId> lands on the page, auto-open the edit modal. */
    #[Url(as: 'edit')]
    public ?int $autoEditId = null;

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

    #[Validate('nullable|numeric|min:0|max:999.99')]
    public ?float $positionX = null;

    #[Validate('nullable|numeric|min:0|max:999.99')]
    public ?float $positionY = null;

    #[Validate('nullable|image|max:5120')]
    public $iconUpload = null;

    public ?string $existingIconPath = null;

    public function mount(): void
    {
        if ($this->autoEditId !== null) {
            $rack = Rack::query()->find($this->autoEditId);
            if ($rack !== null && auth()->user()?->can('update', $rack)) {
                $this->openEdit($this->autoEditId);
            }
            $this->autoEditId = null;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoomFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int, string>
     */
    protected function rememberedFilters(): array
    {
        return ['search', 'roomFilter'];
    }

    public function openCreate(): void
    {
        $this->authorize('create', Rack::class);
        $this->reset(['editingId', 'name', 'heightUnits', 'widthMm', 'depthMm', 'numbering', 'notes', 'roomId', 'positionX', 'positionY', 'iconUpload', 'existingIconPath']);
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
        $this->positionX = $rack->position_x !== null ? (float) $rack->position_x : null;
        $this->positionY = $rack->position_y !== null ? (float) $rack->position_y : null;
        $this->iconUpload = null;
        $this->existingIconPath = $rack->icon_path;
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
            'position_x' => $this->positionX,
            'position_y' => $this->positionY,
        ];

        if ($this->editingId !== null) {
            $rack = Rack::query()->findOrFail($this->editingId);
            $this->authorize('update', $rack);

            if ($this->iconUpload instanceof TemporaryUploadedFile) {
                $payload['icon_path'] = $this->storeIcon($this->iconUpload, $rack->icon_path, (int) $rack->tenant_id);
            }

            $rack->update($payload);
            $message = 'Rack aggiornato.';
        } else {
            $this->authorize('create', Rack::class);
            $rack = Rack::create($payload);

            if ($this->iconUpload instanceof TemporaryUploadedFile) {
                $rack->update(['icon_path' => $this->storeIcon($this->iconUpload, null, (int) $rack->tenant_id)]);
            }

            $message = 'Rack creato.';
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function removeIcon(): void
    {
        if ($this->editingId === null) {
            $this->iconUpload = null;

            return;
        }

        $rack = Rack::query()->findOrFail($this->editingId);
        $this->authorize('update', $rack);

        if ($rack->icon_path !== null) {
            Storage::disk('public')->delete($rack->icon_path);
            $rack->update(['icon_path' => null]);
        }

        $this->iconUpload = null;
        $this->existingIconPath = null;
        $this->dispatch('toast', type: 'success', message: 'Icona rimossa.');
    }

    public function delete(int $id): void
    {
        $rack = Rack::query()->findOrFail($id);
        $this->authorize('delete', $rack);

        if ($rack->icon_path !== null) {
            Storage::disk('public')->delete($rack->icon_path);
        }

        $rack->delete();
        $this->dispatch('toast', type: 'success', message: 'Rack rimosso.');
    }

    private function storeIcon(TemporaryUploadedFile $file, ?string $previousPath, int $tenantId): string
    {
        $path = $file->store("icons/{$tenantId}/racks", 'public');

        if ($previousPath !== null && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

        return $path;
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
