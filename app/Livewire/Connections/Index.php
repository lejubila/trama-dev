<?php

declare(strict_types=1);

namespace App\Livewire\Connections;

use App\Enums\ConnectionStatus;
use App\Models\Connection;
use Illuminate\Contracts\View\View;
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
    public string $statusFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
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
            ->with(['fromInterface.equipment', 'toInterface.equipment'])
            ->when($this->search !== '', fn ($q) => $q->where('cable_label', 'ilike', "%{$this->search}%"))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.connections.index', [
            'connections' => $connections,
            'statuses' => ConnectionStatus::cases(),
        ]);
    }
}
