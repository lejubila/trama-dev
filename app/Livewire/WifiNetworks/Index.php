<?php

declare(strict_types=1);

namespace App\Livewire\WifiNetworks;

use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Livewire\Concerns\RemembersFilters;
use App\Models\NetworkInterface;
use App\Models\WifiNetwork;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use RemembersFilters, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:64')]
    public string $ssid = '';

    #[Validate('nullable|string|max:32')]
    public string $securityType = '';

    #[Validate('nullable|integer|min:1|max:4094')]
    public ?int $vlanIdField = null;

    public bool $hiddenSsid = false;

    #[Validate('nullable|string|max:2000')]
    public string $notes = '';

    /** @var array<int, int> Selected broadcaster NetworkInterface IDs. */
    public array $broadcasterIds = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int, string>
     */
    protected function rememberedFilters(): array
    {
        return ['search'];
    }

    public function openCreate(): void
    {
        $this->authorize('create', WifiNetwork::class);
        $this->reset(['editingId', 'ssid', 'securityType', 'vlanIdField', 'hiddenSsid', 'notes', 'broadcasterIds']);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $net = WifiNetwork::query()->with('broadcasters')->findOrFail($id);
        $this->authorize('update', $net);

        $this->editingId = $net->getKey();
        $this->ssid = $net->ssid;
        $this->securityType = (string) ($net->security_type ?? '');
        $this->vlanIdField = $net->vlan_id;
        $this->hiddenSsid = (bool) $net->hidden_ssid;
        $this->notes = (string) ($net->notes ?? '');
        $this->broadcasterIds = $net->broadcasters->pluck('id')->all();
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'ssid' => $this->ssid,
            'security_type' => $this->securityType !== '' ? $this->securityType : null,
            'vlan_id' => $this->vlanIdField,
            'hidden_ssid' => $this->hiddenSsid,
            'notes' => $this->notes !== '' ? $this->notes : null,
        ];

        if ($this->editingId !== null) {
            $net = WifiNetwork::query()->findOrFail($this->editingId);
            $this->authorize('update', $net);
            $net->update($payload);
            $message = __('wifi.toast_updated');
        } else {
            $this->authorize('create', WifiNetwork::class);
            $net = WifiNetwork::create($payload);
            $message = __('wifi.toast_created');
        }

        // Broadcasters: sync the pivot scoped to wireless interfaces of the
        // active tenant. Bad IDs are silently dropped at sync time because
        // the source list is built from the tenant query below.
        $allowed = $this->availableBroadcasters()->pluck('id')->all();
        $sync = array_values(array_intersect(array_map('intval', $this->broadcasterIds), $allowed));
        $net->broadcasters()->sync($sync);

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: $message);
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $net = WifiNetwork::query()->findOrFail($id);
        $this->authorize('delete', $net);
        $net->delete();
        $this->dispatch('toast', type: 'success', message: __('wifi.toast_deleted'));
    }

    /**
     * Wireless interfaces owned by AP/controller devices of the active
     * tenant — eligible broadcasters for the SSID.
     */
    private function availableBroadcasters()
    {
        return NetworkInterface::query()
            ->with('equipment')
            ->where('media', InterfaceMedia::Wireless->value)
            ->whereHas('equipment', fn ($q) => $q->whereIn('type', [
                EquipmentType::AccessPoint->value,
                EquipmentType::Controller->value,
            ]))
            ->orderBy('equipment_id')
            ->orderBy('name');
    }

    public function render(): View
    {
        $networks = WifiNetwork::query()
            ->withCount(['broadcasters', 'associations'])
            ->when($this->search !== '', fn ($q) => $q->where('ssid', 'ilike', "%{$this->search}%"))
            ->orderBy('ssid')
            ->paginate(20);

        return view('livewire.wifi-networks.index', [
            'networks' => $networks,
            'availableBroadcasters' => $this->availableBroadcasters()->get(['id', 'name', 'equipment_id']),
        ]);
    }
}
