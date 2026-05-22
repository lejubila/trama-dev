<?php

declare(strict_types=1);

namespace App\Livewire\Audit;

use App\Livewire\Concerns\RemembersFilters;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use OwenIt\Auditing\Models\Audit;

#[Layout('layouts.app')]
class Trail extends Component
{
    use RemembersFilters, WithPagination;

    #[Url(except: '')]
    public string $modelFilter = '';

    #[Url(except: '')]
    public string $eventFilter = '';

    public function updatingModelFilter(): void
    {
        $this->resetPage();
    }

    public function updatingEventFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int, string>
     */
    protected function rememberedFilters(): array
    {
        return ['modelFilter', 'eventFilter'];
    }

    public function render(): View
    {
        // Audit model isn't BelongsToTenant, so we filter manually on tenant_id.
        $tenantId = TenantContext::id();

        // Note: lazy-load `user` in the view rather than eager-loading here.
        // owen-it/laravel-auditing exposes the relation via the trait, but it's
        // not visible to static analysis on the Audit model.
        $audits = Audit::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->when($this->modelFilter !== '', fn ($q) => $q->where('auditable_type', $this->modelFilter))
            ->when($this->eventFilter !== '', fn ($q) => $q->where('event', $this->eventFilter))
            ->orderByDesc('id')
            ->paginate(50);

        return view('livewire.audit.trail', [
            'audits' => $audits,
            'modelTypes' => [
                'App\\Models\\Equipment' => 'Equipment',
                'App\\Models\\NetworkInterface' => 'NetworkInterface',
                'App\\Models\\Connection' => 'Connection',
            ],
            'events' => ['created', 'updated', 'deleted', 'restored'],
        ]);
    }
}
