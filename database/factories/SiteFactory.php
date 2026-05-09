<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->city(),
            'address' => $this->faker->streetAddress().', '.$this->faker->city(),
            'latitude' => $this->faker->latitude(36, 47),
            'longitude' => $this->faker->longitude(7, 18),
            'notes' => null,
        ];
    }
}
