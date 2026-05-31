<?php

declare(strict_types=1);

namespace App\Livewire\WifiNetworks;

use App\Actions\WifiNetworks\AttachClient;
use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\WifiAssociation;
use App\Models\WifiNetwork;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public WifiNetwork $network;

    public bool $showAttachForm = false;

    public int $clientEquipmentId = 0;

    public int $clientInterfaceId = 0;

    public bool $createNewInterface = true;

    public function mount(WifiNetwork $network): void
    {
        $this->authorize('view', $network);
        $this->network = $network->loadMissing('broadcasters.equipment');
    }

    public function openAttach(): void
    {
        $this->authorize('update', $this->network);
        $this->reset(['clientEquipmentId', 'clientInterfaceId', 'createNewInterface']);
        $this->createNewInterface = true;
        $this->showAttachForm = true;
    }

    public function attach(AttachClient $action): void
    {
        $this->authorize('update', $this->network);
        if ($this->clientEquipmentId <= 0) {
            $this->addError('clientEquipmentId', __('wifi.err_client_required'));

            return;
        }

        /** @var Equipment $client */
        $client = Equipment::query()->findOrFail($this->clientEquipmentId);

        $iface = null;
        if (! $this->createNewInterface) {
            if ($this->clientInterfaceId <= 0) {
                $this->addError('clientInterfaceId', __('wifi.err_iface_required'));

                return;
            }
            $iface = NetworkInterface::query()
                ->where('equipment_id', $client->getKey())
                ->where('media', InterfaceMedia::Wireless->value)
                ->findOrFail($this->clientInterfaceId);
        }

        $action->execute($this->network, $client, $iface);

        $this->showAttachForm = false;
        $this->dispatch('toast', type: 'success', message: __('wifi.toast_attached'));
    }

    public function detach(int $associationId): void
    {
        $this->authorize('update', $this->network);
        $assoc = WifiAssociation::query()
            ->where('wifi_network_id', $this->network->getKey())
            ->findOrFail($associationId);
        $assoc->delete();
        $this->dispatch('toast', type: 'success', message: __('wifi.toast_detached'));
    }

    public function render(): View
    {
        $associations = WifiAssociation::query()
            ->where('wifi_network_id', $this->network->getKey())
            ->with(['clientInterface.equipment'])
            ->get();

        // Eligible client equipment list: not already associated, in current
        // tenant scope (global scope handles that), excluding the AP-class
        // devices that are typically broadcasters (visual de-cluttering).
        $alreadyClientIds = $associations
            ->map(fn ($a) => optional($a->clientInterface)->equipment_id)
            ->filter()
            ->all();
        $clients = Equipment::query()
            ->whereNotIn('id', $alreadyClientIds)
            ->whereNotIn('type', [
                EquipmentType::PatchPanel->value,
                EquipmentType::WallOutlet->value,
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $clientWirelessIfaces = [];
        if ($this->clientEquipmentId > 0) {
            $clientWirelessIfaces = NetworkInterface::query()
                ->where('equipment_id', $this->clientEquipmentId)
                ->where('media', InterfaceMedia::Wireless->value)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return view('livewire.wifi-networks.show', [
            'associations' => $associations,
            'clients' => $clients,
            'clientWirelessIfaces' => $clientWirelessIfaces,
        ]);
    }
}
