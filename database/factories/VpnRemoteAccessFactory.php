<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VpnProtocol;
use App\Models\VpnRemoteAccess;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VpnRemoteAccess>
 */
class VpnRemoteAccessFactory extends Factory
{
    protected $model = VpnRemoteAccess::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company().' VPN',
            'protocol' => VpnProtocol::WireGuard->value,
            'routed_vlans' => null,
            'notes' => null,
        ];
    }
}
