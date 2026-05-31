<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WifiNetwork;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WifiNetwork>
 */
class WifiNetworkFactory extends Factory
{
    protected $model = WifiNetwork::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ssid' => $this->faker->unique()->words(2, true),
            'security_type' => $this->faker->randomElement(['wpa2', 'wpa3', 'wpa2_ent', 'open']),
            'vlan_id' => null,
            'hidden_ssid' => false,
            'notes' => null,
        ];
    }
}
