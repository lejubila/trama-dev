<?php

declare(strict_types=1);

namespace App\Livewire\Layout;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class TenantSelector extends Component
{
    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.layout.tenant-selector', [
            'tenants' => $user?->accessibleTenants()->orderBy('name')->get() ?? collect(),
            'currentTenantId' => $user?->current_tenant_id,
        ]);
    }
}
