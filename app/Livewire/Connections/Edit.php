<?php

declare(strict_types=1);

namespace App\Livewire\Connections;

use App\Enums\ConnectionStatus;
use App\Models\Connection;
use App\Support\CableColors;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Connection $connection;

    public string $cableType = '';

    public ?string $cableLengthM = null;

    public string $cableLabel = '';

    public string $color = '';

    public string $notes = '';

    public ?string $establishedAt = null;

    public string $status = '';

    /** When ?from_equipment=<id> lands on the page, save/cancel returns to
     *  that equipment's Connessioni tab instead of the global list. */
    #[Url(as: 'from_equipment')]
    public ?int $fromEquipmentId = null;

    public function mount(Connection $connection): void
    {
        $this->authorize('update', $connection);

        $this->connection = $connection;
        $this->cableType = $connection->cable_type;
        $this->cableLengthM = $connection->cable_length_m !== null ? (string) $connection->cable_length_m : null;
        $this->cableLabel = $connection->cable_label ?? '';
        $this->color = $connection->color ?? '';
        $this->notes = $connection->notes ?? '';
        $this->establishedAt = $connection->established_at?->format('Y-m-d');
        $this->status = $connection->status?->value ?? ConnectionStatus::Active->value;
    }

    public function save(): void
    {
        $this->resetValidation();

        $data = $this->validate([
            'cableType' => 'required|string|max:30',
            'cableLengthM' => 'nullable|numeric|min:0',
            'cableLabel' => 'nullable|string|max:80',
            'color' => ['nullable', 'string', 'regex:'.CableColors::HEX_REGEX],
            'notes' => 'nullable|string|max:2000',
            'establishedAt' => 'nullable|date',
            'status' => 'required|in:'.implode(',', array_map(fn ($c) => $c->value, ConnectionStatus::cases())),
        ]);

        $this->connection->update([
            'cable_type' => $data['cableType'],
            'cable_length_m' => $this->cableLengthM !== null && $this->cableLengthM !== '' ? (float) $this->cableLengthM : null,
            'cable_label' => $this->cableLabel !== '' ? $this->cableLabel : null,
            'color' => $this->color !== '' ? $this->color : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
            'established_at' => $this->establishedAt !== null && $this->establishedAt !== '' ? $this->establishedAt : null,
            'status' => $this->status,
        ]);

        $this->dispatch('toast', type: 'success', message: 'Connessione aggiornata.');
        $this->redirectAfterExit();
    }

    /**
     * Where to send the browser after save/cancel: back to the originating
     * equipment's Connessioni tab when the edit was opened from a device,
     * otherwise to the global connections list.
     */
    private function redirectAfterExit(): void
    {
        if ($this->fromEquipmentId !== null) {
            $this->redirectRoute('equipment.show', [
                'equipment' => $this->fromEquipmentId,
                'tab' => 'connections',
            ], navigate: true);

            return;
        }

        $this->redirectRoute('connections.index', navigate: true);
    }

    public function render(): View
    {
        $this->connection->loadMissing(['fromInterface.equipment', 'toInterface.equipment']);

        return view('livewire.connections.edit', [
            'colorPresets' => CableColors::presets(),
            'statuses' => ConnectionStatus::cases(),
        ]);
    }
}
