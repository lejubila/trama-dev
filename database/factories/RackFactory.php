<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RackNumbering;
use App\Models\Rack;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rack>
 */
class RackFactory extends Factory
{
    protected $model = Rack::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'name' => 'Rack-'.$this->faker->unique()->bothify('?##'),
            'height_units' => $this->faker->randomElement([24, 36, 42, 47]),
            'width_mm' => 600,
            'depth_mm' => $this->faker->randomElement([800, 1000, 1200]),
            'numbering' => RackNumbering::BottomUp,
            'notes' => null,
        ];
    }
}
