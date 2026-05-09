<?php

declare(strict_types=1);

namespace App\Livewire\Equipment;

use App\Enums\InterfaceMedia;
use App\Enums\InterfacePoe;
use App\Enums\InterfaceStatus;
use App\Enums\InterfaceType;
use App\Enums\InterfaceVlanMode;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Equipment $equipment;

    public string $activeTab = 'general';

    // Interface form state
    public bool $showIfForm = false;

    public ?int $editingIfId = null;

    public string $ifName = '';

    public string $ifType = 'ethernet';

    public string $ifMedia = 'copper';

    public ?int $ifSpeedMbps = 1000;

    public string $ifVlanMode = 'access';

    public ?int $ifVlanDefault = 1;

    public string $ifIpAddress = '';

    public string $ifMacAddress = '';

    public string $ifStatus = 'unknown';

    public string $ifPoe = 'none';

    public string $ifDescription = '';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ifName' => 'required|string|max:80',
            'ifType' => ['required', Rule::in(array_column(InterfaceType::cases(), 'value'))],
            'ifMedia' => ['required', Rule::in(array_column(InterfaceMedia::cases(), 'value'))],
            'ifSpeedMbps' => 'nullable|integer|min:1',
            'ifVlanMode' => ['nullable', Rule::in(array_column(InterfaceVlanMode::cases(), 'value'))],
            'ifVlanDefault' => 'nullable|integer|min:1|max:4094',
            'ifIpAddress' => 'nullable|string|max:45',
            'ifMacAddress' => 'nullable|string|max:17',
            'ifStatus' => ['required', Rule::in(array_column(InterfaceStatus::cases(), 'value'))],
            'ifPoe' => ['required', Rule::in(array_column(InterfacePoe::cases(), 'value'))],
            'ifDescription' => 'nullable|string|max:255',
        ];
    }

    public function mount(Equipment $equipment): void
    {
        $this->authorize('view', $equipment);
        $this->equipment = $equipment->load('rack.room.site');
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['general', 'interfaces', 'connections', 'audit'], true) ? $tab : 'general';
    }

    public function openIfCreate(): void
    {
        $this->authorize('create', NetworkInterface::class);
        $this->reset(['editingIfId', 'ifName', 'ifIpAddress', 'ifMacAddress', 'ifDescription']);
        $this->ifType = 'ethernet';
        $this->ifMedia = 'copper';
        $this->ifSpeedMbps = 1000;
        $this->ifVlanMode = 'access';
        $this->ifVlanDefault = 1;
        $this->ifStatus = 'unknown';
        $this->ifPoe = 'none';
        $this->resetErrorBag();
        $this->showIfForm = true;
    }

    public function openIfEdit(int $id): void
    {
        $if = NetworkInterface::query()->findOrFail($id);
        $this->authorize('update', $if);

        $this->editingIfId = $if->getKey();
        $this->ifName = $if->name;
        $this->ifType = $if->type?->value ?? 'ethernet';
        $this->ifMedia = $if->media?->value ?? 'copper';
        $this->ifSpeedMbps = $if->speed_mbps;
        $this->ifVlanMode = $if->vlan_mode?->value ?? 'access';
        $this->ifVlanDefault = $if->vlan_default;
        $this->ifIpAddress = (string) ($if->ip_address ?? '');
        $this->ifMacAddress = (string) ($if->mac_address ?? '');
        $this->ifStatus = $if->status?->value ?? 'unknown';
        $this->ifPoe = $if->poe?->value ?? 'none';
        $this->ifDescription = (string) ($if->description ?? '');
        $this->resetErrorBag();
        $this->showIfForm = true;
    }

    public function saveIf(): void
    {
        $this->validate();

        $payload = [
            'equipment_id' => $this->equipment->getKey(),
            'name' => $this->ifName,
            'type' => InterfaceType::from($this->ifType),
            'media' => InterfaceMedia::from($this->ifMedia),
            'speed_mbps' => $this->ifSpeedMbps,
            'vlan_mode' => InterfaceVlanMode::from($this->ifVlanMode),
            'vlan_default' => $this->ifVlanDefault,
            'ip_address' => $this->ifIpAddress !== '' ? $this->ifIpAddress : null,
            'mac_address' => $this->ifMacAddress !== '' ? $this->ifMacAddress : null,
            'status' => InterfaceStatus::from($this->ifStatus),
            'poe' => InterfacePoe::from($this->ifPoe),
            'description' => $this->ifDescription !== '' ? $this->ifDescription : null,
        ];

        if ($this->editingIfId !== null) {
            $if = NetworkInterface::query()->findOrFail($this->editingIfId);
            $this->authorize('update', $if);
            $if->update($payload);
            $this->dispatch('toast', type: 'success', message: 'Interfaccia aggiornata.');
        } else {
            $this->authorize('create', NetworkInterface::class);
            NetworkInterface::create($payload);
            $this->dispatch('toast', type: 'success', message: 'Interfaccia creata.');
        }

        $this->showIfForm = false;
    }

    public function deleteIf(int $id): void
    {
        $if = NetworkInterface::query()->findOrFail($id);
        $this->authorize('delete', $if);
        $if->delete();
        $this->dispatch('toast', type: 'success', message: 'Interfaccia rimossa.');
    }

    public function render(): View
    {
        return view('livewire.equipment.show', [
            'interfaces' => $this->equipment->interfaces()->orderBy('index')->orderBy('name')->get(),
            'audits' => $this->equipment->audits()->latest()->limit(50)->get(),
        ]);
    }
}
