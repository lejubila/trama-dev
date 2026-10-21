<?php

declare(strict_types=1);

namespace App\Livewire\Connections\Concerns;

use App\Support\CableColors;
use App\Validation\TenantExists;

/**
 * Cable fields shared by the connection wizard and the edit page, plus the
 * return trip to the device the user came from.
 */
trait EditsCable
{
    public string $cableType = 'utp_cat6';

    public ?string $cableLengthM = null;

    public string $cableLabel = '';

    public string $color = '';

    public string $notes = '';

    public ?string $establishedAt = null;

    /** @var array<int, int|string> */
    public array $selectedTagIds = [];

    /**
     * @return array<string, mixed>
     */
    protected function cableRules(): array
    {
        return [
            'cableType' => 'required|string|max:30',
            'cableLengthM' => 'nullable|numeric|min:0',
            'cableLabel' => 'nullable|string|max:80',
            'color' => ['nullable', 'string', 'regex:'.CableColors::HEX_REGEX],
            'notes' => 'nullable|string|max:5000',
            'establishedAt' => 'nullable|date',
            'selectedTagIds' => 'array',
            'selectedTagIds.*' => ['integer', TenantExists::in('tags', softDeletes: false)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function cableAttributes(): array
    {
        return [
            'cable_type' => $this->cableType,
            'cable_length_m' => $this->cableLengthM !== null && $this->cableLengthM !== '' ? (float) $this->cableLengthM : null,
            'cable_label' => $this->cableLabel !== '' ? $this->cableLabel : null,
            'color' => $this->color !== '' ? $this->color : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
            'established_at' => $this->establishedAt !== null && $this->establishedAt !== '' ? $this->establishedAt : null,
        ];
    }

    /**
     * Back to the originating equipment's Connessioni tab when the page was
     * opened from a device, otherwise to the global connections list.
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
}
