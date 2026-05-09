<?php

declare(strict_types=1);

namespace App\Livewire\Equipment;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\Rack;
use App\Services\RackPlacementService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    #[Url(except: 0)]
    public int $rackFilter = 0;

    #[Url(except: '')]
    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $rackId = null;

    public string $name = '';

    public string $type = 'switch';

    public string $vendor = '';

    public string $modelName = '';

    public string $serial = '';

    public bool $mounted = false;

    public ?int $positionUStart = null;

    public ?int $positionUHeight = 1;

    public string $status = 'active';

    public string $description = '';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rackId' => 'nullable|exists:racks,id',
            'name' => 'required|string|max:150',
            'type' => ['required', Rule::in(array_column(EquipmentType::cases(), 'value'))],
            'vendor' => 'nullable|string|max:80',
            'modelName' => 'nullable|string|max:120',
            'serial' => 'nullable|string|max:120',
            'mounted' => 'boolean',
            'positionUStart' => 'nullable|integer|min:1|max:60',
            'positionUHeight' => 'nullable|integer|min:1|max:60',
            'status' => ['required', Rule::in(array_column(EquipmentStatus::cases(), 'value'))],
            'description' => 'nullable|string|max:5000',
        ];
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

    public function openCreate(): void
    {
        $this->authorize('create', Equipment::class);
        $this->reset(['editingId', 'rackId', 'name', 'vendor', 'modelName', 'serial', 'mounted', 'positionUStart', 'description']);
        $this->type = 'switch';
        $this->status = 'active';
        $this->positionUHeight = 1;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $eq = Equipment::query()->findOrFail($id);
        $this->authorize('update', $eq);

        $this->editingId = $eq->getKey();
        $this->rackId = $eq->rack_id;
        $this->name = $eq->name;
        $this->type = $eq->type?->value ?? 'switch';
        $this->vendor = (string) ($eq->vendor ?? '');
        $this->modelName = (string) ($eq->model ?? '');
        $this->serial = (string) ($eq->serial ?? '');
        $this->mounted = (bool) $eq->mounted;
        $this->positionUStart = $eq->position_u_start;
        $this->positionUHeight = $eq->position_u_height ?? 1;
        $this->status = $eq->status?->value ?? 'active';
        $this->description = (string) ($eq->description ?? '');
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
            if ($this->positionUStart === null || $this->positionUHeight === null) {
                $this->addError('positionUStart', 'Posizione U obbligatoria per dispositivi montati.');

                return;
            }

            $rack = Rack::query()->findOrFail($this->rackId);
            $excluding = $this->editingId ? Equipment::query()->find($this->editingId) : null;

            if (! $placement->canPlace($rack, $this->positionUStart, $this->positionUHeight, $excluding)) {
                $this->addError('positionUStart', 'Conflitto con altro dispositivo o posizione fuori dal rack.');

                return;
            }
        }

        $payload = [
            'rack_id' => $this->rackId,
            'name' => $this->name,
            'type' => EquipmentType::from($this->type),
            'vendor' => $this->vendor !== '' ? $this->vendor : null,
            'model' => $this->modelName !== '' ? $this->modelName : null,
            'serial' => $this->serial !== '' ? $this->serial : null,
            'mounted' => $this->mounted,
            'position_u_start' => $this->mounted ? $this->positionUStart : null,
            'position_u_height' => $this->mounted ? $this->positionUHeight : null,
            'status' => EquipmentStatus::from($this->status),
            'description' => $this->description !== '' ? $this->description : null,
        ];

        if ($this->editingId !== null) {
            $eq = Equipment::query()->findOrFail($this->editingId);
            $this->authorize('update', $eq);
            $eq->update($payload);
            $this->dispatch('toast', type: 'success', message: 'Dispositivo aggiornato.');
        } else {
            $this->authorize('create', Equipment::class);
            Equipment::create($payload);
            $this->dispatch('toast', type: 'success', message: 'Dispositivo creato.');
        }

        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        $eq = Equipment::query()->findOrFail($id);
        $this->authorize('delete', $eq);
        $eq->delete();
        $this->dispatch('toast', type: 'success', message: 'Dispositivo rimosso.');
    }

    public function render(): View
    {
        $equipment = Equipment::query()
            ->with('rack.room.site')
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
            'racks' => Rack::query()->orderBy('name')->get(),
            'types' => EquipmentType::cases(),
            'statuses' => EquipmentStatus::cases(),
        ]);
    }
}
