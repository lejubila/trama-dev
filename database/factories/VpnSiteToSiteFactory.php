<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VpnProtocol;
use App\Models\VpnSiteToSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VpnSiteToSite>
 */
class VpnSiteToSiteFactory extends Factory
{
    protected $model = VpnSiteToSite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->city().' tunnel',
            'protocol' => VpnProtocol::Ipsec->value,
            'routed_vlans_a' => null,
            'routed_vlans_b' => null,
            'notes' => null,
        ];
    }
}
