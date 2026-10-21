<?php

declare(strict_types=1);

namespace App\Livewire\Connections;

use App\Enums\ConnectionStatus;
use App\Livewire\Connections\Concerns\EditsCable;
use App\Models\Connection;
use App\Models\Tag;
use App\Support\CableColors;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    use EditsCable;

    public Connection $connection;

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
        $this->selectedTagIds = $connection->tags()->pluck('tags.id')->all();
    }

    public function save(): void
    {
        $this->resetValidation();

        $this->validate([
            ...$this->cableRules(),
            'status' => ['required', Rule::enum(ConnectionStatus::class)],
        ]);

        $this->connection->update([...$this->cableAttributes(), 'status' => $this->status]);

        $this->connection->tags()->sync($this->selectedTagIds);

        $this->dispatch('toast', type: 'success', message: __('connections.toast_updated'));
        $this->redirectAfterExit();
    }

    public function render(): View
    {
        $this->connection->loadMissing(['fromInterface.equipment', 'toInterface.equipment']);

        return view('livewire.connections.edit', [
            'colorPresets' => CableColors::presets(),
            'statuses' => ConnectionStatus::cases(),
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }
}
