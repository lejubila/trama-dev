<?php

declare(strict_types=1);

namespace App\Livewire\Vpn;

use App\Models\VpnSiteToSite;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SiteToSiteShow extends Component
{
    public VpnSiteToSite $vpn;

    public function mount(VpnSiteToSite $vpn): void
    {
        $this->authorize('view', $vpn);
        $this->vpn = $vpn->loadMissing(['endpointAInterface.equipment', 'endpointBInterface.equipment']);
    }

    public function render(): View
    {
        return view('livewire.vpn.site-to-site-show');
    }
}
