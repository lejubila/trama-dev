<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WifiAssociation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WifiAssociation>
 */
class WifiAssociationFactory extends Factory
{
    protected $model = WifiAssociation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // wifi_network_id and client_interface_id must be supplied by the
            // caller — they belong to a real graph fixture.
            'preferred_broadcaster_interface_id' => null,
        ];
    }
}
