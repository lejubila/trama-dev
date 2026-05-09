<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Models\Site;
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

    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:150')]
    public string $name = '';

    #[Validate('nullable|string|max:255')]
    public string $address = '';

    #[Validate('nullable|string|max:2000')]
    public string $notes = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Site::class);
        $this->reset(['editingId', 'name', 'address', 'notes']);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $site = Site::query()->findOrFail($id);
        $this->authorize('update', $site);

        $this->editingId = $site->getKey();
        $this->name = $site->name;
        $this->address = (string) ($site->address ?? '');
        $this->notes = (string) ($site->notes ?? '');
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId !== null) {
            $site = Site::query()->findOrFail($this->editingId);
            $this->authorize('update', $site);
            $site->update($this->payload());
            $message = 'Sede aggiornata.';
        } else {
            $this->authorize('create', Site::class);
            Site::create($this->payload());
            $message = 'Sede creata.';
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: $message);
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $site = Site::query()->findOrFail($id);
        $this->authorize('delete', $site);
        $site->delete();
        $this->dispatch('toast', type: 'success', message: 'Sede rimossa.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => $this->name,
            'address' => $this->address !== '' ? $this->address : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];
    }

    public function render(): View
    {
        $sites = Site::query()
            ->withCount('rooms')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'ilike', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.sites.index', [
            'sites' => $sites,
        ]);
    }
}
