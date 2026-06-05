<?php

declare(strict_types=1);

namespace App\Livewire\Vpn;

use App\Enums\EquipmentType;
use App\Enums\VpnProtocol;
use App\Enums\VpnRoutingMode;
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

    /** Remote-access only: 'routed' | 'bridged'. */
    #[Validate('required|string|in:routed,bridged')]
    public string $routingMode = 'routed';

    /** Remote-access only: IPv4 of the client subnet (without prefix). */
    public string $clientNetworkIp = '';

    /** Remote-access only: CIDR prefix length 0..32. -1 = unset. */
    public int $clientNetworkPrefix = 24;

    /** Site-to-site only. */
    public int $endpointAInterfaceId = 0;

    public int $endpointBInterfaceId = 0;

    /** @var string CSV of VLAN IDs (Remote-access OR site-A). */
    public string $routedVlans = '';

    /** Site-to-site only. */
    public string $routedVlansB = '';

    /** Site-to-site only: CSV of CIDR strings exported by side A. */
    public string $routedNetworksA = '';

    /** Site-to-site only: CSV of CIDR strings exported by side B. */
    public string $routedNetworksB = '';

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
        $this->routingMode = $vpn->routing_mode?->value ?? 'routed';
        [$this->clientNetworkIp, $this->clientNetworkPrefix] = $this->splitCidr($vpn->client_network_cidr);
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
        $this->routedNetworksA = $this->cidrListToCsv($vpn->routed_networks_a);
        $this->routedNetworksB = $this->cidrListToCsv($vpn->routed_networks_b);
        $this->notes = (string) ($vpn->notes ?? '');
    }

    private function resetForm(string $type, ?int $editingId = null): void
    {
        $this->reset(['name', 'firewallInterfaceId', 'endpointAInterfaceId', 'endpointBInterfaceId', 'routedVlans', 'routedVlansB', 'routedNetworksA', 'routedNetworksB', 'notes', 'clientNetworkIp']);
        $this->routingMode = 'routed';
        $this->clientNetworkPrefix = 24;
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
            $cidr = $this->buildCidr($this->clientNetworkIp, $this->clientNetworkPrefix);
            if ($cidr === false) {
                $this->addError('clientNetworkIp', __('vpn.err_cidr_invalid'));

                return;
            }
            $payload = [
                'name' => $this->name,
                'protocol' => $this->protocol,
                'firewall_interface_id' => $this->firewallInterfaceId,
                'routing_mode' => $this->routingMode,
                'client_network_cidr' => $cidr,
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
                'routed_networks_a' => $this->csvToCidrList($this->routedNetworksA),
                'routed_networks_b' => $this->csvToCidrList($this->routedNetworksB),
                'notes' => $this->notes !== '' ? $this->notes : null,
            ];
            $invalid = $this->firstInvalidCidr($this->routedNetworksA);
            if ($invalid !== null) {
                $this->addError('routedNetworksA', __('vpn.err_cidr_list_invalid', ['value' => $invalid]));

                return;
            }
            $invalid = $this->firstInvalidCidr($this->routedNetworksB);
            if ($invalid !== null) {
                $this->addError('routedNetworksB', __('vpn.err_cidr_list_invalid', ['value' => $invalid]));

                return;
            }
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
     * Split a stored CIDR ("10.0.0.0/24") into [ip, prefix]. Empty / null
     * input falls back to the default "" + /24 so the form opens with a
     * neutral state for new rows.
     *
     * @return array{0:string,1:int}
     */
    private function splitCidr(?string $cidr): array
    {
        if (! is_string($cidr) || $cidr === '' || ! str_contains($cidr, '/')) {
            return ['', 24];
        }
        [$ip, $p] = explode('/', $cidr, 2);
        $p = (int) $p;

        return [$ip, ($p >= 0 && $p <= 32) ? $p : 24];
    }

    /**
     * Validate + normalise the IP/prefix pair back into a CIDR string.
     * Returns null when both fields are empty (treated as "no network").
     * Returns false when the IP is not a valid IPv4 — the caller surfaces
     * a form error in that case.
     */
    private function buildCidr(string $ip, int $prefix): string|false|null
    {
        $ip = trim($ip);
        if ($ip === '') {
            return null;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        $prefix = max(0, min(32, $prefix));

        return $ip.'/'.$prefix;
    }

    /**
     * Build the prefix-length combobox options: every /N from 0 to 32 with
     * its dotted-quad netmask and 32-bit binary view, grouped in octets.
     *
     * @return list<array{prefix:int,netmask:string,bits:string}>
     */
    private function prefixOptions(): array
    {
        $out = [];
        for ($p = 0; $p <= 32; $p++) {
            $mask = $p === 0 ? 0 : (0xFFFFFFFF << (32 - $p)) & 0xFFFFFFFF;
            $netmask = long2ip($mask);
            $bits = str_pad(decbin($mask), 32, '0', STR_PAD_LEFT);
            $bitsGrouped = implode('.', str_split($bits, 8));
            $out[] = ['prefix' => $p, 'netmask' => $netmask, 'bits' => $bitsGrouped];
        }

        return $out;
    }

    /**
     * @param  array<string>|null  $list
     */
    private function cidrListToCsv(?array $list): string
    {
        return is_array($list) ? implode("\n", $list) : '';
    }

    /**
     * Parse a CSV / whitespace-separated list of CIDRs and return only the
     * syntactically valid ones (cheap pre-filter; the firstInvalidCidr()
     * call surfaces a form error for tokens that *look* like a CIDR but
     * fail validation).
     *
     * @return array<string>|null
     */
    private function csvToCidrList(string $csv): ?array
    {
        $values = [];
        foreach (preg_split('/[\s,;]+/', trim($csv)) ?: [] as $tok) {
            $tok = trim($tok);
            if ($tok === '' || ! $this->isValidCidr($tok)) {
                continue;
            }
            $values[] = $tok;
        }

        return $values !== [] ? array_values(array_unique($values)) : null;
    }

    /**
     * Return the first token from the CSV input that *looks* like an
     * attempted CIDR (has a "/") but doesn't parse — surfaced as a
     * form-level error so the user sees what to fix.
     */
    private function firstInvalidCidr(string $csv): ?string
    {
        foreach (preg_split('/[\s,;]+/', trim($csv)) ?: [] as $tok) {
            $tok = trim($tok);
            if ($tok === '') {
                continue;
            }
            if (! $this->isValidCidr($tok)) {
                return $tok;
            }
        }

        return null;
    }

    private function isValidCidr(string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return false;
        }
        [$ip, $p] = explode('/', $cidr, 2);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        if (! ctype_digit($p)) {
            return false;
        }
        $p = (int) $p;

        return $p >= 0 && $p <= 32;
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
            'routingModes' => VpnRoutingMode::cases(),
            'prefixOptions' => $this->prefixOptions(),
        ]);
    }
}
