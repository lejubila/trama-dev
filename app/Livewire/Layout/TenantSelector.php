<?php

declare(strict_types=1);

namespace App\Livewire\Layout;

use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class TenantSelector extends Component
{
    public function render(): View
    {
        $user = auth()->user();

        /** @var Collection<int, Tenant> $tenants */
        $tenants = $user
            ? $user->tenants()->orderBy('name')->get()
            : new Collection;

        return view('livewire.layout.tenant-selector', [
            'tenants' => $tenants,
            'currentTenantId' => $user?->current_tenant_id,
        ]);
    }
}
