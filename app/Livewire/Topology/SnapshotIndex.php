<?php

declare(strict_types=1);

namespace App\Livewire\Topology;

use App\Livewire\Concerns\RemembersFilters;
use App\Models\Site;
use App\Models\TopologySnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class SnapshotIndex extends Component
{
    use RemembersFilters, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $dateFrom = '';

    #[Url(except: '')]
    public string $dateTo = '';

    #[Url(except: 0)]
    public int $siteFilter = 0;

    public bool $showEditForm = false;

    public ?int $editingId = null;

    public string $editTitle = '';

    public string $editDescription = '';

    public string $editSnapshotDate = '';

    public function mount(): void
    {
        $this->authorize('viewAny', TopologySnapshot::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingSiteFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int, string>
     */
    protected function rememberedFilters(): array
    {
        return ['search', 'dateFrom', 'dateTo', 'siteFilter'];
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'dateFrom', 'dateTo', 'siteFilter']);
        $this->persistFilters();
        $this->resetPage();
    }

    public function openEdit(int $id): void
    {
        $snap = TopologySnapshot::query()->findOrFail($id);
        $this->authorize('update', $snap);

        $this->editingId = $snap->getKey();
        $this->editTitle = (string) $snap->title;
        $this->editDescription = (string) ($snap->description ?? '');
        $this->editSnapshotDate = $snap->snapshot_date?->toDateString() ?? now()->toDateString();
        $this->resetErrorBag();
        $this->showEditForm = true;
    }

    public function closeEdit(): void
    {
        $this->showEditForm = false;
        $this->editingId = null;
        $this->editTitle = '';
        $this->editDescription = '';
        $this->editSnapshotDate = '';
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editTitle' => 'required|string|max:255',
            'editDescription' => 'nullable|string|max:5000',
            'editSnapshotDate' => 'required|date',
        ]);

        if ($this->editingId === null) {
            return;
        }

        $snap = TopologySnapshot::query()->findOrFail($this->editingId);
        $this->authorize('update', $snap);

        $snap->update([
            'title' => $this->editTitle,
            'description' => $this->editDescription !== '' ? $this->editDescription : null,
            'snapshot_date' => $this->editSnapshotDate,
        ]);

        $this->closeEdit();
        $this->dispatch('toast', type: 'success', message: 'Snapshot aggiornato.');
    }

    public function delete(int $id): void
    {
        $snap = TopologySnapshot::query()->findOrFail($id);
        $this->authorize('delete', $snap);

        if ($snap->image_path !== '' && Storage::disk('public')->exists($snap->image_path)) {
            Storage::disk('public')->delete($snap->image_path);
        }
        $snap->delete();
        $this->dispatch('toast', type: 'success', message: 'Snapshot eliminato.');
    }

    public function render(): View
    {
        $snapshots = TopologySnapshot::query()
            ->with('creator')
            ->when($this->search !== '', fn ($q) => $q->where('title', 'ilike', "%{$this->search}%"))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('snapshot_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('snapshot_date', '<=', $this->dateTo))
            ->when($this->siteFilter > 0, fn ($q) => $q->whereRaw("(view_state->>'siteId')::int = ?", [$this->siteFilter]))
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.topology.snapshot-index', [
            'snapshots' => $snapshots,
            'sites' => Site::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
