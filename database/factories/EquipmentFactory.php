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
        // Skip VirtualMachine/Hypervisor in the default pool: they need a
        // host or explicit setup. Call ->hypervisor() / ->virtualMachine($host)
        // explicitly when those are desired.
        $pool = array_values(array_filter(
            EquipmentType::cases(),
            fn (EquipmentType $t) => ! in_array($t, [EquipmentType::Hypervisor, EquipmentType::VirtualMachine], true),
        ));
        $type = $this->faker->randomElement($pool);

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
            EquipmentType::Tv => 'TV',
            EquipmentType::Computer => 'PC',
            EquipmentType::Notebook => 'NB',
            EquipmentType::IotDevice => 'IOT',
            EquipmentType::Printer => 'PRN',
            EquipmentType::Hypervisor => 'HV',
            EquipmentType::VirtualMachine => 'VM',
            EquipmentType::Other => 'DEV',
        };
    }

    public function hypervisor(?\App\Enums\HypervisorVendor $vendor = null): self
    {
        $vendor ??= \App\Enums\HypervisorVendor::Proxmox;

        return $this->state(fn (array $attrs) => [
            'type' => EquipmentType::Hypervisor,
            'name' => $this->prefixForType(EquipmentType::Hypervisor).'-'.$this->faker->numberBetween(1, 9999),
            'custom_fields' => ['hypervisor_vendor' => $vendor->value],
        ]);
    }

    public function virtualMachine(Equipment $host, ?string $guestOs = null): self
    {
        return $this->state(fn (array $attrs) => [
            'type' => EquipmentType::VirtualMachine,
            'name' => $this->prefixForType(EquipmentType::VirtualMachine).'-'.$this->faker->numberBetween(1, 9999),
            'host_equipment_id' => $host->getKey(),
            'tenant_id' => $host->tenant_id,
            'rack_id' => null,
            'mounted' => false,
            'room_id' => $host->room_id ?? $host->rack?->room_id,
            'custom_fields' => [
                'vcpu' => $this->faker->numberBetween(1, 16),
                'ram_mb' => $this->faker->randomElement([1024, 2048, 4096, 8192, 16384]),
                'disk_gb' => $this->faker->randomElement([20, 50, 100, 250, 500]),
                'guest_os' => $guestOs ?? $this->faker->randomElement(['Ubuntu 22.04', 'Debian 12', 'Windows Server 2022', 'Rocky Linux 9']),
            ],
        ]);
    }
}
