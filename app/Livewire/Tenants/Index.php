<?php

declare(strict_types=1);

namespace App\Livewire\Tenants;

use App\Actions\Tenancy\CreateTenant;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    #[Validate('required|string|max:150')]
    public string $name = '';

    #[Validate('nullable|string|alpha_dash|max:80|unique:tenants,slug')]
    public string $slug = '';

    #[Validate('nullable|string|max:255')]
    public string $domain = '';

    /** When ?new=1 lands on the page (empty-state onboarding), auto-open the modal. */
    #[Url(as: 'new')]
    public ?string $autoOpen = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Tenant::class);

        if ($this->autoOpen === '1') {
            $this->openCreate();
        }
    }

    public function openCreate(): void
    {
        $this->authorize('create', Tenant::class);
        $this->reset(['name', 'slug', 'domain']);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(CreateTenant $action): void
    {
        $this->authorize('create', Tenant::class);
        $this->validate();

        $tenant = $action->execute(auth()->user(), [
            'name' => $this->name,
            'slug' => $this->slug !== '' ? $this->slug : null,
            'domain' => $this->domain !== '' ? $this->domain : null,
        ]);

        // The action already flipped current_tenant_id; mirror it in this
        // request so the next render sees the right tenant scope.
        TenantContext::setId($tenant->getKey());

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: "Cliente \"{$tenant->name}\" creato.");
        $this->redirectRoute('tenants.manage', ['tenant' => $tenant], navigate: true);
    }

    public function switchTo(int $id): void
    {
        $tenant = Tenant::query()->findOrFail($id);
        $this->authorize('view', $tenant);

        $user = auth()->user();
        $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
        TenantContext::setId($tenant->getKey());

        $this->dispatch('toast', type: 'success', message: "Cliente attivo: {$tenant->name}.");
        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render(): View
    {
        $user = auth()->user();
        // Admins and tecnici see every tenant; clienti only their assigned ones.
        $tenants = match (true) {
            $user === null => collect(),
            $user->canManageData() => Tenant::query()->orderBy('name')->get(),
            default => $user->tenants()->orderBy('name')->get(),
        };

        return view('livewire.tenants.index', [
            'tenants' => $tenants,
            'currentTenantId' => $user?->current_tenant_id,
        ]);
    }
}
