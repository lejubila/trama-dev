<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConnectionStatus;
use App\Models\Connection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Endpoints (from_interface_id, to_interface_id) are intentionally not auto-
 * generated: callers must pass valid interface ids. This is to avoid creating
 * cross-tenant connections by accident in tests/seeders.
 *
 * @extends Factory<Connection>
 */
class ConnectionFactory extends Factory
{
    protected $model = Connection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cable_type' => $this->faker->randomElement(['utp_cat6', 'utp_cat6a', 'fiber_om3', 'fiber_om4', 'dac']),
            'cable_length_m' => $this->faker->randomFloat(2, 0.5, 50),
            'cable_label' => null,
            'color' => $this->faker->randomElement(['blue', 'red', 'yellow', 'green', 'gray', null]),
            'status' => ConnectionStatus::Active,
            'notes' => null,
            'established_at' => $this->faker->date(),
        ];
    }
}
