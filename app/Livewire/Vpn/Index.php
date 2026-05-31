<?php

declare(strict_types=1);

namespace App\Livewire\Vpn;

use App\Enums\EquipmentType;
use App\Enums\VpnProtocol;
use App\Models\NetworkInterface;
use App\Models\VpnRemoteAccess;
use App\Models\VpnSiteToSite;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    // --- form state shared between Remote-access and Site-to-Site ---------
    public string $formType = '';        // '' | 'remote' | 'site'

    public ?int $editingId = null;

    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('required|string|in:wireguard,openvpn,ipsec,l2tp,pptp,ssl_vpn,gre,other')]
    public string $protocol = 'wireguard';

    /** Remote-access only. */
    public int $firewallInterfaceId = 0;

    /** Site-to-site only. */
    public int $endpointAInterfaceId = 0;

    public int $endpointBInterfaceId = 0;

    /** @var string CSV of VLAN IDs (Remote-access OR site-A). */
    public string $routedVlans = '';

    /** Site-to-site only. */
    public string $routedVlansB = '';

    public string $notes = '';

    public function openCreateRemote(): void
    {
        $this->authorize('create', VpnRemoteAccess::class);
        $this->resetForm('remote');
    }

    public function openEditRemote(int $id): void
    {
        $vpn = VpnRemoteAccess::query()->findOrFail($id);
        $this->authorize('update', $vpn);
        $this->resetForm('remote', $id);
        $this->name = $vpn->name;
        $this->protocol = $vpn->protocol?->value ?? 'wireguard';
        $this->firewallInterfaceId = (int) $vpn->firewall_interface_id;
        $this->routedVlans = $this->vlansToCsv($vpn->routed_vlans);
        $this->notes = (string) ($vpn->notes ?? '');
    }

    public function openCreateSite(): void
    {
        $this->authorize('create', VpnSiteToSite::class);
        $this->resetForm('site');
    }

    public function openEditSite(int $id): void
    {
        $vpn = VpnSiteToSite::query()->findOrFail($id);
        $this->authorize('update', $vpn);
        $this->resetForm('site', $id);
        $this->name = $vpn->name;
        $this->protocol = $vpn->protocol?->value ?? 'ipsec';
        $this->endpointAInterfaceId = (int) $vpn->endpoint_a_interface_id;
        $this->endpointBInterfaceId = (int) $vpn->endpoint_b_interface_id;
        $this->routedVlans = $this->vlansToCsv($vpn->routed_vlans_a);
        $this->routedVlansB = $this->vlansToCsv($vpn->routed_vlans_b);
        $this->notes = (string) ($vpn->notes ?? '');
    }

    private function resetForm(string $type, ?int $editingId = null): void
    {
        $this->reset(['name', 'firewallInterfaceId', 'endpointAInterfaceId', 'endpointBInterfaceId', 'routedVlans', 'routedVlansB', 'notes']);
        $this->resetErrorBag();
        $this->editingId = $editingId;
        $this->formType = $type;
        if ($editingId === null) {
            $this->protocol = $type === 'remote' ? 'wireguard' : 'ipsec';
        }
    }

    public function save(): void
    {
        $this->validate();

        if ($this->formType === 'remote') {
            if ($this->firewallInterfaceId <= 0) {
                $this->addError('firewallInterfaceId', __('vpn.err_fw_iface_required'));

                return;
            }
            $payload = [
                'name' => $this->name,
                'protocol' => $this->protocol,
                'firewall_interface_id' => $this->firewallInterfaceId,
                'routed_vlans' => $this->csvToVlans($this->routedVlans),
                'notes' => $this->notes !== '' ? $this->notes : null,
            ];
            if ($this->editingId !== null) {
                $vpn = VpnRemoteAccess::query()->findOrFail($this->editingId);
                $this->authorize('update', $vpn);
                $vpn->update($payload);
                $message = __('vpn.toast_updated');
            } else {
                $this->authorize('create', VpnRemoteAccess::class);
                VpnRemoteAccess::create($payload);
                $message = __('vpn.toast_created');
            }
        } elseif ($this->formType === 'site') {
            if ($this->endpointAInterfaceId <= 0 || $this->endpointBInterfaceId <= 0) {
                $this->addError('endpointAInterfaceId', __('vpn.err_endpoints_required'));

                return;
            }
            if ($this->endpointAInterfaceId === $this->endpointBInterfaceId) {
                $this->addError('endpointBInterfaceId', __('vpn.err_endpoints_distinct'));

                return;
            }
            $payload = [
                'name' => $this->name,
                'protocol' => $this->protocol,
                'endpoint_a_interface_id' => $this->endpointAInterfaceId,
                'endpoint_b_interface_id' => $this->endpointBInterfaceId,
                'routed_vlans_a' => $this->csvToVlans($this->routedVlans),
                'routed_vlans_b' => $this->csvToVlans($this->routedVlansB),
                'notes' => $this->notes !== '' ? $this->notes : null,
            ];
            if ($this->editingId !== null) {
                $vpn = VpnSiteToSite::query()->findOrFail($this->editingId);
                $this->authorize('update', $vpn);
                $vpn->update($payload);
                $message = __('vpn.toast_updated');
            } else {
                $this->authorize('create', VpnSiteToSite::class);
                VpnSiteToSite::create($payload);
                $message = __('vpn.toast_created');
            }
        } else {
            return;
        }

        $this->formType = '';
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function deleteRemote(int $id): void
    {
        $vpn = VpnRemoteAccess::query()->findOrFail($id);
        $this->authorize('delete', $vpn);
        $vpn->delete();
        $this->dispatch('toast', type: 'success', message: __('vpn.toast_deleted'));
    }

    public function deleteSite(int $id): void
    {
        $vpn = VpnSiteToSite::query()->findOrFail($id);
        $this->authorize('delete', $vpn);
        $vpn->delete();
        $this->dispatch('toast', type: 'success', message: __('vpn.toast_deleted'));
    }

    /**
     * Firewall interfaces of the current tenant — eligible endpoints
     * for both VPN types.
     */
    private function firewallInterfaces()
    {
        return NetworkInterface::query()
            ->with('equipment')
            ->whereHas('equipment', fn ($q) => $q->where('type', EquipmentType::Firewall->value))
            ->orderBy('equipment_id')
            ->orderBy('name');
    }

    /**
     * @param  array<int>|null  $list
     */
    private function vlansToCsv(?array $list): string
    {
        return is_array($list) ? implode(',', $list) : '';
    }

    /**
     * @return array<int>|null
     */
    private function csvToVlans(string $csv): ?array
    {
        $csv = trim($csv);
        if ($csv === '') {
            return null;
        }
        $values = [];
        foreach (explode(',', $csv) as $tok) {
            $n = (int) trim($tok);
            if ($n > 0 && $n <= 4094) {
                $values[] = $n;
            }
        }

        return $values !== [] ? array_values(array_unique($values)) : null;
    }

    public function render(): View
    {
        return view('livewire.vpn.index', [
            'remotes' => VpnRemoteAccess::query()
                ->withCount('clients')
                ->with('firewallInterface.equipment')
                ->orderBy('name')
                ->get(),
            'sites' => VpnSiteToSite::query()
                ->with(['endpointAInterface.equipment', 'endpointBInterface.equipment'])
                ->orderBy('name')
                ->get(),
            'firewallInterfaces' => $this->firewallInterfaces()->get(['id', 'name', 'equipment_id']),
            'protocols' => VpnProtocol::cases(),
        ]);
    }
}
