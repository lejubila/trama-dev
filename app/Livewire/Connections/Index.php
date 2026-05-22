<?php

declare(strict_types=1);

namespace App\Livewire\Connections;

use App\Enums\ConnectionStatus;
use App\Livewire\Concerns\RemembersFilters;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use RemembersFilters, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: 0)]
    public int $equipmentFilter = 0;

    #[Url(except: '')]
    public string $portFilter = '';

    #[Url(except: 0)]
    public int $tagFilter = 0;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingEquipmentFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPortFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTagFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int, string>
     */
    protected function rememberedFilters(): array
    {
        return ['search', 'statusFilter', 'equipmentFilter', 'portFilter', 'tagFilter'];
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'equipmentFilter', 'portFilter', 'tagFilter']);
        $this->persistFilters();
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $c = Connection::query()->findOrFail($id);
        $this->authorize('delete', $c);
        $c->delete();
        $this->dispatch('toast', type: 'success', message: 'Connessione rimossa.');
    }

    public function decommission(int $id): void
    {
        $c = Connection::query()->findOrFail($id);
        $this->authorize('update', $c);
        $c->update(['status' => ConnectionStatus::Decommissioned]);
        $this->dispatch('toast', type: 'success', message: 'Connessione dismessa.');
    }

    public function render(): View
    {
        $connections = Connection::query()
            ->with(['fromInterface.equipment', 'toInterface.equipment', 'tags'])
            ->when($this->search !== '', fn ($q) => $q->where('cable_label', 'ilike', "%{$this->search}%"))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->equipmentFilter > 0, fn ($q) => $q->where(function ($qq): void {
                $qq->whereHas('fromInterface', fn ($qi) => $qi->where('equipment_id', $this->equipmentFilter))
                    ->orWhereHas('toInterface', fn ($qi) => $qi->where('equipment_id', $this->equipmentFilter));
            }))
            ->when($this->portFilter !== '', fn ($q) => $q->where(function ($qq): void {
                $qq->whereHas('fromInterface', fn ($qi) => $qi->where('name', 'ilike', "%{$this->portFilter}%"))
                    ->orWhereHas('toInterface', fn ($qi) => $qi->where('name', 'ilike', "%{$this->portFilter}%"));
            }))
            ->when($this->tagFilter > 0, fn ($q) => $q->whereHas('tags', fn ($qt) => $qt->where('tags.id', $this->tagFilter)))
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.connections.index', [
            'connections' => $connections,
            'statuses' => ConnectionStatus::cases(),
            'equipmentList' => Equipment::query()->orderBy('name')->get(['id', 'name']),
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }
}
