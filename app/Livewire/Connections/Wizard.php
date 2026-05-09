<?php

declare(strict_types=1);

namespace App\Livewire\Connections;

use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Services\ConnectionService;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Wizard extends Component
{
    public int $step = 1;

    public ?int $fromInterfaceId = null;

    public ?int $toInterfaceId = null;

    public string $cableType = 'utp_cat6';

    public ?string $cableLengthM = null;

    public string $cableLabel = '';

    public string $color = '';

    public string $notes = '';

    public ?string $establishedAt = null;

    public function mount(): void
    {
        $this->authorize('create', Connection::class);
    }

    public function next(): void
    {
        match ($this->step) {
            1 => $this->fromInterfaceId !== null ? $this->step = 2 : $this->addError('fromInterfaceId', 'Scegli l\'interfaccia di partenza.'),
            2 => $this->toInterfaceId !== null ? $this->step = 3 : $this->addError('toInterfaceId', 'Scegli l\'interfaccia di destinazione.'),
            default => null,
        };
    }

    public function back(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function save(ConnectionService $service): void
    {
        $this->validate([
            'fromInterfaceId' => 'required|exists:interfaces,id',
            'toInterfaceId' => 'required|exists:interfaces,id|different:fromInterfaceId',
            'cableType' => 'required|string|max:30',
            'cableLengthM' => 'nullable|numeric|min:0',
            'cableLabel' => 'nullable|string|max:80',
            'color' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:2000',
            'establishedAt' => 'nullable|date',
        ]);

        $a = NetworkInterface::query()->findOrFail($this->fromInterfaceId);
        $b = NetworkInterface::query()->findOrFail($this->toInterfaceId);

        try {
            $conn = $service->connect($a, $b, [
                'cable_type' => $this->cableType,
                'cable_length_m' => $this->cableLengthM !== null && $this->cableLengthM !== '' ? (float) $this->cableLengthM : null,
                'cable_label' => $this->cableLabel !== '' ? $this->cableLabel : null,
                'color' => $this->color !== '' ? $this->color : null,
                'notes' => $this->notes !== '' ? $this->notes : null,
                'established_at' => $this->establishedAt !== null && $this->establishedAt !== '' ? $this->establishedAt : null,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->addError('toInterfaceId', $e->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Connessione creata.');
        $this->redirectRoute('connections.index', navigate: true);
    }

    public function render(): View
    {
        $equipment = Equipment::query()->with('interfaces')->orderBy('name')->get();

        // Available interfaces excluding ones already in an active connection
        $busyIds = Connection::query()
            ->where('status', 'active')
            ->pluck('from_interface_id')
            ->merge(Connection::query()->where('status', 'active')->pluck('to_interface_id'))
            ->unique()
            ->all();

        return view('livewire.connections.wizard', [
            'equipment' => $equipment,
            'busyIds' => $busyIds,
        ]);
    }
}
