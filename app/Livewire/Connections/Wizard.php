<?php

declare(strict_types=1);

namespace App\Livewire\Connections;

use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Tag;
use App\Services\ConnectionService;
use App\Support\CableColors;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
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

    /** @var array<int, int|string> */
    public array $selectedTagIds = [];

    /** When ?from_equipment=<id> lands on the page, scope step 1 (Estremo A)
     *  to only the interfaces of that equipment. Step 2 stays unrestricted. */
    #[Url(as: 'from_equipment')]
    public ?int $fromEquipmentId = null;

    public function mount(): void
    {
        $this->authorize('create', Connection::class);
    }

    public function next(): void
    {
        if ($this->step === 1) {
            if ($this->fromInterfaceId === null) {
                $this->addError('fromInterfaceId', 'Scegli l\'interfaccia di partenza.');

                return;
            }
            // Step 2 reuses a <select> structurally identical to step 1's,
            // so morphdom may carry over the previous DOM value. Clear the
            // server-side property to keep a single source of truth.
            $this->toInterfaceId = null;
            $this->step = 2;

            return;
        }

        if ($this->step === 2) {
            if ($this->toInterfaceId === null) {
                $this->addError('toInterfaceId', 'Scegli l\'interfaccia di destinazione.');

                return;
            }
            $this->step = 3;
        }
    }

    public function back(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function save(ConnectionService $service): void
    {
        // Clear stale errors so a retry doesn't show last-attempt residue.
        $this->resetValidation();

        $this->validate([
            'fromInterfaceId' => 'required|exists:interfaces,id',
            'toInterfaceId' => 'required|exists:interfaces,id|different:fromInterfaceId',
            'cableType' => 'required|string|max:30',
            'cableLengthM' => 'nullable|numeric|min:0',
            'cableLabel' => 'nullable|string|max:80',
            'color' => ['nullable', 'string', 'regex:'.CableColors::HEX_REGEX],
            'notes' => 'nullable|string|max:2000',
            'establishedAt' => 'nullable|date',
            'selectedTagIds' => 'array',
            'selectedTagIds.*' => 'integer|exists:tags,id',
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

        $conn->tags()->sync($this->selectedTagIds);

        $this->dispatch('toast', type: 'success', message: 'Connessione creata.');
        $this->redirectAfterExit();
    }

    /**
     * Where to send the browser after save/cancel: back to the originating
     * equipment's Connessioni tab when the wizard was opened from a device,
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
        $equipment = Equipment::query()->with('interfaces')->orderBy('name')->get();

        // Available interfaces excluding ones already in an active connection
        $busyIds = Connection::query()
            ->where('status', 'active')
            ->pluck('from_interface_id')
            ->merge(Connection::query()->where('status', 'active')->pluck('to_interface_id'))
            ->unique()
            ->all();

        // Resolve the currently-chosen endpoints so the step 3 summary can
        // show the user what they're about to commit. If an ID is null or
        // stale we just pass null and the view handles the dash fallback.
        $fromInterface = $this->fromInterfaceId !== null
            ? NetworkInterface::query()->with('equipment')->find($this->fromInterfaceId)
            : null;
        $toInterface = $this->toInterfaceId !== null
            ? NetworkInterface::query()->with('equipment')->find($this->toInterfaceId)
            : null;

        // When the wizard was launched from a device page (?from_equipment=ID)
        // restrict the step-1 select to that device's interfaces. Step 2 keeps
        // the full list so the user can wire the other end to anything.
        $equipmentStep1 = $this->fromEquipmentId !== null
            ? $equipment->where('id', $this->fromEquipmentId)->values()
            : $equipment;

        return view('livewire.connections.wizard', [
            'equipment' => $equipment,
            'equipmentStep1' => $equipmentStep1,
            'busyIds' => $busyIds,
            'fromInterface' => $fromInterface,
            'toInterface' => $toInterface,
            'colorPresets' => CableColors::presets(),
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }
}
