<?php

declare(strict_types=1);

namespace App\Livewire\Equipment\Concerns;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Validation\TenantExists;
use Illuminate\Validation\Rule;

/**
 * Descriptive fields of a device, shared by the equipment form and the
 * create-from-rack-slot form. Placement stays with each component.
 */
trait EditsEquipmentDetails
{
    public string $name = '';

    public string $type = 'switch';

    public string $vendor = '';

    public string $modelName = '';

    public string $serial = '';

    public string $firmware = '';

    public string $assetTag = '';

    public string $managementIp = '';

    public string $status = 'active';

    public bool $locked = false;

    public bool $hiddenInTopology = false;

    public string $description = '';

    public mixed $iconUpload = null;

    /** @var array<int, int|string> */
    public array $selectedTagIds = [];

    protected function resetDetails(): void
    {
        $this->reset(['name', 'type', 'vendor', 'modelName', 'serial', 'firmware', 'assetTag', 'managementIp', 'status', 'locked', 'hiddenInTopology', 'description', 'iconUpload', 'selectedTagIds']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailRules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'type' => ['required', Rule::enum(EquipmentType::class)],
            'vendor' => 'nullable|string|max:80',
            'modelName' => 'nullable|string|max:120',
            'serial' => 'nullable|string|max:120',
            'firmware' => 'nullable|string|max:80',
            'assetTag' => 'nullable|string|max:80',
            'managementIp' => 'nullable|ip',
            'status' => ['required', Rule::enum(EquipmentStatus::class)],
            'locked' => 'boolean',
            'hiddenInTopology' => 'boolean',
            'description' => 'nullable|string|max:5000',
            'iconUpload' => 'nullable|image|max:5120',
            'selectedTagIds' => 'array',
            'selectedTagIds.*' => ['integer', TenantExists::in('tags', softDeletes: false)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailAttributes(): array
    {
        return [
            'name' => $this->name,
            'type' => EquipmentType::from($this->type),
            'vendor' => $this->vendor !== '' ? $this->vendor : null,
            'model' => $this->modelName !== '' ? $this->modelName : null,
            'serial' => $this->serial !== '' ? $this->serial : null,
            'firmware' => $this->firmware !== '' ? $this->firmware : null,
            'asset_tag' => $this->assetTag !== '' ? $this->assetTag : null,
            'management_ip' => $this->managementIp !== '' ? $this->managementIp : null,
            'locked' => $this->locked,
            'hidden_in_topology' => $this->hiddenInTopology,
            'status' => EquipmentStatus::from($this->status),
            'description' => $this->description !== '' ? $this->description : null,
        ];
    }
}
