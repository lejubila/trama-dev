<?php

declare(strict_types=1);

namespace App\Livewire\Vpn;

use App\Actions\Vpn\AttachRemoteClient;
use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\VpnRemoteAccess;
use App\Models\VpnRemoteAccessClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RemoteAccessShow extends Component
{
    public VpnRemoteAccess $vpn;

    public bool $showAttachForm = false;

    public int $clientEquipmentId = 0;

    public int $clientInterfaceId = 0;

    public bool $createNewInterface = true;

    public string $username = '';

    public function mount(VpnRemoteAccess $vpn): void
    {
        $this->authorize('view', $vpn);
        $this->vpn = $vpn->loadMissing('firewallInterface.equipment');
    }

    public function openAttach(): void
    {
        $this->authorize('update', $this->vpn);
        $this->reset(['clientEquipmentId', 'clientInterfaceId', 'createNewInterface', 'username']);
        $this->createNewInterface = true;
        $this->showAttachForm = true;
    }

    public function attach(AttachRemoteClient $action): void
    {
        $this->authorize('update', $this->vpn);
        if ($this->clientEquipmentId <= 0) {
            $this->addError('clientEquipmentId', __('vpn.err_client_required'));

            return;
        }

        /** @var Equipment $client */
        $client = Equipment::query()->findOrFail($this->clientEquipmentId);

        $iface = null;
        if (! $this->createNewInterface) {
            if ($this->clientInterfaceId <= 0) {
                $this->addError('clientInterfaceId', __('vpn.err_iface_required'));

                return;
            }
            $iface = NetworkInterface::query()
                ->where('equipment_id', $client->getKey())
                ->where('media', InterfaceMedia::Virtual->value)
                ->findOrFail($this->clientInterfaceId);
        }

        $action->execute($this->vpn, $client, $iface, $this->username !== '' ? $this->username : null);

        $this->showAttachForm = false;
        $this->dispatch('toast', type: 'success', message: __('vpn.toast_attached'));
    }

    public function detach(int $clientId): void
    {
        $this->authorize('update', $this->vpn);
        $row = VpnRemoteAccessClient::query()
            ->where('vpn_remote_access_id', $this->vpn->getKey())
            ->findOrFail($clientId);
        $row->delete();
        $this->dispatch('toast', type: 'success', message: __('vpn.toast_detached'));
    }

    public function render(): View
    {
        $clients = VpnRemoteAccessClient::query()
            ->where('vpn_remote_access_id', $this->vpn->getKey())
            ->with(['clientInterface.equipment'])
            ->get();

        $alreadyIds = $clients->map(fn ($a) => optional($a->clientInterface)->equipment_id)->filter()->all();
        $candidateClients = Equipment::query()
            ->whereNotIn('id', $alreadyIds)
            ->whereNotIn('type', [
                EquipmentType::PatchPanel->value,
                EquipmentType::WallOutlet->value,
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $virtualIfaces = [];
        if ($this->clientEquipmentId > 0) {
            $virtualIfaces = NetworkInterface::query()
                ->where('equipment_id', $this->clientEquipmentId)
                ->where('media', InterfaceMedia::Virtual->value)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return view('livewire.vpn.remote-access-show', [
            'clients' => $clients,
            'candidateClients' => $candidateClients,
            'virtualIfaces' => $virtualIfaces,
        ]);
    }
}
