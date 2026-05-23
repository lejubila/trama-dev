<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    private const VENDORS = ['Cisco', 'HPE Aruba', 'Juniper', 'MikroTik', 'Fortinet', 'Ubiquiti', 'Dell', 'Netgear'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(EquipmentType::cases());

        return [
            'name' => $this->prefixForType($type).'-'.$this->faker->numberBetween(1, 9999),
            'type' => $type,
            'vendor' => $this->faker->randomElement(self::VENDORS),
            'model' => $this->faker->bothify('Model-####'),
            'serial' => strtoupper($this->faker->bothify('SN##??##??##')),
            'firmware' => $this->faker->semver(),
            'asset_tag' => $this->faker->bothify('AT-####'),
            'mounted' => false,
            'status' => EquipmentStatus::Active,
            'description' => null,
            'custom_fields' => [],
        ];
    }

    public function mountedAt(int $startU, int $heightU = 1): self
    {
        return $this->state([
            'mounted' => true,
            'position_u_start' => $startU,
            'position_u_height' => $heightU,
            'position_orient' => 'front',
        ]);
    }

    public function ofType(EquipmentType $type): self
    {
        return $this->state([
            'type' => $type,
            'name' => $this->prefixForType($type).'-'.$this->faker->numberBetween(1, 9999),
        ]);
    }

    private function prefixForType(EquipmentType $type): string
    {
        return match ($type) {
            EquipmentType::Switch => 'SW',
            EquipmentType::Router => 'RTR',
            EquipmentType::Firewall => 'FW',
            EquipmentType::AccessPoint => 'AP',
            EquipmentType::Controller => 'WLC',
            EquipmentType::PatchPanel => 'PP',
            EquipmentType::WallOutlet => 'WO',
            EquipmentType::Server => 'SRV',
            EquipmentType::Ups => 'UPS',
            EquipmentType::Pdu => 'PDU',
            EquipmentType::MediaConverter => 'MC',
            EquipmentType::Nas => 'NAS',
            EquipmentType::Kvm => 'KVM',
            EquipmentType::PhoneSystem => 'PBX',
            EquipmentType::AccessControl => 'ACS',
            EquipmentType::Nvr => 'NVR',
            EquipmentType::Camera => 'CAM',
            EquipmentType::Intercom => 'INT',
            EquipmentType::Other => 'DEV',
        };
    }
}
