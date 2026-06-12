<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InterfaceMedia;
use App\Enums\InterfacePoe;
use App\Enums\InterfaceStatus;
use App\Enums\InterfaceType;
use App\Enums\InterfaceVlanMode;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetworkInterface>
 */
class NetworkInterfaceFactory extends Factory
{
    protected $model = NetworkInterface::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
            'name' => 'Gi0/'.$this->faker->numberBetween(1, 48),
            'type' => InterfaceType::Ethernet,
            'index' => 0,
            'speed_mbps' => 1000,
            'media' => InterfaceMedia::Copper,
            'connector' => 'RJ45',
            'vlan_mode' => InterfaceVlanMode::Access,
            'vlan_default' => 1,
            'vlans_allowed' => null,
            'ip_address' => null,
            'mac_address' => null,
            'status' => InterfaceStatus::Up,
            'poe' => InterfacePoe::None,
            'description' => null,
            'custom_fields' => [],
        ];
    }

    public function ethernet(): self
    {
        return $this->state([
            'type' => InterfaceType::Ethernet,
            'media' => InterfaceMedia::Copper,
            'connector' => 'RJ45',
            'speed_mbps' => 1000,
        ]);
    }

    public function fiber(int $speedMbps = 10000, string $connector = 'SFP+'): self
    {
        return $this->state([
            'type' => InterfaceType::Fiber,
            'media' => InterfaceMedia::Fiber,
            'connector' => $connector,
            'speed_mbps' => $speedMbps,
        ]);
    }

    public function wireless(): self
    {
        return $this->state([
            'type' => InterfaceType::Wireless,
            'media' => InterfaceMedia::Wireless,
            'connector' => null,
            'speed_mbps' => null,
            'vlan_mode' => InterfaceVlanMode::None,
            'vlan_default' => null,
        ]);
    }

    /**
     * @param  list<int>  $allowed
     */
    public function backedBy(NetworkInterface $pnic): self
    {
        return $this->state([
            'type' => InterfaceType::Virtual,
            'media' => InterfaceMedia::Copper,
            'connector' => null,
            'backed_by_interface_id' => $pnic->getKey(),
        ]);
    }

    /**
     * @param  list<int>  $allowed
     */
    public function trunk(int $defaultVlan = 1, array $allowed = [10, 20, 30]): self
    {
        return $this->state([
            'vlan_mode' => InterfaceVlanMode::Trunk,
            'vlan_default' => $defaultVlan,
            'vlans_allowed' => $allowed,
        ]);
    }
}
