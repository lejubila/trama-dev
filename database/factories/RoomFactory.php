<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Room;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => 'CED '.$this->faker->unique()->bothify('##'),
            'floor' => $this->faker->randomElement(['Piano 0', 'Piano 1', 'Piano -1', 'S1']),
            'notes' => null,
        ];
    }
}
