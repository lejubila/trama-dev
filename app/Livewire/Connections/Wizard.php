<?php

declare(strict_types=1);

namespace App\Livewire\Connections;

use App\Enums\EquipmentType;
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

    /** When ?from_iface=<id> lands on the page, pre-select Estremo A and jump
     *  straight to step 2 — useful for the per-port "Crea connessione" button
     *  on the equipment page. */
    #[Url(as: 'from_iface')]
    public ?int $fromInterfaceParam = null;

    /** Volatile toggle for step 2: when true, the destination list ignores
     *  the side/media compatibility filter and shows every interface. Reset
     *  on back() so a different origin always starts from the filtered view. */
    public bool $showAllTargets = false;

    public function mount(): void
    {
        $this->authorize('create', Connection::class);

        if ($this->fromInterfaceParam !== null && $this->fromInterfaceParam > 0) {
            // Verify the iface exists (and is visible under the current
            // tenant scope) before pre-selecting, so a stale URL doesn't
            // ghost-fill the form with an unauthorised id.
            $iface = NetworkInterface::query()->find($this->fromInterfaceParam);
            if ($iface !== null) {
                $this->fromInterfaceId = $iface->getKey();
                if ($this->fromEquipmentId === null) {
                    $this->fromEquipmentId = (int) $iface->equipment_id;
                }
                $this->step = 2;
            }
        }
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
            // Reset the volatile target-filter override so changing the
            // origin at step 1 always re-applies the compatibility filter.
            $this->showAllTargets = false;
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
        $equipment = Equipment::query()
            ->with(['interfaces', 'rack:id,room_id'])
            ->orderBy('name')
            ->get();

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
            ? NetworkInterface::query()
                ->with(['equipment.rack:id,room_id'])
                ->find($this->fromInterfaceId)
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

        // Step 2 dataset: apply the side/media compatibility filter unless
        // the user explicitly opted into seeing everything, or the origin is
        // not yet known. We rebuild the equipment collection with filtered
        // `interfaces` so the view markup stays identical.
        $targetsFiltered = false;
        $equipmentStep2 = $equipment;
        if (! $this->showAllTargets && $fromInterface !== null) {
            $predicate = $this->compatibleTargetFilter($fromInterface);
            $equipmentStep2 = $equipment
                ->map(function ($eq) use ($predicate) {
                    $eq = clone $eq;
                    $eq->setRelation('interfaces', $eq->interfaces->filter($predicate)->values());

                    return $eq;
                })
                ->filter(fn ($eq) => $eq->interfaces->isNotEmpty())
                ->values();
            $targetsFiltered = true;
        }

        return view('livewire.connections.wizard', [
            'equipment' => $equipment,
            'equipmentStep1' => $equipmentStep1,
            'equipmentStep2' => $equipmentStep2,
            'targetsFiltered' => $targetsFiltered,
            'busyIds' => $busyIds,
            'fromInterface' => $fromInterface,
            'toInterface' => $toInterface,
            'colorPresets' => CableColors::presets(),
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Returns the predicate used to filter the step-2 destination list given
     * the origin interface. Rules:
     *  - origin rear on a patch panel: only rear destinations AND proximity
     *    (same rack, or unracked same locale).
     *  - origin rear elsewhere (wall outlet): only rear, ignoring proximity
     *    (passive endpoints are physically pinned to one location, dorsali
     *    don't make sense from them).
     *  - origin non-rear: exclude rear, require same media, apply proximity.
     */
    private function compatibleTargetFilter(NetworkInterface $from): \Closure
    {
        $fromEq = $from->equipment;
        $fromRack = $fromEq?->rack_id;
        // Locale di riferimento: il locale del rack se il dispositivo è
        // racked, altrimenti il suo room_id diretto.
        $fromRoom = $fromEq?->rack?->room_id ?? $fromEq?->room_id;

        if ($from->isRear()) {
            $applyProximity = $fromEq?->type === EquipmentType::PatchPanel;
            if (! $applyProximity) {
                return fn (NetworkInterface $if): bool => $if->isRear();
            }

            return function (NetworkInterface $if) use ($fromRack, $fromRoom): bool {
                if (! $if->isRear()) {
                    return false;
                }
                $eq = $if->equipment;
                if (! $eq) {
                    return false;
                }
                $sameRack = $fromRack !== null && $eq->rack_id === $fromRack;
                $sameRoom = $fromRoom !== null
                    && $eq->rack_id === null
                    && $eq->room_id === $fromRoom;

                return $sameRack || $sameRoom;
            };
        }

        $fromMedia = $from->media;

        return function (NetworkInterface $if) use ($fromRack, $fromRoom, $fromMedia): bool {
            if ($if->isRear()) {
                return false;
            }
            if ($fromMedia !== null && $if->media !== $fromMedia) {
                return false;
            }

            $eq = $if->equipment;
            if (! $eq) {
                return false;
            }

            // Prossimità fisica: stesso rack oppure unracked nello stesso locale.
            $sameRack = $fromRack !== null && $eq->rack_id === $fromRack;
            $sameRoom = $fromRoom !== null
                && $eq->rack_id === null
                && $eq->room_id === $fromRoom;

            return $sameRack || $sameRoom;
        };
    }
}
