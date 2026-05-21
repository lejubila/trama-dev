<?php

declare(strict_types=1);

namespace App\Livewire\Equipment;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\Rack;
use App\Models\Room;
use App\Services\RackPlacementService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    #[Url(except: 0)]
    public int $rackFilter = 0;

    #[Url(except: '')]
    public string $statusFilter = '';

    /** When ?edit=<equipmentId> lands on the page, auto-open the edit modal. */
    #[Url(as: 'edit')]
    public ?int $autoEditId = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $rackId = null;

    public ?int $roomId = null;

    public string $name = '';

    public string $type = 'switch';

    public string $vendor = '';

    public string $modelName = '';

    public string $serial = '';

    public bool $mounted = false;

    public bool $locked = false;

    public ?int $positionUStart = null;

    public ?int $positionUHeight = 1;

    public string $positionOrient = 'front';

    public bool $onTop = false;

    public bool $hiddenInTopology = false;

    public string $status = 'active';

    public string $description = '';

    public $iconUpload = null;

    public ?string $existingIconPath = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rackId' => 'nullable|exists:racks,id',
            'roomId' => 'nullable|exists:rooms,id',
            'name' => 'required|string|max:150',
            'type' => ['required', Rule::in(array_column(EquipmentType::cases(), 'value'))],
            'vendor' => 'nullable|string|max:80',
            'modelName' => 'nullable|string|max:120',
            'serial' => 'nullable|string|max:120',
            'mounted' => 'boolean',
            'locked' => 'boolean',
            'positionUStart' => 'nullable|integer|min:1|max:60',
            'positionUHeight' => 'nullable|integer|min:1|max:60',
            'positionOrient' => 'required|in:front,rear',
            'onTop' => 'boolean',
            'hiddenInTopology' => 'boolean',
            'status' => ['required', Rule::in(array_column(EquipmentStatus::cases(), 'value'))],
            'description' => 'nullable|string|max:5000',
            'iconUpload' => 'nullable|image|max:5120',
        ];
    }

    public function mount(): void
    {
        if ($this->autoEditId !== null) {
            $eq = Equipment::query()->find($this->autoEditId);
            if ($eq !== null && auth()->user()?->can('update', $eq)) {
                $this->openEdit($this->autoEditId);
            }
            $this->autoEditId = null;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingRackFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Keep roomId synced with the rack's room whenever the user picks a
     * different rack in the form. Empty selection leaves the roomId
     * untouched so an admin can still pick a room manually after detaching
     * from a rack.
     */
    public function updatedRackId(mixed $value): void
    {
        if (! empty($value)) {
            $rack = Rack::query()->find((int) $value);
            if ($rack !== null) {
                $this->roomId = (int) $rack->room_id;
            }
        }
    }

    public function openCreate(): void
    {
        $this->authorize('create', Equipment::class);
        $this->reset(['editingId', 'rackId', 'roomId', 'name', 'vendor', 'modelName', 'serial', 'mounted', 'locked', 'positionUStart', 'description', 'iconUpload', 'existingIconPath', 'onTop', 'hiddenInTopology']);
        $this->type = 'switch';
        $this->status = 'active';
        $this->positionUHeight = 1;
        $this->positionOrient = 'front';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $eq = Equipment::query()->findOrFail($id);
        $this->authorize('update', $eq);

        $this->editingId = $eq->getKey();
        $this->rackId = $eq->rack_id;
        $this->roomId = $eq->room_id;
        $this->name = $eq->name;
        $this->type = $eq->type?->value ?? 'switch';
        $this->vendor = (string) ($eq->vendor ?? '');
        $this->modelName = (string) ($eq->model ?? '');
        $this->serial = (string) ($eq->serial ?? '');
        $this->mounted = (bool) $eq->mounted;
        $this->locked = (bool) $eq->locked;
        $this->positionUStart = $eq->position_u_start;
        $this->positionUHeight = $eq->position_u_height ?? 1;
        $this->positionOrient = $eq->position_orient ?: 'front';
        $this->onTop = (bool) $eq->on_top;
        $this->hiddenInTopology = (bool) $eq->hidden_in_topology;
        $this->status = $eq->status?->value ?? 'active';
        $this->description = (string) ($eq->description ?? '');
        $this->iconUpload = null;
        $this->existingIconPath = $eq->icon_path;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(RackPlacementService $placement): void
    {
        $this->validate();

        if ($this->mounted) {
            if ($this->rackId === null) {
                $this->addError('rackId', 'Un dispositivo montato richiede un rack.');

                return;
            }

            if (! $this->onTop) {
                if ($this->positionUStart === null || $this->positionUHeight === null) {
                    $this->addError('positionUStart', 'Posizione U obbligatoria per dispositivi montati in una unità.');

                    return;
                }

                $rack = Rack::query()->findOrFail($this->rackId);
                $excluding = $this->editingId ? Equipment::query()->find($this->editingId) : null;

                if (! $placement->canPlace($rack, $this->positionUStart, $this->positionUHeight, $excluding, $this->positionOrient)) {
                    $this->addError('positionUStart', 'Conflitto con altro dispositivo sullo stesso lato, o posizione fuori dal rack.');

                    return;
                }
            }
        }

        // U coordinates are only meaningful for rack-mounted devices that
        // actually occupy a slot. On-top devices reuse the rack association
        // but have NULL Us so they don't clash with anything in the layout.
        $useUs = $this->mounted && ! $this->onTop;

        // When the device sits in a rack, the rack's room is authoritative:
        // re-derive room_id here so it can't drift out of sync with the rack.
        // Unracked devices keep whatever room the user selected manually.
        if ($this->mounted && $this->rackId !== null) {
            $rackForRoom = Rack::query()->find($this->rackId);
            if ($rackForRoom !== null) {
                $this->roomId = (int) $rackForRoom->room_id;
            }
        }

        $payload = [
            'rack_id' => $this->rackId,
            'room_id' => $this->roomId,
            'name' => $this->name,
            'type' => EquipmentType::from($this->type),
            'vendor' => $this->vendor !== '' ? $this->vendor : null,
            'model' => $this->modelName !== '' ? $this->modelName : null,
            'serial' => $this->serial !== '' ? $this->serial : null,
            'mounted' => $this->mounted,
            'locked' => $this->locked,
            'on_top' => $this->mounted ? $this->onTop : false,
            'hidden_in_topology' => $this->hiddenInTopology,
            'position_u_start' => $useUs ? $this->positionUStart : null,
            'position_u_height' => $useUs ? $this->positionUHeight : null,
            'position_orient' => $this->mounted ? $this->positionOrient : null,
            'status' => EquipmentStatus::from($this->status),
            'description' => $this->description !== '' ? $this->description : null,
        ];

        if ($this->editingId !== null) {
            $eq = Equipment::query()->findOrFail($this->editingId);
            $this->authorize('update', $eq);

            if ($this->iconUpload instanceof TemporaryUploadedFile) {
                $payload['icon_path'] = $this->storeIcon($this->iconUpload, $eq->icon_path, (int) $eq->tenant_id);
            }

            $eq->update($payload);
            $this->dispatch('toast', type: 'success', message: 'Dispositivo aggiornato.');
        } else {
            $this->authorize('create', Equipment::class);
            $eq = Equipment::create($payload);

            if ($this->iconUpload instanceof TemporaryUploadedFile) {
                $eq->update(['icon_path' => $this->storeIcon($this->iconUpload, null, (int) $eq->tenant_id)]);
            }

            $this->dispatch('toast', type: 'success', message: 'Dispositivo creato.');
        }

        $this->showForm = false;
    }

    public function removeIcon(): void
    {
        if ($this->editingId === null) {
            $this->iconUpload = null;

            return;
        }

        $eq = Equipment::query()->findOrFail($this->editingId);
        $this->authorize('update', $eq);

        if ($eq->icon_path !== null) {
            Storage::disk('public')->delete($eq->icon_path);
            $eq->update(['icon_path' => null]);
        }

        $this->iconUpload = null;
        $this->existingIconPath = null;
        $this->dispatch('toast', type: 'success', message: 'Icona rimossa.');
    }

    public function delete(int $id): void
    {
        $eq = Equipment::query()->findOrFail($id);
        $this->authorize('delete', $eq);

        if ($eq->icon_path !== null) {
            Storage::disk('public')->delete($eq->icon_path);
        }

        $eq->delete();
        $this->dispatch('toast', type: 'success', message: 'Dispositivo rimosso.');
    }

    private function storeIcon(TemporaryUploadedFile $file, ?string $previousPath, int $tenantId): string
    {
        $path = $file->store("icons/{$tenantId}/equipment", 'public');

        if ($previousPath !== null && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

        return $path;
    }

    public function render(): View
    {
        $equipment = Equipment::query()
            ->with(['rack.room.site', 'room.site'])
            ->withCount('interfaces')
            ->when($this->search !== '', fn ($q) => $q->where(function ($qq) {
                $qq->where('name', 'ilike', "%{$this->search}%")
                    ->orWhere('serial', 'ilike', "%{$this->search}%")
                    ->orWhere('model', 'ilike', "%{$this->search}%");
            }))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->rackFilter > 0, fn ($q) => $q->where('rack_id', $this->rackFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.equipment.index', [
            'equipment' => $equipment,
            'racks' => Rack::query()->with('room')->orderBy('name')->get(),
            'rooms' => Room::query()->with('site')->orderBy('name')->get(),
            'types' => EquipmentType::cases(),
            'statuses' => EquipmentStatus::cases(),
        ]);
    }
}
